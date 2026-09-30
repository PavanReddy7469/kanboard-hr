<?php
/* Overrides app/Template/dashboard/tasks.php
   SUPERBEE Tasks View: tasks grouped by project, with the same filter panel
   the project grid uses.

   The rows come from GridModel::getTaskRowsForProjects(), which is the
   project grid's own row builder. This page used to build its own rows from
   a query that selected neither the column title nor the owner's name nor
   the subtask counts, so every task read "Open" whatever column it was
   really in, the owner could not be shown at all, and Subtasks was a column
   of dashes. One builder now feeds both pages.
*/

$uid = isset($user['id']) ? (int) $user['id'] : (int) $this->user->getId();
$portfolio = $this->dashboard->getPortfolio($uid);
$allProjectsList = array();
if (! empty($portfolio['projects'])) {
    foreach ($portfolio['projects'] as $p) {
        $allProjectsList[$p['id']] = $p['name'];
    }
}

$selectedProjectId = isset($_GET['project_id']) ? (int) $_GET['project_id'] : 0;

/* 'search', not a name of our own: the filter panel writes its query into
   that parameter on whatever page it is open on, and the same string is what
   the project grid reads. Plain words still work - the row builder only
   treats it as the search language when it contains a colon. */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$projectScope = $selectedProjectId > 0 && isset($allProjectsList[$selectedProjectId])
    ? array($selectedProjectId)
    : array_keys($allProjectsList);

$rows = empty($projectScope)
    ? array()
    : $this->dashboard->getTaskRowsForProjects($projectScope, 'all', $search, 'id', 'ASC');

$filterOptions = $this->dashboard->getFilterOptions($projectScope);

$projectsGrouped = array();

foreach ($rows as $row) {
    $pid = (int) $row['project_id'];

    if (! isset($projectsGrouped[$pid])) {
        $projectsGrouped[$pid] = array(
            'id'    => $pid,
            'name'  => $row['project'] !== '' ? $row['project'] : (isset($allProjectsList[$pid]) ? $allProjectsList[$pid] : t('General Project')),
            'tasks' => array(),
        );
    }

    $projectsGrouped[$pid]['tasks'][] = $row;
}

$totalVisibleTasks = count($rows);
$isFiltered = $selectedProjectId > 0 || $search !== '';
?>

<?php /* The panel is a sibling of the page, not of the button: it is found
         with a document-wide query and its click is delegated from the
         document, so it works from anywhere on the page. */ ?>
<?= $this->render('TaskManager:grid/filter_panel', array(
    'columns'           => $filterOptions['columns'],
    'column_classes'    => $filterOptions['column_classes'],
    'filter_users'      => $filterOptions['users'],
    'filter_priorities' => $filterOptions['priorities'],
    'filter_tags'       => $filterOptions['tags'],
)) ?>

