<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>

<h4 class="fw-bold mb-1">นำเข้าข้อมูล</h4>
<p class="text-muted small mb-3">
    นำเข้าได้เฉพาะข้อมูลของ <?= esc($scopeLabel) ?>
</p>

<?php $result = session()->getFlashdata('import_result'); ?>
<?php if ($result): ?>
    <div class="card mb-3 border-start border-4 border-success"><div class="card-body">
        <h6 class="fw-bold mb-3">
            <i class="bi bi-check-circle text-success me-1"></i>
            ผลการนำเข้า: <?= esc($result['filename']) ?>
        </h6>
        <div class="row g-3 text-center">
            <div class="col-6 col-md-3">
                <div class="border rounded py-2">
                    <div class="h5 mb-0"><?= number_format($result['total']) ?></div>
                    <small class="text-muted">แถวทั้งหมด</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded py-2 bg-success-subtle">
                    <div class="h5 mb-0 text-success"><?= number_format($result['inserted']) ?></div>
                    <small class="text-muted">นำเข้าสำเร็จ</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded py-2 bg-warning-subtle">
                    <div class="h5 mb-0 text-warning-emphasis">
                        <?= number_format($result['duplicate_in_db'] + $result['duplicate_in_file']) ?>
                    </div>
                    <small class="text-muted">ซ้ำ (ข้าม)</small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="border rounded py-2 <?= $result['error_count'] > 0 ? 'bg-danger-subtle' : '' ?>">
                    <div class="h5 mb-0 <?= $result['error_count'] > 0 ? 'text-danger' : '' ?>">
                        <?= number_format($result['error_count']) ?>
                    </div>
                    <small class="text-muted">ผิดพลาด</small>
                </div>
            </div>
        </div>

        <p class="small text-muted mt-3 mb-0">
            ซ้ำในฐานข้อมูลเดิม <?= number_format($result['duplicate_in_db']) ?> แถว,
            ซ้ำภายในไฟล์เดียวกัน <?= number_format($result['duplicate_in_file']) ?> แถว
            (ตรวจซ้ำด้วย <code>cid</code> + <code>encounter_ref_code</code>)
        </p>

        <?php if (! empty($result['errors'])): ?>
            <details class="mt-3">
                <summary class="small text-danger">ดูรายละเอียดข้อผิดพลาด (<?= count($result['errors']) ?> รายการแรก)</summary>
                <ul class="small text-muted mt-2 mb-0 ps-3">
                    <?php foreach ($result['errors'] as $error): ?>
                        <li><?= esc($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </details>
        <?php endif; ?>
    </div></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <h6 class="fw-bold mb-3">อัปโหลดไฟล์</h6>
            <form method="post" action="<?= site_url('upload') ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="file" name="import_file" class="form-control mb-2"
                       accept=".xlsx,.xls,.csv" required>
                <p class="small text-muted mb-3">
                    รับไฟล์ <strong>.xlsx</strong> ที่ export มาจากระบบ PHR ได้เลย
                    (รองรับ .xls และ .csv ด้วย)
                </p>
                <button class="btn btn-primary w-100">
                    <i class="bi bi-upload me-1"></i>นำเข้าข้อมูล
                </button>
            </form>

            <hr class="my-3">

            <h6 class="fw-bold small">รูปแบบไฟล์ที่ต้องการ</h6>
            <p class="small text-muted mb-2">
                หัวตารางอยู่แถวแรกของชีตแรก (ลำดับสลับได้ ตัวพิมพ์เล็ก/ใหญ่ไม่สำคัญ):
            </p>
            <pre class="small bg-light p-2 rounded mb-2" style="font-size:.75rem">create_datetime
cid                  <span class="text-danger">*จำเป็น</span>
encounter_ref_code   <span class="text-danger">*จำเป็น</span>
process_note
officer_name
process_datetime
phr_encounter_mask_id
update_datetime</pre>
            <ul class="small text-muted ps-3 mb-0">
                <li>ระบบเพิ่มคอลัมน์ <code>code</code> ให้อัตโนมัติ จาก 5 หลักแรกของ <code>encounter_ref_code</code>
                    (เช่น <code>11055:690615062028</code> → <code>11055</code>)</li>
                <li>แถวที่มี <code>cid</code> + <code>encounter_ref_code</code> ซ้ำกับที่เคยนำเข้าจะถูกข้าม</li>
                <li>วันที่ในไฟล์ Excel อ่านได้เลย ไม่ต้องจัดรูปแบบก่อน</li>
            </ul>
        </div></div>
    </div>

    <div class="col-lg-7">
        <div class="card"><div class="card-body">
            <h6 class="fw-bold mb-3">ประวัติการนำเข้า<?= $showUploader ? ' (ทุกคนในขอบเขตของคุณ)' : '' ?></h6>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th>ไฟล์</th>
                            <?php if ($showUploader): ?><th>ผู้นำเข้า</th><?php endif; ?>
                            <th class="text-end">ทั้งหมด</th>
                            <th class="text-end">สำเร็จ</th>
                            <th class="text-end">ซ้ำ</th>
                            <th class="text-end">ผิดพลาด</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($batches === []): ?>
                            <tr><td colspan="<?= $showUploader ? 7 : 6 ?>" class="text-center text-muted py-4">ยังไม่มีประวัติ</td></tr>
                        <?php endif; ?>
                        <?php foreach ($batches as $batch): ?>
                            <tr>
                                <td class="small text-nowrap"><?= esc(phr_thai_date($batch['created_at'])) ?></td>
                                <td class="small text-truncate" style="max-width:180px" title="<?= esc($batch['filename'], 'attr') ?>">
                                    <?= esc($batch['filename']) ?>
                                </td>
                                <?php if ($showUploader): ?>
                                    <td class="small">
                                        <?= esc(trim(($batch['first_name'] ?? '') . ' ' . ($batch['last_name'] ?? ''))) ?>
                                        <div class="text-muted"><?= esc($batch['hosname'] ?? '') ?></div>
                                    </td>
                                <?php endif; ?>
                                <td class="text-end"><?= number_format((int) $batch['total_rows']) ?></td>
                                <td class="text-end text-success fw-semibold"><?= number_format((int) $batch['inserted_rows']) ?></td>
                                <td class="text-end text-warning-emphasis"><?= number_format((int) $batch['skipped_rows']) ?></td>
                                <td class="text-end <?= (int) $batch['error_rows'] > 0 ? 'text-danger' : 'text-muted' ?>">
                                    <?= number_format((int) $batch['error_rows']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>

<?= $this->endSection() ?>
