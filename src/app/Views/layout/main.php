<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'ระบบตรวจสอบข้อร้องเรียน PHR') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', system-ui, sans-serif; background:#f5f7fb; }
        .navbar-brand { font-weight:700; }
        .card { border:0; box-shadow:0 1px 3px rgba(16,24,40,.08); border-radius:.75rem; }
        .stat-card .value { font-size:1.75rem; font-weight:700; line-height:1.2; }
        .stat-card .label { font-size:.85rem; color:#667085; }
        .table thead th { font-size:.82rem; white-space:nowrap; background:#f8f9fb; }
        .table td { font-size:.88rem; vertical-align:middle; }
        .table-sticky thead th { position:sticky; top:0; z-index:2; }
        /* แถวรวมค้างอยู่ท้ายตารางเสมอ แม้ตารางจะเลื่อนอยู่ */
        .table-sticky tfoot td {
            position:sticky; bottom:0; z-index:2;
            background:#eef2f7; font-weight:700;
            border-top:2px solid #b6c2d2;
        }
        .badge-status { font-weight:500; }
        code.ref { font-size:.85rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container-fluid px-lg-4">
        <a class="navbar-brand" href="<?= site_url('dashboard') ?>">
            <i class="bi bi-shield-check me-1"></i> PHR Check
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link<?= url_is('dashboard*') ? ' active fw-semibold' : '' ?>" href="<?= site_url('dashboard') ?>">
                        <i class="bi bi-bar-chart-line me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= url_is('encounters*') ? ' active fw-semibold' : '' ?>" href="<?= site_url('encounters') ?>">
                        <i class="bi bi-list-check me-1"></i>รายการตรวจสอบ
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= url_is('upload*') ? ' active fw-semibold' : '' ?>" href="<?= site_url('upload') ?>">
                        <i class="bi bi-upload me-1"></i>นำเข้าข้อมูล
                    </a>
                </li>
                <?php if (! empty($isAdmin)): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= url_is('admin/users*') ? ' active fw-semibold' : '' ?>" href="<?= site_url('admin/users') ?>">
                            <i class="bi bi-people me-1"></i>จัดการผู้ใช้
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center text-white gap-3">
                <div class="text-end small lh-sm d-none d-lg-block">
                    <div class="fw-semibold"><?= esc($user['name'] ?? '') ?>
                        <?php if (($user['role'] ?? '') === 'admin'): ?>
                            <span class="badge bg-warning text-dark ms-1">Admin จังหวัด</span>
                        <?php elseif (($user['role'] ?? '') === 'district'): ?>
                            <span class="badge bg-info text-dark ms-1">Admin อำเภอ</span>
                        <?php endif; ?>
                    </div>
                    <div class="opacity-75">
                        <?= esc($user['hosname'] ?? '') ?>
                        <?php if (($user['role'] ?? '') === 'district' && ! empty($user['ampurname'])): ?>
                            · อ.<?= esc($user['ampurname']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (! empty($user['picture'])): ?>
                    <img src="<?= esc($user['picture'], 'attr') ?>" alt="" width="36" height="36" class="rounded-circle border border-2 border-light">
                <?php endif; ?>
                <a href="<?= site_url('logout') ?>" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="container-fluid px-lg-4 pb-5">

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-1"></i><?= esc(session()->getFlashdata('success')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-1"></i><?= esc(session()->getFlashdata('error')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?= $this->renderSection('content') ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
