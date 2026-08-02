<?= $this->extend('layout/main') ?>
<?= $this->section('content') ?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h4 class="fw-bold mb-1">Dashboard สรุปผลการตรวจสอบ</h4>
        <p class="text-muted mb-0 small">
            <?= $isAdmin ? 'ทุกหน่วยบริการในจังหวัดมหาสารคาม' : esc($user['hosname']) ?>
        </p>
    </div>
    <form method="get" class="d-flex align-items-center gap-2">
        <label class="text-muted small mb-0">ช่วงเวลา</label>
        <select name="days" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <?php foreach ([7 => '7 วัน', 30 => '30 วัน', 90 => '90 วัน', 365 => '1 ปี'] as $value => $label): ?>
                <option value="<?= $value ?>" <?= $days === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<!-- การ์ดสรุป -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="label">จำนวน Record ร้องเรียน</div>
            <div class="value text-primary"><?= number_format($summary['records']) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="label">จำนวนคน (CID ไม่ซ้ำ)</div>
            <div class="value text-info"><?= number_format($summary['persons']) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="label">ตรวจสอบแล้ว</div>
            <div class="value text-success"><?= number_format($summary['checked']) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="label">ยังไม่ตรวจสอบ</div>
            <div class="value text-secondary"><?= number_format($summary['unchecked']) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="card stat-card h-100"><div class="card-body">
            <div class="label">ตรวจสอบวันนี้</div>
            <div class="value text-warning"><?= number_format($summary['checked_today']) ?></div>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- กราฟรายวัน -->
    <div class="col-lg-8">
        <div class="card h-100"><div class="card-body">
            <h6 class="fw-bold mb-3">สถานะการตรวจสอบรายวัน (ตามวันที่ตรวจสอบ)</h6>
            <div style="height:320px"><canvas id="dailyChart"></canvas></div>
            <p class="text-muted small mb-0 mt-2" id="dailyEmpty" hidden>
                ยังไม่มีรายการที่ถูกตรวจสอบในช่วงเวลานี้
            </p>
        </div></div>
    </div>

    <!-- สัดส่วนตามสาเหตุ -->
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h6 class="fw-bold mb-3">แยกตามสาเหตุ</h6>
            <?php foreach ($statuses as $status): ?>
                <?php
                    $count   = $byStatus[$status['id']] ?? 0;
                    $percent = $summary['records'] > 0 ? round($count * 100 / $summary['records'], 1) : 0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= esc($status['name']) ?></span>
                        <span class="fw-semibold"><?= number_format($count) ?> <span class="text-muted">(<?= $percent ?>%)</span></span>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar bg-<?= esc($status['color'], 'attr') ?>" style="width:<?= $percent ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div></div>
    </div>
</div>

<!-- ตารางรายหน่วยบริการ -->
<div class="card">
    <div class="card-body">
        <h6 class="fw-bold mb-3">สรุปรายหน่วยบริการ</h6>
        <div class="table-responsive" style="max-height:520px">
            <table class="table table-hover table-sm table-sticky mb-0">
                <thead>
                    <tr>
                        <th>รหัส</th>
                        <th>หน่วยบริการ</th>
                        <th>อำเภอ</th>
                        <th class="text-end">Record</th>
                        <th class="text-end">คน</th>
                        <?php foreach ($statuses as $status): ?>
                            <th class="text-end"><?= esc($status['name']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($byHospital === []): ?>
                        <tr>
                            <td colspan="<?= 5 + count($statuses) ?>" class="text-center text-muted py-4">
                                ยังไม่มีข้อมูล — เริ่มที่เมนู <a href="<?= site_url('upload') ?>">นำเข้าข้อมูล</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($byHospital as $row): ?>
                        <tr>
                            <td><code><?= esc($row['code']) ?></code></td>
                            <td><?= esc($row['hosname']) ?></td>
                            <td class="text-muted"><?= esc($row['ampurname'] ?? '-') ?></td>
                            <td class="text-end fw-semibold"><?= number_format((int) $row['records']) ?></td>
                            <td class="text-end"><?= number_format((int) $row['persons']) ?></td>
                            <?php foreach ($statuses as $status): ?>
                                <?php $value = (int) ($row['s' . $status['id']] ?? 0); ?>
                                <td class="text-end <?= $value === 0 ? 'text-black-50' : '' ?>">
                                    <?= $value === 0 ? '-' : number_format($value) ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
fetch('<?= site_url('dashboard/data') ?>?days=<?= $days ?>')
    .then(function (response) { return response.json(); })
    .then(function (payload) {
        if (!payload.datasets.length) {
            document.getElementById('dailyEmpty').hidden = false;
            return;
        }

        new Chart(document.getElementById('dailyChart'), {
            type: 'bar',
            data: { labels: payload.labels, datasets: payload.datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { stacked: true, ticks: { maxRotation: 0, autoSkipPadding: 16 } },
                    y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
                },
                plugins: { legend: { position: 'bottom' } }
            }
        });
    });
</script>
<?= $this->endSection() ?>
