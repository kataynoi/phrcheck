<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Libraries\DataScope;
use App\Models\HospitalModel;
use App\Models\UserModel;

class Users extends BaseController
{
    /** สิทธิ์ที่ตั้งให้ผู้ใช้ได้ พร้อมชื่อที่แสดงในหน้าจอ */
    public const ROLES = [
        DataScope::ROLE_ADMIN    => 'Admin จังหวัด',
        DataScope::ROLE_DISTRICT => 'Admin อำเภอ',
        DataScope::ROLE_USER     => 'User',
    ];

    public function index()
    {
        $status = (string) $this->request->getGet('status');
        $model  = new UserModel();

        return view('admin/users', [
            'users'   => $model->listWithHospital($status),
            'status'  => $status,
            'pending' => $model->countPending(),
            'roles'   => self::ROLES,
            'user'    => $this->currentUser(),
            'isAdmin' => true,
        ]);
    }

    public function approve(int $id)
    {
        $model = new UserModel();
        $row   = $model->find($id);

        if ($row === null) {
            return redirect()->back()->with('error', 'ไม่พบผู้ใช้');
        }

        if (empty($row['first_name']) || empty($row['hoscode'])) {
            return redirect()->back()->with('error', 'ผู้ใช้รายนี้ยังกรอกข้อมูลลงทะเบียนไม่ครบ');
        }

        $model->update($id, [
            'status'      => 'approved',
            'approved_at' => phr_now(),
            'approved_by' => (int) $this->session->get('user_id'),
        ]);

        return redirect()->back()->with('success', 'อนุมัติ ' . $row['first_name'] . ' ' . $row['last_name'] . ' แล้ว');
    }

    public function reject(int $id)
    {
        $model = new UserModel();
        $row   = $model->find($id);

        if ($row === null) {
            return redirect()->back()->with('error', 'ไม่พบผู้ใช้');
        }

        if ($id === (int) $this->session->get('user_id')) {
            return redirect()->back()->with('error', 'ระงับสิทธิ์ตัวเองไม่ได้');
        }

        $model->update($id, [
            'status'      => 'rejected',
            'approved_at' => null,
            'approved_by' => (int) $this->session->get('user_id'),
        ]);

        return redirect()->back()->with('success', 'ระงับสิทธิ์ผู้ใช้แล้ว');
    }

    public function setRole(int $id)
    {
        $role = (string) $this->request->getPost('role');

        if (! array_key_exists($role, self::ROLES)) {
            return redirect()->back()->with('error', 'สิทธิ์ไม่ถูกต้อง');
        }

        if ($id === (int) $this->session->get('user_id')) {
            return redirect()->back()->with('error', 'เปลี่ยนสิทธิ์ของตัวเองไม่ได้');
        }

        $model = new UserModel();
        $row   = $model->find($id);

        if ($row === null) {
            return redirect()->back()->with('error', 'ไม่พบผู้ใช้');
        }

        // ขอบเขตของ Admin อำเภอมาจากอำเภอของหน่วยบริการที่สังกัด
        // ถ้ายังไม่ได้เลือกหน่วยบริการ จะกลายเป็นคนที่ไม่เห็นข้อมูลอะไรเลย
        if ($role === DataScope::ROLE_DISTRICT
            && (new HospitalModel())->districtOf($row['hoscode']) === null) {
            return redirect()->back()->with(
                'error',
                'ตั้งเป็น Admin อำเภอไม่ได้ เพราะผู้ใช้รายนี้ยังไม่ได้เลือกหน่วยบริการ (ระบบใช้อำเภอของหน่วยบริการเป็นขอบเขต)'
            );
        }

        $model->update($id, ['role' => $role]);

        return redirect()->back()->with('success', 'เปลี่ยนสิทธิ์เป็น ' . self::ROLES[$role] . ' แล้ว');
    }
}
