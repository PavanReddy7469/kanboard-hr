<?php
/**
 * Task Lists.
 *
 * Each list is a group header you can fold, with its tasks underneath, and
 * the tasks in no list get a group of their own at the end so nothing is
 * only visible somewhere else.
 *
 * The folding reuses the project tree's machinery: a row carries the class
 * of every ancestor it has, and the toggle carries data-zg-tree. The script
 * that drives it is already loaded on every page of this plugin, so there is
 * nothing inline here - the Content-Security-Policy would drop it anyway.
 *
 * @var array $project
 * @var array $task_lists
 * @var array $unfiled
 */
?>
<section class="zg tm-tasklists">
    <div class="zg-title">
        <div>
            <h2><i class="fa fa-list-ul text-indigo"></i> <?= t('Task Lists') ?></h2>
            <p class="zg-subtitle">
                <?= t('Organize and track tasks grouped by feature batches, sprints, and deliverable packages.') ?>
            </p>
        </div>
        <div>
            <?= $this->modal->medium('plus', t('New Task List'), 'TaskGroupController', 'create', array('plugin' => 'TaskManager', 'project_id' => $project['id'])) ?>
        </div>
    </div>

    <?php if (empty($task_lists) && empty($unfiled)): ?>
        <div class="tm-empty-panel">
            <div class="tm-empty-icon"><i class="fa fa-list-ul"></i></div>
            <h3><?= t('No Task Lists Yet') ?></h3>
            <p>
                <?= t('Task lists help group related tasks together within milestones. Create your first task list to structure your project workflow.') ?>
            </p>
            <?= $this->modal->medium('plus', t('Create First Task List'), 'TaskGroupController', 'create', array('plugin' => 'TaskManager', 'project_id' => $project['id'])) ?>
        </div>
    <?php else: ?>
        <div class="zg-scroll tm-tasklists-panel">
            <table class="zg-table tm-tasklists-table">
                <thead>
                    <tr>
                        <th class="tm-col-name"><?= t('Task List') ?></th>
                        <th><?= t('Assignee') ?></th>
                        <th><?= t('Due date') ?></th>
                        <th><?= t('Status') ?></th>
                        <th class="tm-col-progress"><?= t('Progress') ?></th>
                        <th class="tm-right"><?= t('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($task_lists as $tl): ?>
                    <?php $key = 'tl-'.$tl['id'] ?>
                    <tr class="tm-group-row">
                        <td class="tm-col-name">
                            <span class="tree-toggle" data-zg-tree="<?= $key ?>" data-tree-key="<?= $key ?>" role="button" tabindex="0" title="<?= t('Show or hide the tasks in this list') ?>">
                                <i class="fa fa-minus-square-o tree-icon"></i>
                            </span>
                            <i class="fa fa-folder-o tm-group-icon"></i>
                            <strong><?= $this->text->e($tl['title']) ?></strong>
                            <span class="count-pill-soft"><?= (int) $tl['total_tasks'] ?></span>
                            <?php if (! empty($tl['milestone_title']) && $tl['milestone_title'] !== t('None')): ?>
                                <span class="tm-milestone-chip"><i class="fa fa-flag"></i> <?= $this->text->e($tl['milestone_title']) ?></span>
                            <?php endif ?>
                        </td>
                        <td class="tm-muted">&mdash;</td>
                        <td class="tm-muted">&mdash;</td>
                        <td class="tm-muted">
                            <?= t('%d of %d done', (int) $tl['closed_tasks'], (int) $tl['total_tasks']) ?>
                        </td>
                        <td>
                            <div class="tm-progress">
                                <div class="tm-progress-track">
                                    <div class="tm-progress-fill<?= $tl['progress'] == 100 ? ' is-complete' : '' ?>" style="width: <?= (int) $tl['progress'] ?>%"></div>
                                </div>
                                <span class="tm-progress-value"><?= (int) $tl['progress'] ?>%</span>
                            </div>
                        </td>
                        <td class="tm-right">
                            <div class="tm-row-actions">
                                <?= $this->modal->medium('edit', t('Edit'), 'TaskGroupController', 'edit', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'task_list_id' => $tl['id'])) ?>
                                <?= $this->modal->confirm('trash-o', t('Remove'), 'TaskGroupController', 'confirm', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'task_list_id' => $tl['id'])) ?>
                            </div>
                        </td>
                    </tr>

                    <?php if (empty($tl['tasks'])): ?>
                        <tr class="tree-row tm-task-row tm-task-empty <?= $key ?>">
                            <td class="tm-col-name" colspan="6">
                                <span class="tm-indent"></span>
                                <span class="tm-muted"><?= t('No tasks in this list yet. Open a task and choose this list in its Task list field.') ?></span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tl['tasks'] as $task): ?>
                            <?= $this->render('TaskManager:task_list/task_row', array('task' => $task, 'row_class' => $key)) ?>
                        <?php endforeach ?>
                    <?php endif ?>
                <?php endforeach ?>

                <?php if (! empty($unfiled)): ?>
                    <tr class="tm-group-row tm-group-unfiled">
                        <td class="tm-col-name">
                            <span class="tree-toggle" data-zg-tree="tl-none" data-tree-key="tl-none" role="button" tabindex="0" title="<?= t('Show or hide these tasks') ?>">
                                <i class="fa fa-minus-square-o tree-icon"></i>
                            </span>
                            <i class="fa fa-inbox tm-group-icon"></i>
                            <strong><?= t('Not in any list') ?></strong>
                            <span class="count-pill-soft"><?= count($unfiled) ?></span>
                        </td>
                        <td class="tm-muted" colspan="5">
                            <?= t('These tasks belong to the project but to no list yet.') ?>
                        </td>
                    </tr>

                    <?php foreach ($unfiled as $task): ?>
                        <?= $this->render('TaskManager:task_list/task_row', array('task' => $task, 'row_class' => 'tl-none')) ?>
                    <?php endforeach ?>
                <?php endif ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
