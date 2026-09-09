<?php
$pid = $task['project_id'];
$tid = $task['id'];

$row = function ($label, $value, $hint = '') {
    return array('label' => $label, 'value' => $value, 'hint' => $hint);
};
?>
<div class="zp-head">
    <div class="zp-head-tags">
        <span class="zp-kind"><i class="fa fa-check-square-o" aria-hidden="true"></i> <?= t('Task') ?></span>
        <span class="zp-code"><?= $this->text->e($code) ?></span>
    </div>
    <div class="zp-head-actions">
        <?php if ($can_edit): ?>
            <?= $this->modal->large('pencil-square-o', t('Edit'), 'TaskModificationController', 'edit', array('task_id' => $tid, 'project_id' => $pid)) ?>
        <?php endif ?>
        <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'task_id' => $tid, 'project_id' => $pid)) ?>" title="<?= t('Open full page') ?>">
            <i class="fa fa-expand" aria-hidden="true"></i>
        </a>
        <a href="#" class="zp-close" title="<?= t('Close') ?>"><i class="fa fa-times" aria-hidden="true"></i></a>
    </div>
</div>

<div class="zp-body">

    <h2 class="zp-title"><?= $this->text->e($task['title']) ?></h2>

    <div class="zp-byline">
        <?= t('By %s', $this->text->e($task['creator_name'] ?: $task['creator_username'] ?: t('Unknown'))) ?>
        <?php if (! empty($comments)): ?>
            <span class="zp-byline-item"><i class="fa fa-comment-o" aria-hidden="true"></i> <?= count($comments) ?></span>
        <?php endif ?>
        <?php if (! empty($files)): ?>
            <span class="zp-byline-item"><i class="fa fa-paperclip" aria-hidden="true"></i> <?= count($files) ?></span>
        <?php endif ?>
    </div>

    <div class="zp-card zp-statuscard">
        <?= $this->render('TaskManager:grid/status_select', array(
            'options'        => $columns,
            'option_classes' => $column_classes,
            'current'        => $task['column_id'],
            'current_label'  => $task['column_title'],
            'current_class'  => $status_class,
            'editable'       => $can_move,
            'url_template'   => $this->url->href('StatusChangeController', 'task', array('plugin' => 'TaskManager', 'project_id' => $pid, 'task_id' => $tid)).'&column_id=%s&csrf_token=%s',
        )) ?>
        <span class="zp-cardlabel"><?= t('Status') ?></span>
    </div>

    <div class="zp-card">
        <div class="zp-cardhead">
            <h3><?= t('Description') ?></h3>
            <?php if ($can_edit): ?>
                <?= $this->modal->medium('pencil', t('Edit'), 'TaskModificationController', 'edit', array('task_id' => $tid, 'project_id' => $pid)) ?>
            <?php endif ?>
        </div>
        <div class="zp-desc">
            <?php if ($task['description'] === ''): ?>
                <span class="zp-muted"><?= t('There is no description.') ?></span>
            <?php else: ?>
                <?= $this->text->markdown($task['description']) ?>
            <?php endif ?>
        </div>
    </div>

    <div class="zp-card">
        <div class="zp-cardhead">
            <h3><?= t('Task Information') ?></h3>
        </div>

        <dl class="zp-info">
            <?php /* The swimlane row used to sit here, labelled "Associated
                     Team". Every project has exactly one - the default - so it
                     only ever read "Default swimlane", which told the reader
                     nothing. The swimlane itself stays: tasks.swimlane_id is
                     NOT NULL with a cascading foreign key, and the Kanban board
                     is rendered from it. It is simply not shown. */ ?>
            <dt><?= t('Owner') ?></dt>
            <dd>
                <?php if (! empty($task['assignee_username'])): ?>
                    <span class="zp-chip"><?= $this->text->e($this->user->formatName($task['owner_id'], $task['assignee_name'] ?: $task['assignee_username'])) ?></span>
                <?php else: ?>
                    <span class="zp-muted"><?= t('Unassigned') ?></span>
                <?php endif ?>
            </dd>

            <dt><?= t('Status') ?></dt>
            <dd><?= $this->render('TaskManager:grid/status_select', array(
                    'options'        => $columns,
                    'option_classes' => $column_classes,
                    'current'        => $task['column_id'],
                    'current_label'  => $task['column_title'],
                    'current_class'  => $status_class,
                    'editable'       => $can_move,
                    'url_template'   => $this->url->href('StatusChangeController', 'task', array('plugin' => 'TaskManager', 'project_id' => $pid, 'task_id' => $tid)).'&column_id=%s&csrf_token=%s',
                )) ?></dd>

            <dt><?= t('Start Date') ?></dt>
            <dd><?= $task['date_started'] > 0 ? $this->dt->date($task['date_started']) : '<span class="zp-muted">&mdash;</span>' ?></dd>

            <dt><?= t('Due Date') ?></dt>
            <dd><?= $task['date_due'] > 0 ? $this->dt->date($task['date_due']) : '<span class="zp-muted">&mdash;</span>' ?></dd>

            <dt><?= t('Duration') ?></dt>
            <dd><?= $duration > 0 ? t('%d days', $duration) : '<span class="zp-muted">&mdash;</span>' ?></dd>

            <dt><?= t('Priority') ?></dt>
            <dd>
                <?php if ($task['priority'] > 0): ?>
                    <span class="zp-priority <?= $this->taskTree->getPriorityClass($task['priority']) ?>"><?= $this->taskTree->getPriorityLabel($task['priority']) ?></span>
                <?php else: ?>
                    <span class="zp-muted">&mdash;</span>
                <?php endif ?>
            </dd>

            <dt><?= t('Completion Percentage') ?></dt>
            <dd>
                <span class="zp-progress">
                    <span class="zg-meter"><span class="zg-meter-fill" style="width: <?= $progress ?>%"></span></span>
                    <em><?= $progress ?>%</em>
                </span>
            </dd>

            <dt><?= t('Category') ?></dt>
            <dd><?= ! empty($task['category_name']) ? $this->text->e($task['category_name']) : '<span class="zp-muted">&mdash;</span>' ?></dd>

            <dt><?= t('Recurrence') ?></dt>
            <dd><?= $this->text->e($recurrence) ?></dd>
        </dl>
    </div>

    <div class="zp-tabs" role="tablist">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="#" class="zp-tab <?= $key === $tab ? 'is-active' : '' ?>" data-zp-tab="<?= $key ?>"
               data-zp-url="<?= $this->url->href('TaskPanelController', 'show', array('plugin' => 'TaskManager', 'task_id' => $tid, 'project_id' => $pid, 'tab' => $key)) ?>">
                <?= t($label) ?>
                <?php
                $count = 0;
                if ($key === 'comments')  { $count = count($comments); }
                if ($key === 'subtasks')  { $count = count($subtasks); }
                if ($key === 'documents') { $count = count($files); }
                if ($key === 'links')     { $count = count($dependencies); }
                ?>
                <?php if ($count > 0): ?><span class="zp-tabcount"><?= $count ?></span><?php endif ?>
            </a>
        <?php endforeach ?>
    </div>

    <div class="zp-tabbody">
        <?= $this->render('TaskManager:grid/panel_'.$tab, array(
            'task'         => $task,
            'project'      => $project,
            'can_edit'     => $can_edit,
            'comments'     => $comments,
            'subtasks'     => $subtasks,
            'files'        => $files,
            'links'        => $links,
            'dependencies' => $dependencies,
            'entries'      => $entries,
            'transitions'  => $transitions,
        )) ?>
    </div>

</div>
