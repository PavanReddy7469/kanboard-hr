<?php
/**
 * One task row plus its subtasks. Shared by the milestone tree and any other
 * view that needs the same eight columns.
 *
 * @var array   $project
 * @var array   $task
 * @var bool    $is_lead
 * @var string  $row_class   space-separated collapse keys from ancestor rows
 * @var int     $indent      1 = directly under a milestone, 2 = inside a task list
 */
$tKey = 'st-'.$task['id'];
?>
<tr class="tree-row task-level-row <?= $row_class ?>">
    <td class="task-cell">
        <span class="indent-<?= (int) $indent ?>"></span>
        <?php if (! empty($task['subtasks'])): ?>
            <span class="tree-toggle" data-zg-tree="<?= $tKey ?>">
                <i class="fa fa-minus-square-o tree-icon"></i>
            </span>
        <?php else: ?>
            <span class="tree-spacer"></span>
        <?php endif ?>
        <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $task['project_id'], 'task_id' => $task['id'])) ?>"
           class="task-title-link"
           data-zp-open="<?= $this->url->href('TaskPanelController', 'show', array('plugin' => 'TaskManager', 'task_id' => $task['id'], 'project_id' => $task['project_id'])) ?>">
            <strong><?= $this->text->e($task['title']) ?></strong>
        </a>
        <?php if (! empty($task['subtasks'])): ?>
            <span class="count-pill-soft"><?= count($task['subtasks']) ?> ST</span>
        <?php endif ?>
    </td>
    <td>
        <span class="tm-assignee">
            <i class="fa fa-user-circle-o"></i>
            <?= $this->text->e($task['assignee_name']) ?>
        </span>
    </td>
    <td class="tm-date <?= $task['date_due'] && $task['date_due'] < time() && $task['is_active'] ? 'is-late' : '' ?>">
        <?= $task['date_due'] ? $this->dt->date($task['date_due']) : '-' ?>
    </td>
    <td class="tm-date"><?= $task['date_completed'] ? $this->dt->date($task['date_completed']) : '-' ?></td>
    <td>
        <span class="status-badge <?= $task['status_class'] ?>"><?= $this->text->e($task['column_title']) ?></span>
    </td>
    <td>
        <?php if (empty($task['dependencies'])): ?>
            <span class="tm-muted"><i class="fa fa-link"></i> <?= t('None') ?></span>
        <?php else: ?>
            <?php foreach ($task['dependencies'] as $dependency): ?>
                <span class="dep-chip" title="<?= t('Depends on task #%d', $dependency['depends_on_id']) ?>">
                    <i class="fa fa-link"></i> <?= $this->text->e($this->taskTree->getDependencyLabel($dependency)) ?>
                </span>
            <?php endforeach ?>
        <?php endif ?>
    </td>
    <td>
        <?php if ($task['priority_label'] !== ''): ?>
            <span class="priority-badge <?= $task['priority_class'] ?>"><?= $task['priority_label'] ?></span>
        <?php else: ?>
            <span class="tm-muted">-</span>
        <?php endif ?>
    </td>
    <td class="tm-right">
        <div class="action-buttons">
            <a href="<?= $this->url->href('CommentController', 'create', array('task_id' => $task['id'], 'project_id' => $project['id'])) ?>" class="btn btn-comment js-modal-medium" title="<?= t('Add a comment') ?>">
                <i class="fa fa-comment-o"></i> <?= t('Comment') ?>
            </a>
            <a href="<?= $this->url->href('TaskDuplicationController', 'duplicate', array('task_id' => $task['id'], 'project_id' => $project['id'])) ?>" class="btn btn-duplicate js-modal-medium" title="<?= t('Duplicate') ?>">
                <i class="fa fa-files-o"></i>
            </a>
            <?php if ($is_lead): ?>
                <a href="<?= $this->url->href('TaskSuppressionController', 'confirm', array('task_id' => $task['id'], 'project_id' => $project['id'])) ?>" class="btn btn-delete js-modal-medium" title="<?= t('Remove') ?>">
                    <i class="fa fa-trash-o"></i>
                </a>
            <?php endif ?>
        </div>
    </td>
</tr>

<?php foreach ($task['subtasks'] as $subtask): ?>
    <tr class="tree-row subtask-level-row <?= $row_class ?> <?= $tKey ?>">
        <td class="subtask-cell">
            <span class="indent-<?= (int) $indent + 1 ?>"></span>
            <span class="legend-subtask"></span>
            <span class="subtask-title"><?= $this->text->e($subtask['title']) ?></span>
        </td>
        <td class="tm-muted"><?= $this->text->e($subtask['assignee_name']) ?></td>
        <td class="tm-date"><?= $subtask['date_due'] ? $this->dt->date($subtask['date_due']) : '-' ?></td>
        <td>-</td>
        <td><span class="status-badge <?= $subtask['status_class'] ?>"><?= $this->text->e($subtask['status_label']) ?></span></td>
        <td>-</td>
        <td>-</td>
        <td class="tm-right">
            <span class="est-pill"><?= t('Est') ?>: <?= (float) $subtask['time_estimated'] ?>h</span>
        </td>
    </tr>
<?php endforeach ?>
