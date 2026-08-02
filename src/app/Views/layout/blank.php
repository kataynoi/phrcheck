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
        body {
            font-family:'Sarabun', system-ui, sans-serif;
            background:linear-gradient(135deg,#0d6efd 0%,#0a58ca 100%);
            min-height:100vh; display:flex; align-items:center;
        }
        .auth-card { border:0; border-radius:1rem; box-shadow:0 12px 40px rgba(0,0,0,.18); }
        .btn-line { background:#06C755; border-color:#06C755; color:#fff; font-weight:600; }
        .btn-line:hover { background:#05b34c; border-color:#05b34c; color:#fff; }
    </style>
</head>
<body>
<div class="container py-5">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="row justify-content-center"><div class="col-lg-6">
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i><?= esc(session()->getFlashdata('error')) ?></div>
        </div></div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="row justify-content-center"><div class="col-lg-6">
            <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i><?= esc(session()->getFlashdata('success')) ?></div>
        </div></div>
    <?php endif; ?>

    <?= $this->renderSection('content') ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
