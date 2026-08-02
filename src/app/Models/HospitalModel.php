<?php

namespace App\Models;

use CodeIgniter\Model;

class HospitalModel extends Model
{
    /**
     * hostype ที่ไม่ให้เลือกตอนลงทะเบียน
     *   16 = คลินิกเอกชน (206 จาก 420 แห่งในจังหวัด) ไม่ใช่หน่วยบริการที่ใช้ระบบนี้
     */
    public const NON_SELECTABLE_HOSTYPES = ['16'];

    protected $table      = 'hospitals';
    protected $primaryKey = 'hoscode';
    protected $returnType = 'array';

    protected $allowedFields = ['hoscode', 'hosname', 'hostype', 'distcode', 'provcode', 'ampurname'];

    /**
     * หน่วยบริการทั้งหมดในจังหวัด เรียงตามชื่อ
     * ใช้กับตัวกรองของ Admin ที่ต้องเห็นได้ครบทุกแห่ง
     *
     * @return list<array<string, mixed>>
     */
    public function options(): array
    {
        return $this->orderBy('hosname', 'ASC')->findAll();
    }

    /**
     * หน่วยบริการที่ให้ผู้ใช้เลือกได้ตอนลงทะเบียน (ตัดคลินิกเอกชนออก)
     *
     * @return list<array<string, mixed>>
     */
    public function selectableOptions(): array
    {
        return $this->whereNotIn('hostype', self::NON_SELECTABLE_HOSTYPES)
            ->orderBy('hosname', 'ASC')
            ->findAll();
    }

    public function nameOf(?string $hoscode): string
    {
        if ($hoscode === null || $hoscode === '') {
            return '-';
        }

        $row = $this->find($hoscode);

        return $row['hosname'] ?? $hoscode;
    }
}
