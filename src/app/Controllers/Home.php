<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        if (! $this->session->get('user_id')) {
            return redirect()->to('/login');
        }

        if (! $this->session->get('registered')) {
            return redirect()->to('/register');
        }

        if ($this->session->get('status') !== 'approved') {
            return redirect()->to('/pending');
        }

        return redirect()->to('/dashboard');
    }
}
