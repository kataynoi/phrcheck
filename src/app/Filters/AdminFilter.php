<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * เฉพาะผู้ใช้ที่มี role = admin
 */
class AdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        if (! $session->get('user_id')) {
            return redirect()->to('/login')->with('error', 'กรุณาเข้าสู่ระบบก่อนใช้งาน');
        }

        if ($session->get('status') !== 'approved' || $session->get('role') !== 'admin') {
            return redirect()->to('/dashboard')->with('error', 'เฉพาะผู้ดูแลระบบเท่านั้น');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
