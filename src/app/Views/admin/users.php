<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1">จัดการผู้ใช้</h4>
        <p class="text-muted small mb-0">
            อนุมัติสิทธิ์เข้าระบบและกำหนดบทบาทผู้ใช้
            <?php if ($pending > 0): ?>
                — <span class="badge bg-warning text-dark">รออนุมัติ <?= $pending ?> คน</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="btn-group btn-group-sm">
        <?php foreach (['' => 'ทั้งหมด', 'pending' => 'รออนุมัติ', 'approved' => 'อนุมัติแล้ว', 'rejected' => 'ระงับสิทธิ์'] as $value => $label): ?>
            <a href="<?= site_url('admin/users') . ($value !== '' ? '?status=' . $value : '') ?>"
               class="btn btn-outline-primary <?= $status === $value ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card"><div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>ผู้ใช้</th>
                    <th>หน่วยบริการ</th>
                    <th>สิทธิ์</th>
                    <th>สถานะ</th>
                    <th>ลงทะเบียนเมื่อ</th>
                    <th class="text-end">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users === []): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">ไม่พบผู้ใช้</td></tr>
                <?php endif; ?>

                <?php foreach ($users as $row): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if (! empty($row['picture_url'])): ?>
                                    <img src="<?= esc($row['picture_url'], 'attr') ?>" width="36" height="36" class="rounded-circle" alt="">
                                <?php else: ?>
                                    <span class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center"
                                          style="width:36px;height:36px"><i class="bi bi-person text-muted"></i></span>
                                <?php endif; ?>
                                <div class="lh-sm">
                                    <div class="fw-semibold">
                                        <?= esc(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: '(ยังไม่กรอกชื่อ)') ?>
                                    </div>
                                    <small class="text-muted">LINE: <?= esc($row['display_name'] ?: '-') ?></small>
                                </div>
                            </div>
                        </td>
                        <td class="small">
                            <?php if (! empty($row['hoscode'])): ?>
                                <code><?= esc($row['hoscode']) ?></code> <?= esc($row['hosname'] ?? '') ?>
                            <?php else: ?>
                                <span class="text-muted">ยังไม่เลือก</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['id'] === (int) $user['id']): ?>
                                <span class="badge bg-warning text-dark">Admin (คุณ)</span>
                            <?php else: ?>
                                <form method="post" action="<?= site_url('admin/users/role/' . $row['id']) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <select name="role" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                                        <option value="user" <?= $row['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="admin" <?= $row['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                                $badges = [
                                    'pending'  => ['warning text-dark', 'รออนุมัติ'],
                                    'approved' => ['success', 'อนุมัติแล้ว'],
                                    'rejected' => ['danger', 'ระงับสิทธิ์'],
                                ];
                                [$class, $label] = $badges[$row['status']] ?? ['secondary', $row['status']];
                            ?>
                            <span class="badge bg-<?= $class ?>"><?= $label ?></span>
                        </td>
                        <td class="small text-nowrap"><?= esc(phr_thai_date($row['created_at'])) ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($row['status'] !== 'approved'): ?>
                                <form method="post" action="<?= site_url('admin/users/approve/' . $row['id']) ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> อนุมัติ</button>
                                </form>
                            <?php endif; ?>

                            <?php if ($row['status'] !== 'rejected' && $row['id'] !== (int) $user['id']): ?>
                                <form method="post" action="<?= site_url('admin/users/reject/' . $row['id']) ?>" class="d-inline"
                                      onsubmit="return confirm('ระงับสิทธิ์ผู้ใช้รายนี้?')">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-slash-circle"></i> ระงับ</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div></div>

<?= $this->endSection() ?>
