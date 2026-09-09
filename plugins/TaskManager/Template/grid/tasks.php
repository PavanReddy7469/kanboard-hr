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

    <div class="zg-toolbar" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; margin-bottom: 8px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div class="dropdown zg-modepick">
                <a href="#" class="dropdown-menu dropdown-menu-link-icon" aria-label="<?= t('Change view') ?>" style="padding: 6px 12px; background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 8px; font-weight: 700; color: #1e293b; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa fa-<?= $mode === 'gantt' ? 'tasks' : ($mode === 'kanban' ? 'columns' : 'list') ?>" style="color: #6366f1;"></i> 
                    <span><?= $this->text->e($modes[$mode]) ?></span> 
                    <i class="fa fa-caret-down" style="color: #94a3b8; font-size: 0.75rem;"></i>
                </a>
                <ul style="border-radius: 10px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15); border: 1px solid #e2e8f0; padding: 6px;">
                    <?php foreach ($modes as $key => $label): ?>
                        <li>
                            <?= $this->url->link(
                                    ($key === 'gantt' ? '<i class="fa fa-tasks"></i> ' : ($key === 'kanban' ? '<i class="fa fa-columns"></i> ' : '<i class="fa fa-list"></i> ')).$label,
                                    'TaskGridController',
                                    'show',
                                    array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'mode' => $key, 'view' => $view, 'group_by' => $group_by),
                                    false,
                                    $key === $mode ? 'is-current' : ''
                                ) ?>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        </div>

        <div class="zg-toolbar-right" style="display: flex; align-items: center; gap: 10px;">
            <?php if ($can_create): ?>
                <span class="zg-btn-primary"><?= $this->modal->large('plus', t('Add Task'), 'TaskCreationController', 'show', array('project_id' => $project['id'])) ?></span>
            <?php endif ?>
        </div>
    </div>

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

            <?php foreach ($groups as $groupLabel => $groupRows): ?>
                <tbody>
                    <?php if ($groupLabel !== ''): ?>
                        <tr class="zg-grouprow">
                            <td colspan="10">
                                <span class="zg-grouplabel"><?= $this->text->e($groupLabel) ?></span>
                                <span class="zg-groupcount"><?= count($groupRows) ?></span>
                            </td>
                        </tr>
                    <?php endif ?>

                    <?php foreach ($groupRows as $row): ?>
                        <tr class="<?= $row['is_overdue'] ? 'is-overdue' : '' ?> <?= $row['is_active'] ? '' : 'is-closed' ?>">
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
                                <?php if ($row['owner'] !== ''): ?>
                                    <span class="zg-owner"><?= $this->text->e($row['owner']) ?></span>
                                <?php else: ?>
                                    <span class="zg-muted"><?= t('Unassigned') ?></span>
                                <?php endif ?>
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
                                <?php if ($row['priority'] > 0): ?>
                                    <span class="zg-priority <?= $this->taskTree->getPriorityClass($row['priority']) ?>"><?= $this->taskTree->getPriorityLabel($row['priority']) ?></span>
                                <?php else: ?>
                                    <span class="zg-muted">&mdash;</span>
                                <?php endif ?>
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
                            <tr class="zg-subrow" data-zg-subtree-of="<?= (int) $row['id'] ?>" hidden>
                                <td class="zg-col-code">
                                    <span class="zg-subbranch <?= $subIndex === count($row['subtasks']) - 1 ? 'is-last' : '' ?>" aria-hidden="true"></span>
                                </td>
                                <td class="zg-col-title">
                                    <span class="zg-subtitle"><?= $this->text->e($sub['title']) ?></span>
                                </td>
                                <td class="zg-col-owner">
                                    <?php if ($sub['owner'] !== ''): ?>
                                        <span class="zg-owner"><?= $this->text->e($sub['owner']) ?></span>
                                    <?php else: ?>
                                        <span class="zg-muted"><?= t('Unassigned') ?></span>
                                    <?php endif ?>
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
