<div class="page-header">
    <h2><?= t('Add dependency') ?></h2>
</div>

<form method="post" action="<?= $this->url->href('DependencyController', 'save', array('task_id' => $task['id'], 'project_id' => $task['project_id'], 'plugin' => 'TaskManager')) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>

    <p class="alert alert-info">
        <?= t('#%d %s depends on:', $task['id'], $this->text->e($task['title'])) ?>
    </p>

    <?= $this->form->label(t('Task'), 'depends_on_id') ?>
    <?= $this->form->select('depends_on_id', $tasks, $values, $errors, array('autofocus', 'required', 'tabindex="1"')) ?>

    <?= $this->form->label(t('Type'), 'dependency_type') ?>
    <?= $this->form->select('dependency_type', $types, $values, $errors, array('tabindex="2"')) ?>

    <?= $this->form->label(t('Lag in days'), 'lag_days') ?>
    <?= $this->form->number('lag_days', $values, $errors, array('tabindex="3"')) ?>
    <p class="form-help"><?= t('Negative values pull the successor earlier (lead).') ?></p>

    <?= $this->modal->submitButtons() ?>
</form>
