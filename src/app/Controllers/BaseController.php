<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Session\Session;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    protected Session $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        $this->helpers = ['form', 'url', 'text', 'phr'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.
        $this->session = service('session');
    }

    /**
     * ผู้ใช้ที่ล็อกอินอยู่ (ข้อมูลย่อที่เก็บใน session)
     *
     * @return array<string, mixed>
     */
    protected function currentUser(): array
    {
        return [
            'id'      => (int) $this->session->get('user_id'),
            'name'    => (string) $this->session->get('name'),
            'hoscode' => $this->session->get('hoscode'),
            'hosname' => (string) $this->session->get('hosname'),
            'role'    => (string) $this->session->get('role'),
            'picture' => (string) $this->session->get('picture'),
        ];
    }

    protected function isAdmin(): bool
    {
        return $this->session->get('role') === 'admin';
    }

    /**
     * ขอบเขตข้อมูลของผู้ใช้: admin = null (เห็นทุกหน่วยบริการ),
     * ผู้ใช้ทั่วไป = รหัสหน่วยบริการของตัวเอง
     */
    protected function scopeHoscode(): ?string
    {
        return $this->isAdmin() ? null : (string) $this->session->get('hoscode');
    }
}
