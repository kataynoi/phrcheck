<?php

namespace App\Models;

use App\Libraries\DataScope;
use CodeIgniter\Model;

class UploadBatchModel extends Model
{
    protected $table         = 'upload_batches';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';

    protected $allowedFields = [
        'user_id',
        'filename',
        'total_rows',
        'inserted_rows',
        'skipped_rows',
        'error_rows',
        'note',
    ];

    /**
     * ประวัติการนำเข้าล่าสุดตามขอบเขตของผู้ใช้
     *
     *   admin    -> ของทุกคน
     *   district -> ของผู้ใช้ที่สังกัดหน่วยบริการในอำเภอเดียวกัน
     *   user     -> เฉพาะที่ตัวเองนำเข้า
     *
     * @return list<array<string, mixed>>
     */
    public function recentInScope(DataScope $scope, int $userId, int $limit = 10): array
    {
        $builder = $this->select('upload_batches.*, users.first_name, users.last_name, hospitals.hosname')
            ->join('users', 'users.id = upload_batches.user_id', 'left')
            ->join('hospitals', 'hospitals.hoscode = users.hoscode', 'left');

        if ($scope->isDistrict()) {
            $condition = $scope->sqlCondition('users.hoscode');

            if ($condition !== '') {
                $builder->where($condition, null, false);
            }
        } elseif (! $scope->isAll()) {
            $builder->where('upload_batches.user_id', $userId);
        }

        return $builder->orderBy('upload_batches.id', 'DESC')->findAll($limit);
    }
}
