<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ต้องล็อกอินด้วย LINE, กรอกข้อมูลลงทะเบียนครบ และผ่านการอนุมัติจาก Admin แล้ว
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        if (! $session->get('user_id')) {
            return redirect()->to('/login')->with('error', 'กรุณาเข้าสู่ระบบก่อนใช้งาน');
        }

        // ล็อกอินแล้วแต่ยังไม่ได้กรอกชื่อ/หน่วยบริการ
        if (! $session->get('registered')) {
            return redirect()->to('/register');
        }

        if ($session->get('status') !== 'approved') {
            return redirect()->to('/pending');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
