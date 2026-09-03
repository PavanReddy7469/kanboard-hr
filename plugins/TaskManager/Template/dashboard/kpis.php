<div class="sb-kpis">
    <div class="sb-kpi">
        <span class="sb-kpi-label"><?= t('Total tasks') ?></span>
        <span class="sb-kpi-value"><?= $kpis['total'] ?></span>
        <span class="sb-kpi-foot"><?= t('%d open, %d closed', $kpis['open'], $kpis['closed']) ?></span>
    </div>

    <div class="sb-kpi <?= $kpis['overdue'] > 0 ? 'is-bad' : '' ?>">
        <span class="sb-kpi-label"><?= t('Overdue tasks') ?></span>
        <span class="sb-kpi-value"><?= $kpis['overdue'] ?></span>
        <span class="sb-kpi-foot"><?= $kpis['overdue'] > 0 ? t('Past the due date') : t('Nothing late') ?></span>
    </div>

    <div class="sb-kpi <?= $kpis['due_soon'] > 0 ? 'is-warn' : '' ?>">
        <span class="sb-kpi-label"><?= t('Due this week') ?></span>
        <span class="sb-kpi-value"><?= $kpis['due_soon'] ?></span>
        <span class="sb-kpi-foot"><?= t('Next 7 days') ?></span>
    </div>

    <div class="sb-kpi <?= $kpis['unassigned'] > 0 ? 'is-warn' : '' ?>">
        <span class="sb-kpi-label"><?= t('Unassigned') ?></span>
        <span class="sb-kpi-value"><?= $kpis['unassigned'] ?></span>
        <span class="sb-kpi-foot"><?= t('Open, nobody on it') ?></span>
    </div>

    <div class="sb-kpi is-good">
        <span class="sb-kpi-label"><?= t('Closed (30d)') ?></span>
        <span class="sb-kpi-value"><?= $kpis['completed'] ?></span>
        <span class="sb-kpi-foot"><?= t('Completed recently') ?></span>
    </div>

    <div class="sb-kpi">
        <span class="sb-kpi-label"><?= t('Work in Progress') ?></span>
        <span class="sb-kpi-value"><?= $kpis['in_progress'] ?></span>
        <span class="sb-kpi-foot"><?= t('In active development') ?></span>
    </div>

    <div class="sb-kpi sb-kpi-progress">
        <span class="sb-kpi-label"><?= t('Progress') ?></span>
        <span class="sb-kpi-value"><?= $kpis['progress'] ?><small>%</small></span>
        <span class="sb-progress" role="img" aria-label="<?= t('%d%% complete', $kpis['progress']) ?>">
            <span class="sb-progress-fill" style="width: <?= $kpis['progress'] ?>%"></span>
        </span>
    </div>
</div>
