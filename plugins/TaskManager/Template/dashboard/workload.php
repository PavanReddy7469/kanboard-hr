<div class="sb-card sb-dash-workload">
    <div class="sb-card-head">
        <h3><?= t('Workload') ?></h3>
        <span class="sb-card-note"><?= t('Open tasks per person') ?></span>
    </div>
    <div class="sb-card-body">
        <?php if (empty($workload['rows'])): ?>
            <p class="sb-empty"><?= t('Nothing open right now.') ?></p>
        <?php else: ?>
            <ul class="sb-workload">
                <?php foreach ($workload['rows'] as $row): ?>
                    <li>
                        <span class="sb-workload-name <?= $row['user_id'] === 0 ? 'is-unassigned' : '' ?>">
                            <?= $this->text->e($row['name']) ?>
                        </span>
                        <span class="sb-workload-track">
                            <span class="sb-workload-fill" style="width: <?= round($row['open'] / $workload['peak'] * 100) ?>%"></span>
                            <?php if ($row['overdue'] > 0): ?>
                                <span class="sb-workload-late" style="width: <?= round($row['overdue'] / $workload['peak'] * 100) ?>%"></span>
                            <?php endif ?>
                        </span>
                        <span class="sb-workload-figures">
                            <strong><?= $row['open'] ?></strong>
                            <?php if ($row['overdue'] > 0): ?>
                                <em class="is-bad"><?= t('%d late', $row['overdue']) ?></em>
                            <?php endif ?>
                            <?php if ($row['hours'] > 0): ?>
                                <em><?= number_format($row['hours'], 1) ?>h</em>
                            <?php endif ?>
                        </span>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </div>
</div>
