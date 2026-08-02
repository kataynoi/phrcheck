<?php

namespace App\Models;

use CodeIgniter\Model;

class CheckStatusModel extends Model
{
    /** สถานะเริ่มต้นเมื่อเพิ่งนำเข้า ยังไม่ได้ตรวจสอบ */
    public const PENDING = 1;

    protected $table      = 'check_statuses';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = ['name', 'color', 'sort_order'];

    /**
     * @return list<array<string, mixed>>
     */
    public function ordered(): array
    {
        return $this->orderBy('sort_order', 'ASC')->findAll();
    }
}
