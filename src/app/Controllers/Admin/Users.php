<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\UserModel;

class Users extends BaseController
{
    public function index()
    {
        $status = (string) $this->request->getGet('status');
        $model  = new UserModel();

        return view('admin/users', [
            'users'   => $model->listWithHospital($status),
            'status'  => $status,
            'pending' => $model->countPending(),
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

        if (! in_array($role, ['admin', 'user'], true)) {
            return redirect()->back()->with('error', 'สิทธิ์ไม่ถูกต้อง');
        }

        if ($id === (int) $this->session->get('user_id')) {
            return redirect()->back()->with('error', 'เปลี่ยนสิทธิ์ของตัวเองไม่ได้');
        }

        $model = new UserModel();

        if ($model->find($id) === null) {
            return redirect()->back()->with('error', 'ไม่พบผู้ใช้');
        }

        $model->update($id, ['role' => $role]);

        return redirect()->back()->with('success', 'เปลี่ยนสิทธิ์เป็น ' . $role . ' แล้ว');
    }
}
