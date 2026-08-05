<?php

namespace App\Controllers;

use App\Libraries\DataScope;
use App\Models\CheckStatusModel;
use App\Models\EncounterMaskModel;
use App\Models\HospitalModel;
use CodeIgniter\HTTP\ResponseInterface;

class Encounters extends BaseController
{
    public function index()
    {
        $filters = $this->filters();
        $perPage = 25;

        $scope     = $this->scope();
        $hospitals = $this->hospitalOptions($scope);
        $model     = new EncounterMaskModel();
        $rows      = $model->scoped($scope, $filters)
            ->orderBy('encounter_masks.create_datetime', 'DESC')
            ->orderBy('encounter_masks.id', 'DESC')
            ->paginate($perPage);

        return view('encounters/index', [
            'rows'     => $rows,
            'pager'    => $model->pager,
            'filters'  => $filters,
            'statuses' => (new CheckStatusModel())->ordered(),
            // ตัวกรอง + คอลัมน์หน่วยบริการมีประโยชน์เมื่อขอบเขตครอบมากกว่า 1 แห่ง
            'hospitals'  => $hospitals,
            'multiUnit'  => $hospitals !== [],
            'isAdmin'    => $this->isAdmin(),
            'scopeLabel' => $scope->label(),
            'user'       => $this->currentUser(),
        ]);
    }

    /**
     * กำหนดสถานะการตรวจสอบของ 1 รายการ + stamp วันที่ตรวจสอบ
     */
    public function updateStatus()
    {
        $id       = (int) $this->request->getPost('id');
        $statusId = (int) $this->request->getPost('check_status_id');
        $note     = trim((string) $this->request->getPost('check_note'));

        if (! $this->statusExists($statusId)) {
            return redirect()->back()->with('error', 'สถานะไม่ถูกต้อง');
        }

        $model = new EncounterMaskModel();
        $row   = $model->find($id);

        if ($row === null || ! $this->canTouch($row)) {
            return redirect()->back()->with('error', 'ไม่พบรายการ หรือไม่มีสิทธิ์แก้ไขรายการนี้');
        }

        $model->update($id, [
            'check_status_id' => $statusId,
            'check_note'      => $note !== '' ? $note : null,
            'checked_at'      => phr_now(),
            'checked_by'      => (int) $this->session->get('user_id'),
        ]);

        return redirect()->back()->with('success', 'บันทึกสถานะการตรวจสอบแล้ว');
    }

    /**
     * กำหนดสถานะพร้อมกันหลายรายการ
     */
    public function bulkStatus()
    {
        $ids      = $this->request->getPost('ids');
        $statusId = (int) $this->request->getPost('check_status_id');

        if (! is_array($ids) || $ids === []) {
            return redirect()->back()->with('error', 'ยังไม่ได้เลือกรายการ');
        }

        if (! $this->statusExists($statusId)) {
            return redirect()->back()->with('error', 'สถานะไม่ถูกต้อง');
        }

        $ids   = array_map('intval', $ids);
        $model = new EncounterMaskModel();

        $builder = $model->whereIn('id', $ids);

        // ใส่ขอบเขตซ้ำใน WHERE — กันการยิง id ของหน่วยบริการนอกขอบเขตเข้ามาตรง ๆ
        $condition = $this->scope()->sqlCondition('code');

        if ($condition !== '') {
            $builder->where($condition, null, false);
        }

        $builder->set([
            'check_status_id' => $statusId,
            'check_note'      => null,
            'checked_at'      => phr_now(),
            'checked_by'      => (int) $this->session->get('user_id'),
            'updated_at'      => phr_now(),
        ])->update();

        $affected = $model->db->affectedRows();

        return redirect()->back()->with('success', "บันทึกสถานะ {$affected} รายการแล้ว");
    }

    /**
     * ดาวน์โหลดผลการตรวจสอบตามเงื่อนไขที่กรองอยู่
     */
    public function export(): ResponseInterface
    {
        $rows = (new EncounterMaskModel())
            ->scoped($this->scope(), $this->filters())
            ->orderBy('encounter_masks.create_datetime', 'DESC')
            ->findAll(50000);

        $handle = fopen('php://temp', 'r+b');

        // BOM ให้ Excel รู้ว่าเป็น UTF-8 ไม่งั้นภาษาไทยเพี้ยน
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'code', 'hosname', 'cid', 'encounter_ref_code', 'officer_name',
            'process_note', 'create_datetime', 'process_datetime', 'update_datetime',
            'check_status', 'check_note', 'checked_at',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['code'],
                $row['hosname'],
                $row['cid'],
                $row['encounter_ref_code'],
                $row['officer_name'],
                $row['process_note'],
                $row['create_datetime'],
                $row['process_datetime'],
                $row['update_datetime'],
                $row['status_name'],
                $row['check_note'],
                $row['checked_at'],
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="phrcheck_export_' . date('Ymd_His') . '.csv"')
            ->setBody($csv);
    }

    /**
     * @return array<string, string>
     */
    private function filters(): array
    {
        // ตัวกรองหน่วยบริการเปิดให้เฉพาะคนที่ขอบเขตครอบมากกว่า 1 แห่ง
        // ส่วนคนที่มีหน่วยบริการเดียว ปล่อยให้กรองก็ไม่มีความหมาย
        $canFilterByCode = $this->isAdmin() || $this->isDistrictAdmin();

        return [
            'q'         => trim((string) $this->request->getGet('q')),
            'status'    => (string) $this->request->getGet('status'),
            'code'      => $canFilterByCode ? (string) $this->request->getGet('code') : '',
            'date_from' => (string) $this->request->getGet('date_from'),
            'date_to'   => (string) $this->request->getGet('date_to'),
        ];
    }

    /**
     * รายชื่อหน่วยบริการสำหรับ dropdown ตัวกรอง — จำกัดตามขอบเขตของผู้ใช้
     *
     * @return list<array<string, mixed>>
     */
    private function hospitalOptions(DataScope $scope): array
    {
        $hospitals = new HospitalModel();

        if ($scope->isAll()) {
            return $hospitals->options();
        }

        if ($scope->isDistrict()) {
            return $hospitals->inDistrict((string) $this->session->get('distcode'));
        }

        return [];
    }

    private function statusExists(int $statusId): bool
    {
        return (new CheckStatusModel())->find($statusId) !== null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function canTouch(array $row): bool
    {
        return $this->scope()->allows($row['code'] ?? null);
    }
}
