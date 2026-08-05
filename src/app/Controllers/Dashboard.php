<?php

namespace App\Controllers;

use App\Models\CheckStatusModel;
use CodeIgniter\HTTP\ResponseInterface;

class Dashboard extends BaseController
{
    /** จำนวนวันย้อนหลังที่แสดงในกราฟรายวัน */
    private const DEFAULT_DAYS = 30;

    public function index()
    {
        $statuses = (new CheckStatusModel())->ordered();

        return view('dashboard/index', [
            'statuses'   => $statuses,
            'summary'    => $this->summary(),
            'byStatus'   => $this->byStatus($statuses),
            'byHospital' => $this->byHospital($statuses),
            'days'       => $this->days(),
            'isAdmin'    => $this->isAdmin(),
            'scopeLabel' => $this->scope()->label(),
            'user'       => $this->currentUser(),
        ]);
    }

    /**
     * ข้อมูลกราฟรายวัน (เรียกจาก JS)
     */
    public function data(): ResponseInterface
    {
        $statuses = (new CheckStatusModel())->ordered();

        return $this->response->setJSON([
            'labels'   => $this->dailyLabels(),
            'datasets' => $this->dailySeries($statuses),
        ]);
    }

    private function days(): int
    {
        $days = (int) $this->request->getGet('days');

        return in_array($days, [7, 30, 90, 365], true) ? $days : self::DEFAULT_DAYS;
    }

    /**
     * ใส่เงื่อนไขขอบเขตหน่วยบริการให้ query ทุกตัว
     */
    private function scopeSql(string $column = 'code'): string
    {
        $condition = $this->scope()->sqlCondition($column);

        return $condition === '' ? '' : ' AND ' . $condition;
    }

    /**
     * @return array<string, int>
     */
    private function summary(): array
    {
        $db  = db_connect();
        $sql = 'SELECT COUNT(*) AS records,
                       COUNT(DISTINCT cid) AS persons,
                       SUM(CASE WHEN checked_at IS NOT NULL THEN 1 ELSE 0 END) AS checked,
                       SUM(CASE WHEN checked_at IS NULL THEN 1 ELSE 0 END) AS unchecked,
                       SUM(CASE WHEN DATE(checked_at) = CURDATE() THEN 1 ELSE 0 END) AS checked_today
                FROM encounter_masks
                WHERE 1 = 1' . $this->scopeSql();

        $row = $db->query($sql)->getRowArray() ?? [];

        return [
            'records'       => (int) ($row['records'] ?? 0),
            'persons'       => (int) ($row['persons'] ?? 0),
            'checked'       => (int) ($row['checked'] ?? 0),
            'unchecked'     => (int) ($row['unchecked'] ?? 0),
            'checked_today' => (int) ($row['checked_today'] ?? 0),
        ];
    }

    /**
     * จำนวนรายการแยกตามสถานะ
     *
     * @param list<array<string, mixed>> $statuses
     *
     * @return array<int, int>
     */
    private function byStatus(array $statuses): array
    {
        $db  = db_connect();
        $sql = 'SELECT check_status_id, COUNT(*) AS c
                FROM encounter_masks
                WHERE 1 = 1' . $this->scopeSql() . '
                GROUP BY check_status_id';

        $counts = array_fill_keys(array_column($statuses, 'id'), 0);

        foreach ($db->query($sql)->getResultArray() as $row) {
            $counts[(int) $row['check_status_id']] = (int) $row['c'];
        }

        return $counts;
    }

    /**
     * ตารางสรุปรายหน่วยบริการ (เหมือนรายงานต้นฉบับ)
     *
     * @param list<array<string, mixed>> $statuses
     *
     * @return list<array<string, mixed>>
     */
    private function byHospital(array $statuses): array
    {
        $db      = db_connect();
        $columns = [];

        foreach ($statuses as $status) {
            $id        = (int) $status['id'];
            $columns[] = "SUM(CASE WHEN m.check_status_id = {$id} THEN 1 ELSE 0 END) AS s{$id}";
        }

        $sql = 'SELECT m.code,
                       COALESCE(h.hosname, m.code) AS hosname,
                       h.ampurname,
                       COUNT(*) AS records,
                       COUNT(DISTINCT m.cid) AS persons,
                       ' . implode(",\n                       ", $columns) . '
                FROM encounter_masks m
                LEFT JOIN hospitals h ON h.hoscode = m.code
                WHERE 1 = 1' . $this->scopeSql('m.code') . '
                GROUP BY m.code, h.hosname, h.ampurname
                ORDER BY records DESC';

        return $db->query($sql)->getResultArray();
    }

    /**
     * @return list<string>
     */
    private function dailyLabels(): array
    {
        $labels = [];
        $days   = $this->days();

        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = date('Y-m-d', strtotime("-{$i} day"));
        }

        return $labels;
    }

    /**
     * ชุดข้อมูลกราฟแท่งซ้อน: 1 ชุดต่อ 1 สถานะ นับตามวันที่ตรวจสอบ
     *
     * @param list<array<string, mixed>> $statuses
     *
     * @return list<array<string, mixed>>
     */
    private function dailySeries(array $statuses): array
    {
        $labels = $this->dailyLabels();
        $from   = $labels[0];

        $db  = db_connect();
        $sql = 'SELECT DATE(checked_at) AS d, check_status_id, COUNT(*) AS c
                FROM encounter_masks
                WHERE checked_at IS NOT NULL
                  AND DATE(checked_at) >= ' . $db->escape($from) . $this->scopeSql() . '
                GROUP BY d, check_status_id';

        $grid = [];

        foreach ($db->query($sql)->getResultArray() as $row) {
            $grid[(int) $row['check_status_id']][$row['d']] = (int) $row['c'];
        }

        // สีให้ตรงกับ badge ในตาราง
        $palette = [
            'secondary' => '#6c757d',
            'success'   => '#198754',
            'danger'    => '#dc3545',
            'warning'   => '#ffc107',
            'info'      => '#0dcaf0',
            'dark'      => '#212529',
        ];

        $datasets = [];

        foreach ($statuses as $status) {
            $id   = (int) $status['id'];
            $data = [];

            foreach ($labels as $label) {
                $data[] = $grid[$id][$label] ?? 0;
            }

            // ไม่ต้องแสดงสถานะที่ไม่มีข้อมูลเลยในช่วงนี้
            if (array_sum($data) === 0) {
                continue;
            }

            $datasets[] = [
                'label'           => $status['name'],
                'data'            => $data,
                'backgroundColor' => $palette[$status['color']] ?? '#6c757d',
            ];
        }

        return $datasets;
    }
}
