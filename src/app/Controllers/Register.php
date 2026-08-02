<?php

namespace App\Controllers;

use App\Models\HospitalModel;
use App\Models\UserModel;

class Register extends BaseController
{
    public function index()
    {
        if (! $this->session->get('user_id')) {
            return redirect()->to('/login');
        }

        // ลงทะเบียนไปแล้วไม่ต้องกรอกซ้ำ
        if ($this->session->get('registered')) {
            return redirect()->to('/');
        }

        $users = new UserModel();
        $user  = $users->find((int) $this->session->get('user_id'));

        return view('auth/register', [
            'user'      => $user,
            'hospitals' => (new HospitalModel())->selectableOptions(),
        ]);
    }

    public function save()
    {
        if (! $this->session->get('user_id')) {
            return redirect()->to('/login');
        }

        $rules = [
            'first_name' => 'required|min_length[2]|max_length[100]',
            'last_name'  => 'required|min_length[2]|max_length[100]',
            'hoscode'    => 'required|is_not_unique[hospitals.hoscode]',
        ];

        $messages = [
            'first_name' => [
                'required'   => 'กรุณากรอกชื่อ',
                'min_length' => 'ชื่อสั้นเกินไป',
            ],
            'last_name' => [
                'required'   => 'กรุณากรอกนามสกุล',
                'min_length' => 'นามสกุลสั้นเกินไป',
            ],
            'hoscode' => [
                'required'      => 'กรุณาเลือกหน่วยบริการ',
                'is_not_unique' => 'ไม่พบหน่วยบริการที่เลือกในจังหวัดมหาสารคาม',
            ],
        ];

        if (! $this->validate($rules, $messages)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $hoscode  = (string) $this->request->getPost('hoscode');
        $hospital = (new HospitalModel())->find($hoscode);

        // dropdown ซ่อนคลินิกเอกชนไว้แล้ว แต่ POST ตรง ๆ ยังส่งมาได้ จึงต้องกันซ้ำที่นี่
        if ($hospital === null || in_array($hospital['hostype'], HospitalModel::NON_SELECTABLE_HOSTYPES, true)) {
            return redirect()->back()->withInput()
                ->with('errors', ['hoscode' => 'หน่วยบริการที่เลือกไม่สามารถลงทะเบียนใช้งานระบบนี้ได้']);
        }

        $userId = (int) $this->session->get('user_id');
        $users  = new UserModel();

        $users->update($userId, [
            'first_name' => trim((string) $this->request->getPost('first_name')),
            'last_name'  => trim((string) $this->request->getPost('last_name')),
            'hoscode'    => $hoscode,
        ]);

        $user = $users->find($userId);

        $this->session->set([
            'name'       => trim($user['first_name'] . ' ' . $user['last_name']),
            'hoscode'    => $user['hoscode'],
            'hosname'    => (new HospitalModel())->nameOf($user['hoscode']),
            'status'     => $user['status'],
            'registered' => true,
        ]);

        if ($user['status'] === 'approved') {
            return redirect()->to('/dashboard')->with('success', 'ลงทะเบียนเรียบร้อย');
        }

        return redirect()->to('/pending')->with('success', 'ลงทะเบียนเรียบร้อย รอผู้ดูแลระบบอนุมัติ');
    }

    /**
     * หน้ารอการอนุมัติ
     */
    public function pending()
    {
        if (! $this->session->get('user_id')) {
            return redirect()->to('/login');
        }

        if (! $this->session->get('registered')) {
            return redirect()->to('/register');
        }

        // เผื่อ Admin เพิ่งกดอนุมัติระหว่างที่ผู้ใช้ค้างอยู่หน้านี้
        $user = (new UserModel())->find((int) $this->session->get('user_id'));

        if ($user !== null && $user['status'] !== $this->session->get('status')) {
            $this->session->set('status', $user['status']);
            $this->session->set('role', $user['role']);
        }

        if ($this->session->get('status') === 'approved') {
            return redirect()->to('/dashboard')->with('success', 'บัญชีของคุณได้รับการอนุมัติแล้ว');
        }

        return view('auth/pending', ['user' => $user]);
    }
}
