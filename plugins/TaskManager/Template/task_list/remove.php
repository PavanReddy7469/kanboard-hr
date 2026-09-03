<div class="page-header">
    <h2><?= t('Remove a task list') ?></h2>
</div>

<div class="confirm">
    <p class="alert alert-info">
        <?= t('Do you really want to remove this task list: "%s"?', $task_list['title']) ?>
    </p>
    <p class="alert alert-normal">
        <?= t('Its tasks are kept — they simply stop belonging to a task list.') ?>
    </p>

    <?= $this->modal->confirmButtons(
        'TaskGroupController',
        'remove',
        array('project_id' => $project['id'], 'task_list_id' => $task_list['id'], 'plugin' => 'TaskManager')
    ) ?>
</div>
