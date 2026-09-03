<div class="page-header">
    <h2><?= t('Remove dependency') ?></h2>
</div>

<div class="confirm">
    <p class="alert alert-info">
        <?= t('Do you really want to remove this dependency on #%d?', $dependency['depends_on_id']) ?>
    </p>

    <?= $this->modal->confirmButtons(
        'DependencyController',
        'remove',
        array('task_id' => $task['id'], 'dependency_id' => $dependency['id'], 'project_id' => $task['project_id'], 'plugin' => 'TaskManager')
    ) ?>
</div>
