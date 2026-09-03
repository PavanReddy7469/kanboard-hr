<span class="logo">
    <a href="<?= $this->url->href('DashboardController', 'show') ?>" title="<?= t('Dashboard') ?>">
        <img src="<?= $this->url->dir() ?>assets/img/superbee_logo.png" alt="Superbee Logo" class="superbee-header-logo" style="height: 42px; vertical-align: middle; margin-right: 12px; background: #ffffff; padding: 4px 10px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08);" />
    </a>
</span>
<h1>
    <span class="title">
        <?php if (! empty($project) && ! empty($task)): ?>
            <?= $this->url->link($this->text->e($project['name']), 'BoardViewController', 'show', array('project_id' => $project['id'])) ?>
        <?php else: ?>
            <?= $this->text->e($title) ?>
        <?php endif ?>
    </span>
    <?php if (! empty($description)): ?>
        <?= $this->app->tooltipHTML($description) ?>
    <?php endif ?>
</h1>
