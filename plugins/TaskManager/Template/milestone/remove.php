<div class="page-header">
    <h2><?= t('Remove a milestone') ?></h2>
</div>

<div class="confirm">
    <p class="alert alert-info">
        <?= t('Do you really want to remove this milestone: "%s"?', $milestone['title']) ?>
    </p>
    <p class="alert alert-normal">
        <?= t('Its task lists and tasks are kept — they simply stop belonging to a milestone.') ?>
    </p>

    <?= $this->modal->confirmButtons(
        'MilestoneController',
        'remove',
        array('project_id' => $project['id'], 'milestone_id' => $milestone['id'], 'plugin' => 'TaskManager')
    ) ?>
</div>
