<?php

namespace App\Models;

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
     * ประวัติการนำเข้าล่าสุด
     *
     * @return list<array<string, mixed>>
     */
    public function recent(?int $userId, int $limit = 10): array
    {
        $builder = $this->select('upload_batches.*, users.first_name, users.last_name')
            ->join('users', 'users.id = upload_batches.user_id', 'left');

        if ($userId !== null) {
            $builder->where('upload_batches.user_id', $userId);
        }

        return $builder->orderBy('upload_batches.id', 'DESC')->findAll($limit);
    }
}
