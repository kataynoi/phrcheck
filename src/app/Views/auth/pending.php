<?= $this->extend('layout/blank') ?>
<?= $this->section('content') ?>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card auth-card">
            <div class="card-body p-4 p-lg-5 text-center">
                <?php if (($user['status'] ?? '') === 'rejected'): ?>
                    <i class="bi bi-x-octagon text-danger" style="font-size:3rem"></i>
                    <h5 class="mt-3 fw-bold">บัญชีถูกระงับสิทธิ์</h5>
                    <p class="text-muted">กรุณาติดต่อผู้ดูแลระบบ</p>
                <?php else: ?>
                    <i class="bi bi-hourglass-split text-warning" style="font-size:3rem"></i>
                    <h5 class="mt-3 fw-bold">รอผู้ดูแลระบบอนุมัติ</h5>
                    <p class="text-muted mb-4">
                        ลงทะเบียนเรียบร้อยแล้ว ระบบจะเปิดให้ใช้งานหลังผู้ดูแลระบบอนุมัติสิทธิ์
                    </p>
                <?php endif; ?>

                <ul class="list-group list-group-flush text-start small mb-4">
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">ชื่อ-นามสกุล</span>
                        <strong><?= esc(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?></strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">หน่วยบริการ</span>
                        <strong><?= esc(session()->get('hosname') ?: '-') ?></strong>
                    </li>
                </ul>

                <div class="d-flex gap-2">
                    <a href="<?= site_url('pending') ?>" class="btn btn-outline-primary flex-grow-1">
                        <i class="bi bi-arrow-clockwise me-1"></i>ตรวจสอบสถานะอีกครั้ง
                    </a>
                    <a href="<?= site_url('logout') ?>" class="btn btn-outline-secondary">ออกจากระบบ</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
