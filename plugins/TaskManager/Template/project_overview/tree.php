<?php
$treeData  = $this->taskTree->getProjectTree($project['id']);
$isLead    = $this->taskTree->canManageTasks($project['id']);
$assignees = $this->taskTree->getAssigneeList($project['id']);
$nbTasks   = 0;
foreach ($treeData as $_m) { $nbTasks += $_m['nb_rows']; }
?>
<div class="taskmanager-container">
    <div class="taskmanager-header">
        <h2>
            <i class="fa fa-sitemap"></i>
            <span><?= t('Detailed Project Overview Table') ?> &mdash; <strong><?= $this->text->e($project['name']) ?></strong></span>
        </h2>
        <div class="permission-notice">
            <span class="badge badge-lead">
                <i class="fa fa-shield"></i> <?= t('Add &amp; Delete') ?>: <strong><?= t('Lead &amp; Above') ?></strong>
            </span>
            <span class="badge badge-everyone">
                <i class="fa fa-comments-o"></i> <?= t('Comments') ?>: <strong><?= t('Everyone') ?></strong>
            </span>
            <a href="<?= $this->url->href('TaskGanttController', 'show', array('project_id' => $project['id'], 'plugin' => 'Gantt')) ?>" class="badge badge-active">
                <i class="fa fa-sliders"></i> <?= t('View Live Gantt Chart Schedule') ?>
            </a>
        </div>
    </div>

    <div class="taskmanager-filterbar">
        <form method="get" action="<?= $this->url->dir() ?>" class="tm-filter-form">
            <input type="hidden" name="controller" value="ProjectOverviewController">
            <input type="hidden" name="action" value="show">
            <input type="hidden" name="project_id" value="<?= $project['id'] ?>">

            <div class="filter-group">
                <label for="tm-assignee-filter"><i class="fa fa-filter text-indigo"></i> <?= t('Assignee') ?>:</label>
                <select id="tm-assignee-filter" name="assignee_filter" data-zg-submit>
                    <option value=""><?= t('All Members &amp; Engineers') ?></option>
                    <?php foreach ($assignees as $userId => $userName): ?>
                        <option value="<?= $userId ?>"><?= $this->text->e($userName) ?></option>
                    <?php endforeach ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="tm-status-filter"><i class="fa fa-tasks text-indigo"></i> <?= t('Status') ?>:</label>
                <select id="tm-status-filter" name="status_filter" data-zg-submit>
                    <option value=""><?= t('All Statuses') ?></option>
                    <option value="Open">Open</option>
                    <option value="WIP">WIP</option>
                    <option value="Rev">Rev</option>
                    <option value="Closed">Closed</option>
                </select>
            </div>

            <?php if ($isLead): ?>
                <div class="tm-toolbar-actions">
                    <a href="<?= $this->url->href('MilestoneController', 'create', array('project_id' => $project['id'], 'plugin' => 'TaskManager')) ?>" class="btn-add-secondary js-modal-medium">
                        <i class="fa fa-flag-o"></i> <?= t('Milestone') ?>
                    </a>
                    <a href="<?= $this->url->href('TaskGroupController', 'create', array('project_id' => $project['id'], 'plugin' => 'TaskManager')) ?>" class="btn-add-secondary js-modal-medium">
                        <i class="fa fa-folder-o"></i> <?= t('Task List') ?>
                    </a>
                    <a href="<?= $this->url->href('TaskCreationController', 'show', array('project_id' => $project['id'])) ?>" class="btn-add-task js-modal-medium">
                        <i class="fa fa-plus-circle"></i> <?= t('Add New Task') ?>
                    </a>
                </div>
            <?php endif ?>
        </form>
    </div>

    <div class="tree-table-wrapper">
        <?php if ($nbTasks === 0): ?>
            <p class="alert alert-info">
                <i class="fa fa-info-circle"></i>
                <?= t('Nothing in this project yet.') ?>
                <?php if ($isLead): ?><?= t('Start with a milestone, then add task lists and tasks underneath it.') ?><?php endif ?>
            </p>
        <?php else: ?>
            <table class="tree-table">
                <thead>
                    <tr>
                        <th style="width: 34%;"><?= t('Task / Subtask Name') ?></th>
                        <th><?= t('Assigned To') ?></th>
                        <th><?= t('Due Date') ?></th>
                        <th><?= t('Completion Date') ?></th>
                        <th><?= t('Status') ?></th>
                        <th><?= t('Dependencies') ?></th>
                        <th><?= t('Priority') ?></th>
                        <th class="tm-right"><?= t('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($treeData as $milestone): ?>
                    <?php $mKey = 'ms-'.$project['id'].'-'.$milestone['id'] ?>

                    <tr class="tree-row milestone-level-row <?= $milestone['is_orphan'] ? 'is-orphan' : '' ?>">
                        <td class="milestone-cell">
                            <span class="tree-toggle" data-zg-tree="<?= $mKey ?>">
                                <i class="fa fa-minus-square-o tree-icon"></i>
                            </span>
                            <span class="milestone-diamond <?= $milestone['is_reached'] ? 'is-done' : ($milestone['is_overdue'] ? 'is-late' : '') ?>"></span>
                            <strong><?= $this->text->e($milestone['title']) ?></strong>
                            <span class="count-pill"><?= (int) $milestone['nb_tasks'] ?> <?= t('tasks') ?></span>
                            <?php if ($milestone['is_overdue']): ?>
                                <span class="chip-risk"><?= t('AT RISK') ?></span>
                            <?php endif ?>
                        </td>
                        <td class="tm-muted">&mdash;</td>
                        <td class="tm-date"><?= $milestone['date_due'] ? $this->dt->date($milestone['date_due']) : '-' ?></td>
                        <td class="tm-date"><?= $milestone['date_reached'] ? $this->dt->date($milestone['date_reached']) : '-' ?></td>
                        <td>
                            <?php if ($milestone['is_reached']): ?>
                                <span class="status-badge status-closed"><?= t('Reached') ?></span>
                            <?php elseif ($milestone['id']): ?>
                                <span class="status-badge status-wip"><?= t('Active') ?></span>
                            <?php else: ?>
                                <span class="tm-muted">&mdash;</span>
                            <?php endif ?>
                        </td>
                        <td class="tm-muted">&mdash;</td>
                        <td class="tm-muted">&mdash;</td>
                        <td class="tm-right">
                            <span class="progress-pill"><?= (int) $milestone['progress'] ?>%</span>
                            <?php if ($isLead && $milestone['id']): ?>
                                <a href="<?= $this->url->href('MilestoneController', 'edit', array('project_id' => $project['id'], 'milestone_id' => $milestone['id'], 'plugin' => 'TaskManager')) ?>" class="btn btn-comment js-modal-medium" title="<?= t('Edit') ?>"><i class="fa fa-pencil"></i></a>
                                <a href="<?= $this->url->href('MilestoneController', 'confirm', array('project_id' => $project['id'], 'milestone_id' => $milestone['id'], 'plugin' => 'TaskManager')) ?>" class="btn btn-delete js-modal-medium" title="<?= t('Remove') ?>"><i class="fa fa-trash-o"></i></a>
                            <?php endif ?>
                        </td>
                    </tr>

                    <?php foreach ($milestone['lists'] as $taskList): ?>
                        <?php $lKey = 'tl-'.$taskList['id'] ?>
                        <tr class="tree-row tasklist-level-row <?= $mKey ?>">
                            <td class="tasklist-cell">
                                <span class="indent-1"></span>
                                <span class="tree-toggle" data-zg-tree="<?= $lKey ?>">
                                    <i class="fa fa-minus-square-o tree-icon"></i>
                                </span>
                                <i class="fa fa-folder-o"></i>
                                <strong><?= $this->text->e($taskList['title']) ?></strong>
                                <span class="count-pill-soft"><?= (int) $taskList['nb_tasks'] ?></span>
                            </td>
                            <td class="tm-muted">&mdash;</td>
                            <td>-</td>
                            <td>-</td>
                            <td class="tm-muted">&mdash;</td>
                            <td class="tm-muted">&mdash;</td>
                            <td class="tm-muted">&mdash;</td>
                            <td class="tm-right">
                                <?php if ($isLead): ?>
                                    <a href="<?= $this->url->href('TaskGroupController', 'edit', array('project_id' => $project['id'], 'task_list_id' => $taskList['id'], 'plugin' => 'TaskManager')) ?>" class="btn btn-comment js-modal-medium" title="<?= t('Edit') ?>"><i class="fa fa-pencil"></i></a>
                                    <a href="<?= $this->url->href('TaskGroupController', 'confirm', array('project_id' => $project['id'], 'task_list_id' => $taskList['id'], 'plugin' => 'TaskManager')) ?>" class="btn btn-delete js-modal-medium" title="<?= t('Remove') ?>"><i class="fa fa-trash-o"></i></a>
                                <?php endif ?>
                            </td>
                        </tr>

                        <?php foreach ($taskList['tasks'] as $task): ?>
                            <?= $this->render('TaskManager:project_overview/task_row', array(
                                'project'    => $project,
                                'task'       => $task,
                                'is_lead'    => $isLead,
                                'row_class'  => $mKey.' '.$lKey,
                                'indent'     => 2,
                            )) ?>
                        <?php endforeach ?>
                    <?php endforeach ?>

                    <?php foreach ($milestone['tasks'] as $task): ?>
                        <?= $this->render('TaskManager:project_overview/task_row', array(
                            'project'    => $project,
                            'task'       => $task,
                            'is_lead'    => $isLead,
                            'row_class'  => $mKey,
                            'indent'     => 1,
                        )) ?>
                    <?php endforeach ?>
                <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>
    </div>

    <div class="tree-legend">
        <span class="tm-muted"><?= t('Levels') ?></span>
        <span class="legend-item"><span class="milestone-diamond"></span><?= t('Milestone') ?></span>
        <span class="legend-item"><i class="fa fa-folder-o"></i><?= t('Task list') ?></span>
        <span class="legend-item"><span class="legend-task"></span><?= t('Task') ?></span>
        <span class="legend-item"><span class="legend-subtask"></span><?= t('Subtask') ?></span>
    </div>
</div>
