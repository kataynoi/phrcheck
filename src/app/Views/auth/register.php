<?= $this->extend('layout/blank') ?>
<?= $this->section('content') ?>

<div class="row justify-content-center">
    <div class="col-md-9 col-lg-7">
        <div class="card auth-card">
            <div class="card-body p-4 p-lg-5">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <?php if (! empty($user['picture_url'])): ?>
                        <img src="<?= esc($user['picture_url'], 'attr') ?>" width="56" height="56" class="rounded-circle" alt="">
                    <?php endif; ?>
                    <div>
                        <h5 class="mb-0 fw-bold">ลงทะเบียนเข้าใช้งาน</h5>
                        <small class="text-muted">LINE: <?= esc($user['display_name'] ?? '') ?></small>
                    </div>
                </div>

                <?php $errors = session()->getFlashdata('errors'); ?>
                <?php if (! empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?= esc($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= site_url('register') ?>">
                    <?= csrf_field() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">ชื่อ <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control form-control-lg"
                                   value="<?= esc(old('first_name', $user['first_name'] ?? '')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control form-control-lg"
                                   value="<?= esc(old('last_name', $user['last_name'] ?? '')) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">หน่วยบริการ <span class="text-danger">*</span></label>
                            <select name="hoscode" class="form-select form-select-lg" required>
                                <option value="">— เลือกหน่วยบริการ —</option>
                                <?php foreach ($hospitals as $hospital): ?>
                                    <option value="<?= esc($hospital['hoscode'], 'attr') ?>"
                                        <?= old('hoscode', $user['hoscode'] ?? '') === $hospital['hoscode'] ? 'selected' : '' ?>>
                                        <?= esc($hospital['hoscode']) ?> — <?= esc($hospital['hosname']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                หน่วยบริการในจังหวัดมหาสารคาม <?= count($hospitals) ?> แห่ง (ไม่รวมคลินิกเอกชน)
                                — เลือกให้ตรงกับหน่วยงานของท่าน เพราะระบบใช้กำหนดสิทธิ์เห็นข้อมูล
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg flex-grow-1">
                            <i class="bi bi-check2-circle me-1"></i>ลงทะเบียน
                        </button>
                        <a href="<?= site_url('logout') ?>" class="btn btn-outline-secondary btn-lg">ยกเลิก</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
