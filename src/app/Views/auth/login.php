<?= $this->extend('layout/blank') ?>
<?= $this->section('content') ?>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card auth-card">
            <div class="card-body p-4 p-lg-5 text-center">
                <i class="bi bi-shield-check text-primary" style="font-size:3rem"></i>
                <h4 class="mt-3 mb-1 fw-bold">ระบบตรวจสอบข้อร้องเรียน PHR</h4>
                <p class="text-muted mb-4">สำนักงานสาธารณสุขจังหวัดมหาสารคาม</p>

                <?php if ($configured): ?>
                    <a href="<?= site_url('login/line') ?>" class="btn btn-line btn-lg w-100">
                        <i class="bi bi-chat-fill me-2"></i>เข้าสู่ระบบด้วย LINE
                    </a>
                    <p class="text-muted small mt-4 mb-0">
                        ผู้ใช้ใหม่ต้องลงทะเบียนและรอผู้ดูแลระบบอนุมัติก่อนเข้าใช้งาน
                    </p>
                <?php else: ?>
                    <div class="alert alert-warning text-start mb-0">
                        <strong><i class="bi bi-gear me-1"></i>ยังตั้งค่า LINE Login ไม่ครบ</strong>
                        <p class="small mb-0 mt-2">
                            กรอก <code>LINE_CHANNEL_ID</code> และ <code>LINE_CHANNEL_SECRET</code>
                            ในไฟล์ <code>.env</code> ที่ root ของโปรเจกต์ แล้วสั่ง
                            <code>docker compose restart php</code>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
