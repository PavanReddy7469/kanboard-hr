<?php
/**
 * One task under a task-list group header.
 *
 * row_class is the group's collapse key; the shared tree script hides every
 * .tree-row carrying the key of a folded ancestor.
 *
 * @var array  $task
 * @var string $row_class
 */
$isLate = ! empty($task['date_due']) && $task['date_due'] < time() && empty($task['is_closed']);
?>
<tr class="tree-row tm-task-row <?= $row_class ?><?= ! empty($task['is_closed']) ? ' is-closed' : '' ?>">
    <td class="tm-col-name">
        <span class="tm-indent"></span>
        <?php if (! empty($task['reference'])): ?>
            <span class="tm-task-code"><?= $this->text->e($task['reference']) ?></span>
        <?php endif ?>
        <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $task['project_id'], 'task_id' => $task['id'])) ?>"
           class="task-title-link"
           data-zp-open="<?= $this->url->href('TaskPanelController', 'show', array('plugin' => 'TaskManager', 'task_id' => $task['id'], 'project_id' => $task['project_id'])) ?>">
            <?= $this->text->e($task['title']) ?>
        </a>
        <?php $priority = $this->taskTree->getPriorityLabel($task['priority']) ?>
        <?php if ($priority !== ''): ?>
            <span class="priority-badge <?= $this->taskTree->getPriorityClass($task['priority']) ?>"><?= $priority ?></span>
        <?php endif ?>
    </td>
    <td>
        <span class="tm-assignee">
            <i class="fa fa-user-circle-o"></i> <?= $this->text->e($task['assignee_name']) ?>
        </span>
    </td>
    <td class="tm-date<?= $isLate ? ' is-late' : '' ?>">
        <?= ! empty($task['date_due']) ? $this->dt->date($task['date_due']) : '-' ?>
    </td>
    <td>
        <?php if (! empty($task['is_closed'])): ?>
            <span class="status-badge status-closed"><?= t('Closed') ?></span>
        <?php else: ?>
            <span class="status-badge <?= $this->taskTree->getStatusClass($task['column_title']) ?>"><?= $this->text->e($task['column_title']) ?></span>
        <?php endif ?>
    </td>
    <td class="tm-muted">&mdash;</td>
    <td class="tm-right">
        <div class="tm-row-actions">
            <a href="<?= $this->url->href('TaskModificationController', 'edit', array('task_id' => $task['id'], 'project_id' => $task['project_id'])) ?>"
               class="btn btn-comment js-modal-large" title="<?= t('Edit this task') ?>">
                <i class="fa fa-pencil"></i>
            </a>
        </div>
    </td>
</tr>
