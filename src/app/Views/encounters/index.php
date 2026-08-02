<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1">รายการตรวจสอบ</h4>
        <p class="text-muted mb-0 small">
            <?= $isAdmin ? 'ทุกหน่วยบริการ' : esc($user['hosname']) ?>
        </p>
    </div>
    <a href="<?= site_url('encounters/export') . '?' . http_build_query($filters) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-download me-1"></i>ดาวน์โหลด CSV
    </a>
</div>

<!-- ตัวกรอง -->
<div class="card mb-3"><div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1">ค้นหา</label>
            <input type="text" name="q" class="form-control form-control-sm"
                   placeholder="CID / encounter_ref_code / ชื่อเจ้าหน้าที่"
                   value="<?= esc($filters['q']) ?>">
        </div>
        <?php if ($isAdmin): ?>
            <div class="col-md-3">
                <label class="form-label small mb-1">หน่วยบริการ</label>
                <select name="code" class="form-select form-select-sm">
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($hospitals as $hospital): ?>
                        <option value="<?= esc($hospital['hoscode'], 'attr') ?>" <?= $filters['code'] === $hospital['hoscode'] ? 'selected' : '' ?>>
                            <?= esc($hospital['hoscode']) ?> — <?= esc($hospital['hosname']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-2">
            <label class="form-label small mb-1">สถานะ</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">ทั้งหมด</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= $status['id'] ?>" <?= (string) $filters['status'] === (string) $status['id'] ? 'selected' : '' ?>>
                        <?= esc($status['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">วันที่ (create) จาก</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= esc($filters['date_from']) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">ถึง</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= esc($filters['date_to']) ?>">
        </div>
        <div class="col-md-auto d-flex gap-2">
            <button class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>กรอง</button>
            <a href="<?= site_url('encounters') ?>" class="btn btn-outline-secondary btn-sm">ล้าง</a>
        </div>
    </form>
</div></div>

<form method="post" action="<?= site_url('encounters/bulk-status') ?>" id="bulkForm">
    <?= csrf_field() ?>

    <!-- แถบกำหนดสถานะหลายรายการ -->
    <div class="card mb-3 d-none" id="bulkBar"><div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
        <span class="small">เลือกไว้ <strong id="selectedCount">0</strong> รายการ →</span>
        <select name="check_status_id" class="form-select form-select-sm" style="width:auto" required>
            <option value="">— กำหนดสถานะ —</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= $status['id'] ?>"><?= esc($status['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm btn-success"><i class="bi bi-check2-all me-1"></i>บันทึกทั้งหมดที่เลือก</button>
    </div></div>

    <div class="card"><div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:34px"><input type="checkbox" class="form-check-input" id="checkAll"></th>
                        <?php if ($isAdmin): ?><th>หน่วยบริการ</th><?php endif; ?>
                        <th>CID</th>
                        <th>encounter_ref_code</th>
                        <th>เจ้าหน้าที่</th>
                        <th>วันที่สร้าง</th>
                        <th>สถานะการตรวจสอบ</th>
                        <th>วันที่ตรวจสอบ</th>
                        <th class="text-end">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="<?= $isAdmin ? 9 : 8 ?>" class="text-center text-muted py-5">
                                ไม่พบข้อมูลตามเงื่อนไข
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><input type="checkbox" class="form-check-input row-check" name="ids[]" value="<?= $row['id'] ?>"></td>
                            <?php if ($isAdmin): ?>
                                <td class="small">
                                    <code><?= esc($row['code']) ?></code><br>
                                    <span class="text-muted"><?= esc($row['hosname'] ?? '-') ?></span>
                                </td>
                            <?php endif; ?>
                            <td><?= esc($row['cid']) ?></td>
                            <td><code class="ref"><?= esc($row['encounter_ref_code']) ?></code></td>
                            <td class="small"><?= esc($row['officer_name'] ?: '-') ?></td>
                            <td class="small text-nowrap"><?= esc(phr_thai_date($row['create_datetime'])) ?></td>
                            <td>
                                <span class="badge badge-status bg-<?= esc($row['status_color'] ?? 'secondary', 'attr') ?>">
                                    <?= esc($row['status_name'] ?? '-') ?>
                                </span>
                                <?php if (! empty($row['check_note'])): ?>
                                    <i class="bi bi-chat-left-text text-muted ms-1" title="<?= esc($row['check_note'], 'attr') ?>"></i>
                                <?php endif; ?>
                            </td>
                            <td class="small text-nowrap">
                                <?= $row['checked_at'] ? esc(phr_thai_date($row['checked_at'])) : '<span class="text-muted">ยังไม่ตรวจ</span>' ?>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal" data-bs-target="#statusModal"
                                        data-id="<?= $row['id'] ?>"
                                        data-cid="<?= esc($row['cid'], 'attr') ?>"
                                        data-ref="<?= esc($row['encounter_ref_code'], 'attr') ?>"
                                        data-status="<?= (int) $row['check_status_id'] ?>"
                                        data-note="<?= esc($row['check_note'] ?? '', 'attr') ?>">
                                    <i class="bi bi-pencil-square"></i> ตรวจสอบ
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div></div>
</form>

<?php if ($pager !== null): ?>
    <div class="mt-3"><?= $pager->links() ?></div>
<?php endif; ?>

<!-- Modal กำหนดสถานะรายตัว -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="<?= site_url('encounters/update-status') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="modalId">

            <div class="modal-header">
                <h6 class="modal-title fw-bold">กำหนดสถานะการตรวจสอบ</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <dl class="row small mb-3">
                    <dt class="col-4 text-muted">CID</dt>
                    <dd class="col-8" id="modalCid"></dd>
                    <dt class="col-4 text-muted">encounter_ref_code</dt>
                    <dd class="col-8"><code id="modalRef"></code></dd>
                </dl>

                <label class="form-label">สาเหตุ / ผลการตรวจสอบ</label>
                <?php foreach ($statuses as $status): ?>
                    <div class="form-check">
                        <input class="form-check-input modal-status" type="radio" name="check_status_id"
                               value="<?= $status['id'] ?>" id="status<?= $status['id'] ?>" required>
                        <label class="form-check-label" for="status<?= $status['id'] ?>">
                            <span class="badge bg-<?= esc($status['color'], 'attr') ?>"><?= esc($status['name']) ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>

                <label class="form-label mt-3">หมายเหตุ</label>
                <textarea name="check_note" id="modalNote" class="form-control" rows="3"
                          placeholder="ระบุรายละเอียดเพิ่มเติม (ถ้ามี)"></textarea>

                <p class="text-muted small mb-0 mt-2">
                    <i class="bi bi-clock-history me-1"></i>ระบบจะบันทึกวันที่ตรวจสอบเป็นวันเวลาปัจจุบันโดยอัตโนมัติ
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// เติมข้อมูลแถวที่เลือกลงใน modal
document.getElementById('statusModal').addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    document.getElementById('modalId').value = button.dataset.id;
    document.getElementById('modalCid').textContent = button.dataset.cid;
    document.getElementById('modalRef').textContent = button.dataset.ref;
    document.getElementById('modalNote').value = button.dataset.note || '';

    document.querySelectorAll('.modal-status').forEach(function (radio) {
        radio.checked = radio.value === button.dataset.status;
    });
});

// เลือกหลายรายการ
var checkAll = document.getElementById('checkAll');
var rowChecks = Array.prototype.slice.call(document.querySelectorAll('.row-check'));
var bulkBar = document.getElementById('bulkBar');
var selectedCount = document.getElementById('selectedCount');

function refreshBulkBar() {
    var count = rowChecks.filter(function (c) { return c.checked; }).length;
    selectedCount.textContent = count;
    bulkBar.classList.toggle('d-none', count === 0);
}

checkAll.addEventListener('change', function () {
    rowChecks.forEach(function (c) { c.checked = checkAll.checked; });
    refreshBulkBar();
});

rowChecks.forEach(function (c) { c.addEventListener('change', refreshBulkBar); });
</script>
<?= $this->endSection() ?>
