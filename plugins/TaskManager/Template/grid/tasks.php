<?php
$base = array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'mode' => $mode, 'view' => $view, 'group_by' => $group_by, 'search' => $search);

$sortLink = function ($column, $label) use ($base, $order, $direction) {
    $next = ($order === $column && $direction === 'ASC') ? 'DESC' : 'ASC';
    $caret = '';

    if ($order === $column) {
        $caret = ' <i class="fa fa-caret-'.($direction === 'ASC' ? 'up' : 'down').'" aria-hidden="true"></i>';
    }

    return array('params' => $base + array('order' => $column, 'direction' => $next), 'label' => $label, 'caret' => $caret);
};
?>
<div class="zg">

    <?php /* The display-type picker and the Filter button used to sit here,
             in a toolbar of their own. They are now at the right-hand end of
             the project tab strip (TaskManager:project_header/grid_actions),
             which puts them on the same line as the tabs instead of spending
             a whole row on two controls.

             The "Add Task" button that was also here duplicated the
             "+ Add Task" row at the top of the table below, which does the
             same job without leaving the list. */ ?>

    <?php if ($mode === 'gantt'): ?>

        <div class="zg-gantt">
            <div class="zg-gantt-bar">
                <label for="zg-scale"><?= t('Zoom') ?></label>
                <select id="zg-scale" >
                    <?php foreach ($scales as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $key === $scale ? 'selected' : '' ?>><?= $this->text->e($label) ?></option>
                    <?php endforeach ?>
                </select>
                <button type="button" class="zg-step" aria-label="<?= t('Zoom out') ?>" data-zg-step="-1">&minus;</button>
                <button type="button" class="zg-step" aria-label="<?= t('Zoom in') ?>" data-zg-step="1">+</button>

                <button type="button" class="zg-fit" data-zg-fit>
                    <i class="fa fa-arrows-h" aria-hidden="true"></i> <?= t('Fit to width') ?>
                </button>
            </div>

            <?php if (empty($tasks)): ?>
                <p class="zg-empty"><?= t('There is no task in your project.') ?></p>
            <?php else: ?>
                <?php /* The Gantt plugin's own chart.js picks this up on dom.ready. */ ?>
                <div
                    id="gantt-chart"
                    data-records='<?= json_encode($tasks, JSON_HEX_APOS) ?>'
                    data-save-url="<?= $this->url->href('TaskGanttController', 'save', array('project_id' => $project['id'], 'plugin' => 'Gantt')) ?>"
                    data-label-start-date="<?= t('Start date:') ?>"
                    data-label-end-date="<?= t('Due date:') ?>"
                    data-label-assignee="<?= t('Assignee:') ?>"
                    data-label-not-defined="<?= t('There is no start date or due date for this task.') ?>"
                ></div>
                <p class="zg-gantt-note"><i class="fa fa-info-circle" aria-hidden="true"></i> <?= t('Moving or resizing a task will change the start and due date of the task.') ?></p>
            <?php endif ?>
        </div>

    <?php elseif ($mode === 'kanban'): ?>

        <div class="zg-kanban">
            <?php /* Kanboard's own board, so drag-and-drop and task limits behave exactly as they do on the Board view. */ ?>
            <?= $this->render('board/table_container', array(
                'project'                        => $project,
                'swimlanes'                      => $swimlanes,
                'board_private_refresh_interval' => $board_private_refresh_interval,
                'board_highlight_period'         => $board_highlight_period,
            )) ?>
        </div>

    <?php else: ?>

    <div class="zg-scroll">
        <table class="zg-table zg-table-tasks">
            <thead>
                <tr>
                    <th class="zg-col-code"><?php $s = $sortLink('id', t('ID')) ?><a href="<?= $this->url->href('TaskGridController', 'show', $s['params']) ?>"><?= $s['label'] ?><?= $s['caret'] ?></a></th>
                    <th class="zg-col-title"><?php $s = $sortLink('title', t('Task Name')) ?><a href="<?= $this->url->href('TaskGridController', 'show', $s['params']) ?>"><?= $s['label'] ?><?= $s['caret'] ?></a></th>
                    <th class="zg-col-owner"><?php $s = $sortLink('owner', t('Owner')) ?><a href="<?= $this->url->href('TaskGridController', 'show', $s['params']) ?>"><?= $s['label'] ?><?= $s['caret'] ?></a></th>
                    <th class="zg-col-status"><?php $s = $sortLink('status', t('Status')) ?><a href="<?= $this->url->href('TaskGridController', 'show', $s['params']) ?>"><?= $s['label'] ?><?= $s['caret'] ?></a></th>
                    <th class="zg-col-tags"><?= t('Tags') ?></th>
                    <th class="zg-col-date"><?php $s = $sortLink('start_date', t('Start Date')) ?><a href="<?= $this->url->href('TaskGridController', 'show', $s['params']) ?>"><?= $s['label'] ?><?= $s['caret'] ?></a></th>
                    <th class="zg-col-date"><?php $s = $sortLink('date_due', t('Due Date')) ?><a href="<?= $this->url->href('TaskGridController', 'show', $s['params']) ?>"><?= $s['label'] ?><?= $s['caret'] ?></a></th>
                    <th class="zg-col-duration"><?php $s = $sortLink('duration', t('Duration')) ?><a href="<?= $this->url->href('TaskGridController', 'show', $s['params']) ?>"><?= $s['label'] ?><?= $s['caret'] ?></a></th>
                    <th class="zg-col-priority"><?php $s = $sortLink('priority', t('Priority')) ?><a href="<?= $this->url->href('TaskGridController', 'show', $s['params']) ?>"><?= $s['label'] ?><?= $s['caret'] ?></a></th>
                    <th class="zg-col-progress"><?php $s = $sortLink('progress', '% '.t('Completion')) ?><a href="<?= $this->url->href('TaskGridController', 'show', $s['params']) ?>"><?= $s['label'] ?><?= $s['caret'] ?></a></th>
                </tr>
            </thead>

            <?php if ($can_create): ?>
                <tbody class="zg-addrow">
                    <tr>
                        <td colspan="10">
                            <span class="zg-addlink"><?= $this->modal->large('plus', t('Add Task'), 'TaskCreationController', 'show', array('project_id' => $project['id'])) ?></span>
                        </td>
                    </tr>
                </tbody>
            <?php endif ?>

            <?php foreach ($groups as $group): ?>
                <?php
                /* A task-list group carries meta - a count, a milestone, how
                   much of it is done - and folds. The plain groupings (status,
                   owner, priority) carry none and stay as they were: their
                   label is free text and would not survive being used as a
                   class name. */
                $groupLabel = $group['label'];
                $groupRows  = $group['rows'];
                $meta       = $group['meta'];
                $foldKey    = empty($meta) ? '' : $group['key'];
                $rowClass   = $foldKey === '' ? '' : 'tree-row '.$foldKey;
                ?>
                <tbody>
                    <?php if ($groupLabel !== '' && empty($meta)): ?>
                        <tr class="zg-grouprow">
                            <td colspan="10">
                                <span class="zg-grouplabel"><?= $this->text->e($groupLabel) ?></span>
                                <span class="zg-groupcount"><?= count($groupRows) ?></span>
                            </td>
                        </tr>
                    <?php elseif (! empty($meta)): ?>
                        <?php $isUnfiled = $meta['task_list_id'] === 0 ?>
                        <tr class="zg-grouprow zg-grouprow-list <?= $isUnfiled ? 'is-unfiled' : '' ?>">
                            <td colspan="10">
                                <span class="zg-grouphead">
                                    <span class="zg-groupmain">
                                        <span class="tree-toggle" data-zg-tree="<?= $foldKey ?>" data-tree-key="<?= $foldKey ?>"
                                              role="button" tabindex="0" title="<?= t('Show or hide the tasks in this list') ?>">
                                            <i class="fa fa-minus-square-o tree-icon" aria-hidden="true"></i>
                                        </span>
                                        <i class="fa <?= $isUnfiled ? 'fa-inbox' : 'fa-folder-o' ?> zg-groupicon" aria-hidden="true"></i>
                                        <span class="zg-grouplabel"><?= $this->text->e($groupLabel) ?></span>
                                        <span class="zg-groupcount"><?= (int) $meta['total'] ?></span>
                                        <?php if ($meta['milestone'] !== ''): ?>
                                            <span class="zg-milestone"><i class="fa fa-flag" aria-hidden="true"></i> <?= $this->text->e($meta['milestone']) ?></span>
                                        <?php endif ?>
                                    </span>

                                    <span class="zg-groupstats">
                                        <span class="zg-groupdone"><?= t('%d of %d done', (int) $meta['closed'], (int) $meta['total']) ?></span>
                                        <span class="zg-progress">
                                            <span class="zg-meter"><span class="zg-meter-fill" style="width: <?= (int) $meta['progress'] ?>%"></span></span>
                                            <em><?= (int) $meta['progress'] ?>%</em>
                                        </span>
                                        <?php if (! $isUnfiled && $can_manage_lists): ?>
                                            <span class="zg-groupactions">
                                                <?= $this->modal->medium('edit', t('Edit'), 'TaskGroupController', 'edit', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'task_list_id' => $meta['task_list_id'])) ?>
                                                <?= $this->modal->confirm('trash-o', t('Remove'), 'TaskGroupController', 'confirm', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'task_list_id' => $meta['task_list_id'])) ?>
                                            </span>
                                        <?php endif ?>
                                    </span>
                                </span>
                            </td>
                        </tr>

                        <?php if (empty($groupRows)): ?>
                            <tr class="<?= $rowClass ?> zg-groupempty">
                                <td colspan="10">
                                    <?= $isUnfiled
                                        ? t('No tasks outside a list.')
                                        : t('No tasks in this list yet. Open a task and choose this list in its Task list field.') ?>
                                </td>
                            </tr>
                        <?php endif ?>
                    <?php endif ?>

                    <?php foreach ($groupRows as $row): ?>
                        <tr class="<?= $rowClass ?> <?= $row['is_overdue'] ? 'is-overdue' : '' ?> <?= $row['is_active'] ? '' : 'is-closed' ?>">
                            <td class="zg-col-code">
                                <?php if (! empty($row['subtasks'])): ?>
                                    <?php /* The id doubles as the disclosure control: click it to
                                             unfold this task's subtasks beneath it. */ ?>
                                    <button type="button"
                                            class="zg-code is-expandable"
                                            data-zg-subtree="<?= (int) $row['id'] ?>"
                                            aria-expanded="false"
                                            title="<?= t('Show subtasks') ?>">
                                        <i class="fa fa-caret-right zg-caret" aria-hidden="true"></i>
                                        <?= $this->text->e($row['code']) ?>
                                        <span class="zg-subcount"><?= count($row['subtasks']) ?></span>
                                    </button>
                                <?php else: ?>
                                    <span class="zg-code"><?= $this->text->e($row['code']) ?></span>
                                <?php endif ?>
                            </td>
                            <td class="zg-col-title">
                                <a href="<?= $this->url->href('TaskViewController', 'show', array('task_id' => $row['id'], 'project_id' => $project['id'])) ?>"
                                   data-zp-open="<?= $this->url->href('TaskPanelController', 'show', array('plugin' => 'TaskManager', 'task_id' => $row['id'], 'project_id' => $project['id'])) ?>">
                                    <?= $this->text->e($row['title']) ?>
                                </a>
                            </td>
                            <td class="zg-col-owner">
                                <?= $this->render('TaskManager:grid/status_select', array(
                                    'variant'        => 'owner',
                                    'options'        => $assignable_users,
                                    'option_classes' => array(),
                                    'current'        => $row['owner_id'],
                                    'current_label'  => $row['owner'] !== '' ? $row['owner'] : t('Unassigned'),
                                    'current_class'  => $row['owner_id'] > 0 ? 'zs-owner' : 'zs-owner is-unassigned',
                                    'editable'       => $can_assign,
                                    'url_template'   => $this->url->href('StatusChangeController', 'owner', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'task_id' => $row['id'])).'&owner_id=%s&csrf_token=%s',
                                )) ?>
                            </td>
                            <td class="zg-col-status">
                                <?= $this->render('TaskManager:grid/status_select', array(
                                    'options'        => $columns,
                                    'option_classes' => $column_classes,
                                    'current'        => $row['column_id'],
                                    'current_label'  => $row['status'],
                                    'current_class'  => $row['status_class'],
                                    'editable'       => $can_move,
                                    'url_template'   => $this->url->href('StatusChangeController', 'task', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'task_id' => $row['id'])).'&column_id=%s&csrf_token=%s',
                                )) ?>
                            </td>
                            <td class="zg-col-tags">
                                <?php if (empty($row['tags'])): ?>
                                    <span class="zg-muted">&mdash;</span>
                                <?php else: ?>
                                    <?php foreach ($row['tags'] as $tag): ?>
                                        <span class="zg-tag"><?= $this->text->e($tag) ?></span>
                                    <?php endforeach ?>
                                <?php endif ?>
                            </td>
                            <td class="zg-col-date"><?= $row['start_date'] > 0 ? $this->dt->date($row['start_date']) : '<span class="zg-muted">&mdash;</span>' ?></td>
                            <td class="zg-col-date <?= $row['is_overdue'] ? 'is-late' : '' ?>"><?= $row['date_due'] > 0 ? $this->dt->date($row['date_due']) : '<span class="zg-muted">&mdash;</span>' ?></td>
                            <td class="zg-col-duration"><?= $row['duration'] > 0 ? t('%d days', $row['duration']) : '<span class="zg-muted">&mdash;</span>' ?></td>
                            <td class="zg-col-priority">
                                <?= $this->render('TaskManager:grid/status_select', array(
                                    'options'        => $priority_options,
                                    'option_classes' => $priority_classes,
                                    'current'        => $row['priority'],
                                    'current_label'  => $row['priority'] > 0 ? 'P'.$row['priority'] : t('None'),
                                    'current_class'  => 'zs-prio '.$this->taskTree->getPriorityClass($row['priority']),
                                    'editable'       => $can_prioritise,
                                    'url_template'   => $this->url->href('StatusChangeController', 'priority', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'task_id' => $row['id'])).'&priority=%s&csrf_token=%s',
                                )) ?>
                            </td>
                            <td class="zg-col-progress">
                                <span class="zg-progress">
                                    <span class="zg-meter"><span class="zg-meter-fill" style="width: <?= $row['progress'] ?>%"></span></span>
                                    <em><?= $row['progress'] ?>%</em>
                                </span>
                            </td>
                        </tr>

                        <?php /* Subtask rows: same table, so the columns stay in
                                 line with the task above. Hidden until the id is
                                 clicked. */ ?>
                        <?php foreach ($row['subtasks'] as $subIndex => $sub): ?>
                            <tr class="zg-subrow <?= $rowClass ?>" data-zg-subtree-of="<?= (int) $row['id'] ?>" hidden>
                                <td class="zg-col-code">
                                    <span class="zg-subbranch <?= $subIndex === count($row['subtasks']) - 1 ? 'is-last' : '' ?>" aria-hidden="true"></span>
                                </td>
                                <td class="zg-col-title">
                                    <span class="zg-subtitle"><?= $this->text->e($sub['title']) ?></span>
                                </td>
                                <td class="zg-col-owner">
                                    <?= $this->render('TaskManager:grid/status_select', array(
                                        'variant'        => 'owner',
                                        'options'        => $assignable_users,
                                        'option_classes' => array(),
                                        'current'        => $sub['owner_id'],
                                        'current_label'  => $sub['owner'] !== '' ? $sub['owner'] : t('Unassigned'),
                                        'current_class'  => $sub['owner_id'] > 0 ? 'zs-owner' : 'zs-owner is-unassigned',
                                        'editable'       => $can_assign,
                                        'url_template'   => $this->url->href('StatusChangeController', 'subtaskOwner', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'task_id' => $row['id'], 'subtask_id' => $sub['id'])).'&owner_id=%s&csrf_token=%s',
                                    )) ?>
                                </td>
                                <td class="zg-col-status">
                                    <?= $this->render('TaskManager:grid/status_select', array(
                                        'options'        => $subtask_statuses,
                                        'option_classes' => $subtask_status_classes,
                                        'current'        => $sub['status'],
                                        'current_label'  => $sub['status_label'],
                                        'current_class'  => $sub['status_class'],
                                        'editable'       => $can_move,
                                        'url_template'   => $this->url->href('StatusChangeController', 'subtask', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'task_id' => $row['id'], 'subtask_id' => $sub['id'])).'&status=%s&csrf_token=%s',
                                    )) ?>
                                </td>
                                <td class="zg-col-tags"><span class="zg-muted">&mdash;</span></td>
                                <td class="zg-col-date"><span class="zg-muted">&mdash;</span></td>
                                <td class="zg-col-date"><span class="zg-muted">&mdash;</span></td>
                                <td class="zg-col-duration">
                                    <?php if ($sub['time_estimated'] > 0): ?>
                                        <?= t('%sh', $sub['time_estimated']) ?>
                                    <?php else: ?>
                                        <span class="zg-muted">&mdash;</span>
                                    <?php endif ?>
                                </td>
                                <td class="zg-col-priority"><span class="zg-muted">&mdash;</span></td>
                                <td class="zg-col-progress"><span class="zg-muted">&mdash;</span></td>
                            </tr>
                        <?php endforeach ?>
                    <?php endforeach ?>
                </tbody>
            <?php endforeach ?>

            <?php if (empty($rows)): ?>
                <tbody><tr><td colspan="10" class="zg-empty"><?= t('No tasks found.') ?></td></tr></tbody>
            <?php endif ?>
        </table>
    </div>

    <?php endif ?>

    <?php if ($mode === 'list'): ?>
        <?= $this->render('TaskManager:grid/pager', array(
            'page'       => $page,
            'controller' => 'TaskGridController',
            'base'       => array(
                'plugin'     => 'TaskManager',
                'project_id' => $project['id'],
                'mode'       => $mode,
                'view'       => $view,
                'group_by'   => $group_by,
                'search'     => $search,
                'order'      => $order,
                'direction'  => $direction,
            ),
        )) ?>
    <?php endif ?>

</div>

<?php /* The filter panel lives here because this is where its options exist:
         the project's columns, its people, its priority range, its tags. */ ?>
<?= $this->render('TaskManager:grid/filter_panel', array(
    'project'           => $project,
    'columns'           => $columns,
    'column_classes'    => $column_classes,
    'filter_users'      => isset($filter_users) ? $filter_users : array(),
    'filter_priorities' => isset($filter_priorities) ? $filter_priorities : array(),
    'filter_tags'       => isset($filter_tags) ? $filter_tags : array(),
)) ?>
