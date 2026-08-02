<?php

namespace App\Controllers;

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

        $model = new EncounterMaskModel();
        $rows  = $model->scoped($this->scopeHoscode(), $filters)
            ->orderBy('encounter_masks.create_datetime', 'DESC')
            ->orderBy('encounter_masks.id', 'DESC')
            ->paginate($perPage);

        return view('encounters/index', [
            'rows'      => $rows,
            'pager'     => $model->pager,
            'filters'   => $filters,
            'statuses'  => (new CheckStatusModel())->ordered(),
            'hospitals' => $this->isAdmin() ? (new HospitalModel())->options() : [],
            'isAdmin'   => $this->isAdmin(),
            'user'      => $this->currentUser(),
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

        // ผู้ใช้ทั่วไปแก้ได้เฉพาะหน่วยบริการตัวเอง — กันการยิง id ของหน่วยอื่นเข้ามาตรง ๆ
        $scope = $this->scopeHoscode();

        if ($scope !== null) {
            $builder->where('code', $scope);
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
            ->scoped($this->scopeHoscode(), $this->filters())
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
        return [
            'q'         => trim((string) $this->request->getGet('q')),
            'status'    => (string) $this->request->getGet('status'),
            'code'      => $this->isAdmin() ? (string) $this->request->getGet('code') : '',
            'date_from' => (string) $this->request->getGet('date_from'),
            'date_to'   => (string) $this->request->getGet('date_to'),
        ];
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
        $scope = $this->scopeHoscode();

        return $scope === null || $row['code'] === $scope;
    }
}
