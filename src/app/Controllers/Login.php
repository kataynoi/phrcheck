<?php

namespace App\Controllers;

use App\Libraries\LineLogin;
use App\Models\HospitalModel;
use App\Models\UserModel;
use Throwable;

class Login extends BaseController
{
    public function index()
    {
        if ($this->session->get('user_id')) {
            return redirect()->to('/');
        }

        return view('auth/login', [
            'configured' => (new LineLogin())->isConfigured(),
        ]);
    }

    /**
     * ส่งผู้ใช้ไปหน้ายินยอมของ LINE
     */
    public function line()
    {
        $line = new LineLogin();

        if (! $line->isConfigured()) {
            return redirect()->to('/login')
                ->with('error', 'ยังไม่ได้ตั้งค่า LINE_CHANNEL_ID / LINE_CHANNEL_SECRET ในไฟล์ .env');
        }

        // state กัน CSRF ของ OAuth — ต้องตรงกันตอน callback
        $state = bin2hex(random_bytes(16));
        $nonce = bin2hex(random_bytes(16));
        $this->session->set('line_state', $state);

        return redirect()->to($line->authorizeUrl($state, $nonce));
    }

    /**
     * LINE เรียกกลับมาพร้อม authorization code
     */
    public function callback()
    {
        $code      = (string) $this->request->getGet('code');
        $state     = (string) $this->request->getGet('state');
        $lineError = (string) $this->request->getGet('error');

        if ($lineError !== '') {
            $description = (string) $this->request->getGet('error_description');

            return redirect()->to('/login')
                ->with('error', 'ยกเลิกการเข้าสู่ระบบ: ' . ($description !== '' ? $description : $lineError));
        }

        $expected = (string) $this->session->get('line_state');
        $this->session->remove('line_state');

        if ($code === '' || $state === '' || ! hash_equals($expected, $state)) {
            return redirect()->to('/login')
                ->with('error', 'การเข้าสู่ระบบไม่ถูกต้องหรือหมดอายุ กรุณาลองใหม่');
        }

        $line = new LineLogin();

        try {
            $token   = $line->exchangeToken($code);
            $profile = $line->profile((string) ($token['access_token'] ?? ''));
        } catch (Throwable $e) {
            log_message('error', 'LINE login ล้มเหลว: {msg}', ['msg' => $e->getMessage()]);

            return redirect()->to('/login')->with('error', $e->getMessage());
        }

        $lineUserId = (string) ($profile['userId'] ?? '');

        if ($lineUserId === '') {
            return redirect()->to('/login')->with('error', 'ไม่พบ userId จาก LINE');
        }

        $users = new UserModel();
        $user  = $users->findByLineId($lineUserId);

        $isBootstrapAdmin = $lineUserId === (string) getenv('ADMIN_LINE_USER_ID');

        if ($user === null) {
            $userId = $users->insert([
                'line_user_id' => $lineUserId,
                'display_name' => (string) ($profile['displayName'] ?? ''),
                'picture_url'  => (string) ($profile['pictureUrl'] ?? ''),
                // Admin คนแรกตาม .env ไม่ต้องรอใครอนุมัติ ไม่งั้นจะไม่มีใครอนุมัติให้ได้เลย
                'role'         => $isBootstrapAdmin ? 'admin' : 'user',
                'status'       => $isBootstrapAdmin ? 'approved' : 'pending',
                'approved_at'  => $isBootstrapAdmin ? phr_now() : null,
            ], true);

            $user = $users->find($userId);
        } else {
            $update = [
                'display_name'  => (string) ($profile['displayName'] ?? ''),
                'picture_url'   => (string) ($profile['pictureUrl'] ?? ''),
                'last_login_at' => phr_now(),
            ];

            if ($isBootstrapAdmin && ($user['role'] !== 'admin' || $user['status'] !== 'approved')) {
                $update['role']        = 'admin';
                $update['status']      = 'approved';
                $update['approved_at'] = phr_now();
            }

            $users->update($user['id'], $update);
            $user = $users->find($user['id']);
        }

        if ($user === null) {
            return redirect()->to('/login')->with('error', 'สร้างบัญชีผู้ใช้ไม่สำเร็จ');
        }

        $this->writeSession($user);

        if (! $this->session->get('registered')) {
            return redirect()->to('/register');
        }

        if ($user['status'] !== 'approved') {
            return redirect()->to('/pending');
        }

        return redirect()->to('/dashboard');
    }

    public function logout()
    {
        $this->session->destroy();

        return redirect()->to('/login')->with('success', 'ออกจากระบบแล้ว');
    }

    /**
     * @param array<string, mixed> $user
     */
    private function writeSession(array $user): void
    {
        // ถือว่าลงทะเบียนแล้วเมื่อกรอกชื่อและเลือกหน่วยบริการครบ
        $registered = ! empty($user['first_name']) && ! empty($user['hoscode']);

        $hospitals = new HospitalModel();
        $distcode  = $hospitals->districtOf($user['hoscode']);

        $this->session->set([
            'user_id' => (int) $user['id'],
            'line_id' => $user['line_user_id'],
            'name'    => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['display_name'] ?? ''),
            'picture' => $user['picture_url'] ?? '',
            'hoscode' => $user['hoscode'],
            'hosname' => $hospitals->nameOf($user['hoscode']),
            // เก็บอำเภอไว้ด้วย เพราะ Admin ระดับอำเภอใช้ค่านี้กำหนดขอบเขตข้อมูล
            'distcode'   => $distcode,
            'ampurname'  => $hospitals->districtName($distcode),
            'role'       => $user['role'],
            'status'     => $user['status'],
            'registered' => $registered,
        ]);
    }
}
