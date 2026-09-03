<div class="page-header">
    <h2><?= t('Edit a sub-task') ?></h2>
</div>

<form method="post" action="<?= $this->url->href('SubtaskController', 'update', array('task_id' => $task['id'], 'subtask_id' => $subtask['id'])) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>

    <?= $this->subtask->renderTitleField($values, $errors, array('autofocus')) ?>

    <div class="form-columns" style="display: flex; gap: 16px; margin-top: 12px; margin-bottom: 6px;">
        <div style="flex: 1;">
            <?= $this->subtask->renderStatusField($status_list, $values, $errors) ?>
        </div>
        <div style="flex: 1;">
            <?= $this->subtask->renderAssigneeField($users_list, $values, $errors) ?>
        </div>
    </div>

    <div class="form-columns" style="display: flex; gap: 16px; margin-top: 10px; margin-bottom: 6px;">
        <div style="flex: 1;">
            <?= $this->subtask->renderDueDateField($values, $errors) ?>
        </div>
        <div style="flex: 1;">
            <?= $this->subtask->renderTimeEstimatedField($values, $errors) ?>
        </div>
    </div>

    <div style="margin-top: 10px; margin-bottom: 12px;">
        <?= $this->subtask->renderTimeSpentField($values, $errors) ?>
    </div>

    <?= $this->hook->render('template:subtask:form:edit', array('values' => $values, 'errors' => $errors)) ?>

    <?= $this->modal->submitButtons() ?>
</form>
