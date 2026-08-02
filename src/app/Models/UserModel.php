<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table         = 'users';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'line_user_id',
        'display_name',
        'picture_url',
        'first_name',
        'last_name',
        'hoscode',
        'role',
        'status',
        'approved_at',
        'approved_by',
        'last_login_at',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function findByLineId(string $lineUserId): ?array
    {
        return $this->where('line_user_id', $lineUserId)->first();
    }

    /**
     * รายชื่อผู้ใช้พร้อมชื่อหน่วยบริการ
     *
     * @return list<array<string, mixed>>
     */
    public function listWithHospital(?string $status = null): array
    {
        $builder = $this->select('users.*, hospitals.hosname')
            ->join('hospitals', 'hospitals.hoscode = users.hoscode', 'left');

        if ($status !== null && $status !== '') {
            $builder->where('users.status', $status);
        }

        return $builder->orderBy('FIELD(users.status, "pending", "approved", "rejected")', '', false)
            ->orderBy('users.created_at', 'DESC')
            ->findAll();
    }

    public function countPending(): int
    {
        return $this->where('status', 'pending')->countAllResults();
    }
}
