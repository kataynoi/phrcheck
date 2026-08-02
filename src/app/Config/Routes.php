<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', 'Home::index');

// ---------- LINE Login ----------
$routes->get('login', 'Login::index');
$routes->get('login/line', 'Login::line');
$routes->get('login/callback', 'Login::callback');
$routes->get('logout', 'Login::logout');

// ---------- ลงทะเบียน / รออนุมัติ ----------
$routes->get('register', 'Register::index');
$routes->post('register', 'Register::save');
$routes->get('pending', 'Register::pending');

// ---------- ส่วนที่ต้องล็อกอินและผ่านการอนุมัติแล้ว ----------
$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('dashboard', 'Dashboard::index');
    $routes->get('dashboard/data', 'Dashboard::data');

    $routes->get('encounters', 'Encounters::index');
    $routes->post('encounters/update-status', 'Encounters::updateStatus');
    $routes->post('encounters/bulk-status', 'Encounters::bulkStatus');
    $routes->get('encounters/export', 'Encounters::export');

    $routes->get('upload', 'Upload::index');
    $routes->post('upload', 'Upload::process');
});

// ---------- เฉพาะ Admin ----------
$routes->group('admin', ['filter' => 'admin'], static function (RouteCollection $routes): void {
    $routes->get('users', 'Admin\Users::index');
    $routes->post('users/approve/(:num)', 'Admin\Users::approve/$1');
    $routes->post('users/reject/(:num)', 'Admin\Users::reject/$1');
    $routes->post('users/role/(:num)', 'Admin\Users::setRole/$1');
});
