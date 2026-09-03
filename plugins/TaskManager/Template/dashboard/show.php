<div class="sb-dash">

    <div class="sb-dash-head">
        <div>
            <h2><?= t('Dashboard') ?> &mdash; <?= $this->text->e($project['name']) ?></h2>
            <p class="sb-dash-sub"><?= t('Live figures, recalculated on every load.') ?></p>
        </div>
        <div class="sb-dash-head-actions">
            <a class="btn-add-secondary" href="<?= $this->url->href('TaskManagerController', 'show', array('plugin' => 'TaskManager', 'project_id' => $project['id'])) ?>">
                <i class="fa fa-sitemap" aria-hidden="true"></i> <?= t('Task Tree') ?>
            </a>
            <a class="btn-add-secondary" href="<?= $this->url->href('AnalyticController', 'taskDistribution', array('project_id' => $project['id'])) ?>">
                <i class="fa fa-bar-chart" aria-hidden="true"></i> <?= t('Full analytics') ?>
            </a>
        </div>
    </div>

    <?= $this->render('TaskManager:dashboard/kpis', array('kpis' => $kpis)) ?>

    <div class="sb-dash-grid">
        <?= $this->render('TaskManager:dashboard/trend', array('trend' => $trend)) ?>
        <?= $this->render('TaskManager:dashboard/distribution', array('distribution' => $distribution)) ?>
        <?= $this->render('TaskManager:dashboard/workload', array('workload' => $workload, 'project' => $project)) ?>
        <?= $this->render('TaskManager:dashboard/attention', array('attention' => $attention, 'show_project' => false)) ?>
    </div>

    <div class="sb-card sb-dash-activity">
        <div class="sb-card-head">
            <h3><?= t('Recent activity') ?></h3>
            <a href="<?= $this->url->href('ActivityController', 'project', array('project_id' => $project['id'])) ?>"><?= t('See all') ?></a>
        </div>
        <div class="sb-card-body">
            <?= $this->render('event/events', array('events' => $events)) ?>
        </div>
    </div>

</div>
