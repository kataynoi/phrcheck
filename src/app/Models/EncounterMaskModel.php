<?php

namespace App\Models;

use CodeIgniter\Model;

class EncounterMaskModel extends Model
{
    protected $table         = 'encounter_masks';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'code',
        'phr_encounter_mask_id',
        'cid',
        'encounter_ref_code',
        'process_note',
        'officer_name',
        'process_datetime',
        'create_datetime',
        'update_datetime',
        'check_status_id',
        'check_note',
        'checked_at',
        'checked_by',
        'batch_id',
        'uploaded_by',
    ];

    /**
     * นำเข้าแบบข้ามแถวที่ซ้ำ — อาศัย UNIQUE (cid, encounter_ref_code)
     * คืนจำนวนแถวที่เพิ่มเข้าไปจริง
     *
     * @param list<array<string, mixed>> $rows
     */
    public function insertIgnoreBatch(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $inserted = 0;

        foreach (array_chunk($rows, 500) as $chunk) {
            $this->db->table($this->table)->ignore(true)->insertBatch($chunk);
            $inserted += max(0, (int) $this->db->affectedRows());
        }

        return $inserted;
    }

    /**
     * ตัวสร้าง query หลักของหน้ารายการ — บังคับขอบเขตหน่วยบริการที่นี่ที่เดียว
     * เพื่อไม่ให้มีทางลืมใส่เงื่อนไขในหน้าใดหน้าหนึ่ง
     *
     * @param array<string, mixed> $filters
     */
    public function scoped(?string $hoscode, array $filters = []): self
    {
        $this->select('encounter_masks.*, check_statuses.name AS status_name, check_statuses.color AS status_color, hospitals.hosname')
            ->join('check_statuses', 'check_statuses.id = encounter_masks.check_status_id', 'left')
            ->join('hospitals', 'hospitals.hoscode = encounter_masks.code', 'left');

        // $hoscode = null หมายถึง admin (เห็นทุกหน่วยบริการ)
        if ($hoscode !== null) {
            $this->where('encounter_masks.code', $hoscode);
        }

        if (! empty($filters['code'])) {
            $this->where('encounter_masks.code', $filters['code']);
        }

        if (! empty($filters['status'])) {
            $this->where('encounter_masks.check_status_id', (int) $filters['status']);
        }

        if (! empty($filters['q'])) {
            $this->groupStart()
                ->like('encounter_masks.cid', $filters['q'])
                ->orLike('encounter_masks.encounter_ref_code', $filters['q'])
                ->orLike('encounter_masks.officer_name', $filters['q'])
                ->groupEnd();
        }

        if (! empty($filters['date_from'])) {
            $this->where('DATE(encounter_masks.create_datetime) >=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $this->where('DATE(encounter_masks.create_datetime) <=', $filters['date_to']);
        }

        return $this;
    }
}