<div class="sb-tasks-workspace" style="max-width: 1300px; margin: 0 auto; padding-bottom: 40px;">
    <!-- Top Header Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; background: #ffffff; padding: 18px 24px; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 1px 4px rgba(0,0,0,0.03); gap: 16px; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <span style="display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 12px; background: #e0e7ff; color: #4338ca;">
                <i class="fa fa-check-square-o" style="font-size: 1.25rem;"></i>
            </span>
            <div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <h2 style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.01em;">
                        <?= t('Tasks') ?>
                    </h2>
                    <span style="background: #f1f5f9; color: #475569; font-size: 0.8rem; font-weight: 700; padding: 2px 10px; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <?= $totalVisibleTasks ?> <?= t('tasks') ?>
                    </span>
                </div>
                <p style="font-size: 0.84rem; color: #64748b; margin: 3px 0 0;">
                    <?= t('Your assigned tasks organized and categorized across projects.') ?>
                </p>
            </div>
        </div>

        <!-- Controls: Project picker and the filter panel -->
        <div style="display: flex; align-items: center; gap: 12px;">
            <?php /* A plain GET form rather than an inline onchange: the app sends
                     Content-Security-Policy default-src 'self', which blocks every
                     inline handler, so an onchange attribute here silently does
                     nothing. data-zg-submit is bound from grid.js. */ ?>
            <form method="get" action="<?= $this->url->dir() ?>" style="display: flex; align-items: center; gap: 8px; margin: 0;">
                <input type="hidden" name="controller" value="DashboardController">
                <input type="hidden" name="action" value="tasks">
                <?php if ($search !== ''): ?>
                    <?php /* Changing project keeps the filter, the same way it
                             travels across views on the project grid. */ ?>
                    <input type="hidden" name="search" value="<?= $this->text->e($search) ?>">
                <?php endif ?>
                <label for="sb-tasks-proj-filter" style="font-size: 0.84rem; font-weight: 600; color: #475569; margin: 0;">
                    <i class="fa fa-folder-open-o" style="color: #6366f1;"></i> <?= t('Project:') ?>
                </label>
                <div style="position: relative;">
                    <select id="sb-tasks-proj-filter" name="project_id" data-zg-submit style="padding: 8px 32px 8px 12px; font-size: 0.86rem; font-weight: 600; color: #1e293b; background-color: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; cursor: pointer; outline: none; appearance: none; -webkit-appearance: none;">
                        <option value="0" <?= $selectedProjectId === 0 ? 'selected="selected"' : '' ?>>
                            <?= t('★ All Projects') ?>
                        </option>
                        <?php foreach ($allProjectsList as $pId => $pName): ?>
                            <option value="<?= $pId ?>" <?= $selectedProjectId === $pId ? 'selected="selected"' : '' ?>>
                                <?= $this->text->e($pName) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <i class="fa fa-caret-down" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #64748b; font-size: 0.8rem;"></i>
                </div>
            </form>

            <a href="#" class="zf-open zg-filter-trigger" data-zf-open title="<?= t('Filter') ?>">
                <i class="fa fa-filter" aria-hidden="true"></i>
                <span><?= t('Filter') ?></span>
                <?php if ($search !== ''): ?>
                    <span class="zf-open-dot" title="<?= t('A filter is applied') ?>"></span>
                <?php endif ?>
            </a>

            <?php if ($isFiltered): ?>
                <a href="<?= $this->url->href('DashboardController', 'tasks') ?>" class="btn btn-sm" style="font-size: 0.8rem; padding: 7px 12px; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none; font-weight: 600;">
                    <i class="fa fa-times"></i> <?= t('Reset') ?>
                </a>
            <?php endif ?>
        </div>
    </div>

    <!-- Project Sections -->
    <?php if (empty($projectsGrouped)): ?>
        <div style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; padding: 50px 20px; text-align: center;">
            <div style="width: 56px; height: 56px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px;">
                <i class="fa fa-check-circle-o" style="font-size: 1.6rem;"></i>
            </div>
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0 0 6px;"><?= t('No Tasks Found') ?></h3>
            <p style="font-size: 0.86rem; color: #64748b; margin: 0;"><?= t('There are no tasks matching your current filter selection.') ?></p>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <?php foreach ($projectsGrouped as $pGroup): ?>
                <div class="sb-project-tasks-card" style="background: #ffffff; border-radius: 14px; border: 1px solid #e2e8f0; box-shadow: 0 1px 4px rgba(0,0,0,0.03); overflow: hidden;">
                    <!-- Project Card Header -->
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 8px; background: #dbeafe; color: #1d4ed8; font-size: 0.85rem;">
                                <i class="fa fa-folder"></i>
                            </span>
                            <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $pGroup['id'])) ?>" style="font-size: 0.98rem; font-weight: 700; color: #0f172a; text-decoration: none;" title="<?= t('View project tasks') ?>">
                                <?= $this->text->e($pGroup['name']) ?>
                            </a>
                            <span style="background: #e2e8f0; color: #475569; font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 10px;">
                                <?= count($pGroup['tasks']) ?> <?= t('tasks') ?>
                            </span>
                        </div>
                        <div>
                            <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $pGroup['id'])) ?>" style="font-size: 0.82rem; font-weight: 600; color: #4f46e5; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 5px 10px; border-radius: 6px; background: #eef2ff;">
                                <?= t('Open Project') ?> <i class="fa fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Tasks Table -->
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; text-align: left;">
                            <thead>
                                <tr style="background: #ffffff; border-bottom: 1px solid #f1f5f9; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em;">
                                    <th style="padding: 10px 18px; width: 90px;"><?= t('ID') ?></th>
                                    <th style="padding: 10px 18px;"><?= t('Task Title') ?></th>
                                    <th style="padding: 10px 14px; width: 150px;"><?= t('Owner') ?></th>
                                    <th style="padding: 10px 14px; width: 110px;"><?= t('Status') ?></th>
                                    <th style="padding: 10px 14px; width: 80px;"><?= t('Priority') ?></th>
                                    <th style="padding: 10px 14px; width: 150px;"><?= t('Subtasks') ?></th>
                                    <th style="padding: 10px 14px; width: 120px;"><?= t('Start Date') ?></th>
                                    <th style="padding: 10px 14px; width: 130px;"><?= t('Due Date') ?></th>
                                    <th style="padding: 10px 18px; width: 110px; text-align: right;"><?= t('Action') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pGroup['tasks'] as $t): ?>
                                    <?php
                                        $subtasks = $t['subtasks'];
                                        $subTotal = count($subtasks);
                                        $subDone  = 0;

                                        foreach ($subtasks as $subtask) {
                                            if ((int) $subtask['status'] === 2) {
                                                $subDone++;
                                            }
                                        }

                                        $subPct = $subTotal > 0 ? (int) round(($subDone / $subTotal) * 100) : 0;
                                        $priorityLabel = $this->taskTree->getPriorityLabel($t['priority']);
                                    ?>
                                    <tr class="sb-hover-row" style="border-bottom: 1px solid #f8fafc;<?= $t['is_active'] ? '' : ' opacity: 0.72;' ?>">
                                        <!-- ID -->
                                        <td style="padding: 12px 18px;">
                                            <span style="font-family: monospace; font-size: 0.84rem; font-weight: 700; color: #4338ca; background: #e0e7ff; padding: 2px 7px; border-radius: 6px; white-space: nowrap;">
                                                #<?= $this->text->e($t['code']) ?>
                                            </span>
                                        </td>

                                        <!-- Title -->
                                        <td style="padding: 12px 18px;">
                                            <a href="<?= $this->url->href('TaskViewController', 'show', array('task_id' => $t['id'], 'project_id' => $pGroup['id'])) ?>"
                                               data-zp-open="<?= $this->url->href('TaskPanelController', 'show', array('plugin' => 'TaskManager', 'task_id' => $t['id'], 'project_id' => $pGroup['id'])) ?>"
                                               style="font-size: 0.9rem; font-weight: 600; color: #1e293b; text-decoration: none; cursor: pointer;<?= $t['is_active'] ? '' : ' text-decoration: line-through; color: #94a3b8;' ?>">
                                                <?= $this->text->e($t['title']) ?>
                                            </a>
                                            <?php if (! empty($t['tags'])): ?>
                                                <span style="display: inline-flex; gap: 4px; margin-left: 6px;">
                                                    <?php foreach ($t['tags'] as $tagName): ?>
                                                        <span style="font-size: 0.7rem; font-weight: 600; color: #475569; background: #f1f5f9; border-radius: 10px; padding: 1px 7px;"><?= $this->text->e($tagName) ?></span>
                                                    <?php endforeach ?>
                                                </span>
                                            <?php endif ?>
                                        </td>

                                        <!-- Owner -->
                                        <td style="padding: 12px 14px;">
                                            <?php if ($t['owner'] !== ''): ?>
                                                <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.82rem; font-weight: 600; color: #334155;">
                                                    <i class="fa fa-user-circle-o" style="color: #94a3b8;"></i>
                                                    <?= $this->text->e($t['owner']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="font-size: 0.78rem; color: #94a3b8;"><?= t('Unassigned') ?></span>
                                            <?php endif ?>
                                        </td>

                                        <!-- Status -->
                                        <td style="padding: 12px 14px;">
                                            <?php if ($t['status'] !== ''): ?>
                                                <span class="zg-status <?= $this->text->e($t['status_class']) ?>" style="display: inline-block; font-size: 0.75rem; font-weight: 700; padding: 3px 9px; border-radius: 6px;">
                                                    <?= $this->text->e($t['status']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="font-size: 0.78rem; color: #94a3b8;">&mdash;</span>
                                            <?php endif ?>
                                        </td>

                                        <!-- Priority -->
                                        <td style="padding: 12px 14px;">
                                            <?php if ($priorityLabel !== ''): ?>
                                                <span class="priority-badge <?= $this->taskTree->getPriorityClass($t['priority']) ?>" style="display: inline-block; font-size: 0.75rem; font-weight: 800; padding: 2px 7px; border-radius: 6px;">
                                                    <?= $priorityLabel ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="font-size: 0.78rem; color: #94a3b8;">&mdash;</span>
                                            <?php endif ?>
                                        </td>

                                        <!-- Subtasks -->
                                        <td style="padding: 12px 14px;">
                                            <?php if ($subTotal > 0): ?>
                                                <div style="display: flex; align-items: center; gap: 8px;" title="<?= t('%d of %d subtasks done', $subDone, $subTotal) ?>">
                                                    <div style="flex: 1; height: 6px; background: #f1f5f9; border-radius: 3px; overflow: hidden; min-width: 60px;">
                                                        <div style="width: <?= $subPct ?>%; height: 100%; background: <?= $subPct == 100 ? '#10b981' : '#6366f1' ?>; border-radius: 3px;"></div>
                                                    </div>
                                                    <span style="font-size: 0.74rem; font-weight: 600; color: #64748b; white-space: nowrap;">
                                                        <?= $subDone ?>/<?= $subTotal ?>
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <span style="font-size: 0.78rem; color: #94a3b8;">&mdash;</span>
                                            <?php endif ?>
                                        </td>

                                        <!-- Start Date -->
                                        <td style="padding: 12px 14px;">
                                            <?php if (! empty($t['start_date'])): ?>
                                                <span style="font-size: 0.8rem; font-weight: 600; color: #475569;">
                                                    <?= date('M d, Y', $t['start_date']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="font-size: 0.78rem; color: #94a3b8;">&mdash;</span>
                                            <?php endif ?>
                                        </td>

                                        <!-- Due Date -->
                                        <td style="padding: 12px 14px;">
                                            <?php if (! empty($t['date_due'])): ?>
                                                <span style="font-size: 0.8rem; font-weight: 600; color: <?= $t['is_overdue'] ? '#b91c1c' : '#475569' ?>; display: inline-flex; align-items: center; gap: 4px;">
                                                    <i class="fa fa-calendar-o" style="font-size: 0.75rem;"></i> <?= date('M d, Y', $t['date_due']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="font-size: 0.78rem; color: #94a3b8;">&mdash;</span>
                                            <?php endif ?>
                                        </td>

                                        <!-- Action -->
                                        <td style="padding: 12px 18px; text-align: right;">
                                            <a href="<?= $this->url->href('TaskViewController', 'show', array('task_id' => $t['id'], 'project_id' => $pGroup['id'])) ?>"
                                               data-zp-open="<?= $this->url->href('TaskPanelController', 'show', array('plugin' => 'TaskManager', 'task_id' => $t['id'], 'project_id' => $pGroup['id'])) ?>"
                                               class="btn btn-sm" style="font-size: 0.78rem; padding: 4px 10px; background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none; font-weight: 600; cursor: pointer;">
                                                <i class="fa fa-eye"></i> <?= t('View') ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>
