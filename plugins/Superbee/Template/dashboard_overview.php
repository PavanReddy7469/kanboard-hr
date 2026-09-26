<?php
/* Overrides app/Template/dashboard/overview.php.
   SUPERBEE Home Project Tasks Grid:
   Renders cards for each project with 4-digit codes, modern task rows,
   interactive right-side drawer triggers, and cohesive SUPERBEE design.
   Fully responsive to the 'View by Project' filter.
*/

$portfolio = $this->dashboard->getPortfolio($user['id']);
$allProjectIds = $this->dashboard->getPortfolioProjectIds($user['id']);

$rawProjectId = isset($_GET['project_id']) ? $_GET['project_id'] : (isset($_REQUEST['project_id']) ? $_REQUEST['project_id'] : 0);
$selectedProjectId = (int) $rawProjectId;

if ($selectedProjectId > 0 && in_array($selectedProjectId, array_map('intval', $allProjectIds))) {
    $targetProjectIds = array($selectedProjectId);
} else {
    $targetProjectIds = $allProjectIds;
}

$projData = array();
if (! empty($portfolio['projects'])) {
    foreach ($portfolio['projects'] as $p) {
        $projData[$p['id']] = array(
            'code' => ! empty($p['identifier']) ? strtoupper(substr($p['identifier'], 0, 4)) : sprintf('P%03d', $p['id']),
            'name' => $p['name'],
        );
    }
}
?>

<?= $this->hook->render('template:dashboard:show:before-filter-box', array('user' => $user)) ?>
<?= $this->hook->render('template:dashboard:show:after-filter-box', array('user' => $user)) ?>

<?php if (empty($targetProjectIds)): ?>
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 32px 24px; text-align: center; margin-top: 16px;">
        <i class="fa fa-folder-open-o" style="font-size: 2rem; color: #94a3b8; display: block; margin-bottom: 8px;"></i>
        <h4 style="margin: 0 0 4px; font-weight: 800; color: #0f172a;"><?= t('No active projects found') ?></h4>
        <p style="color: #64748b; font-size: 0.88rem; margin: 0;"><?= t('You are not assigned to any active projects yet.') ?></p>
    </div>
<?php else: ?>
    <div class="sb-dash-grid sb-home-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(480px, 1fr)); gap: 20px; margin-top: 16px;">
        <?php foreach ($targetProjectIds as $pId): ?>
            <?php
                $pId = (int) $pId;
                $pCode = isset($projData[$pId]['code']) ? $projData[$pId]['code'] : sprintf('P%03d', $pId);
                $pName = isset($projData[$pId]['name']) ? $projData[$pId]['name'] : ('Project #'.$pId);

                // Fetch open tasks with subtasks count via dashboard helper
                $tasksList = $this->dashboard->getProjectTaskCards($pId);
                $totalTasks = count($tasksList);
            ?>
            <section class="sb-card" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04); overflow: hidden; display: flex; flex-direction: column;">
                
                <!-- Card Header -->
                <div style="padding: 16px 20px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-family: monospace; font-size: 0.8rem; font-weight: 800; color: #4338ca; background: #e0e7ff; padding: 3px 8px; border-radius: 6px;">
                            <?= $pCode ?>
                        </span>
                        <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $pId)) ?>" style="font-size: 1rem; font-weight: 800; color: #0f172a; text-decoration: none; letter-spacing: -0.01em;">
                            <?= $this->text->e($pName) ?>
                        </a>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 0.78rem; font-weight: 700; color: #64748b; background: #ffffff; border: 1px solid #cbd5e1; padding: 2px 9px; border-radius: 12px;">
                            <?= $totalTasks ?> <?= $totalTasks === 1 ? t('task') : t('tasks') ?>
                        </span>
                        <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $pId)) ?>" class="btn btn-sm" style="font-size: 0.75rem; padding: 4px 10px; background: #ffffff; color: #4338ca; border: 1px solid #c7d2fe; border-radius: 6px; font-weight: 700; text-decoration: none;">
                            <?= t('Open Tasks') ?> <i class="fa fa-arrow-right" style="font-size: 0.7rem; margin-left: 2px;"></i>
                        </a>
                    </div>
                </div>

                <!-- Task List Items -->
                <div style="display: flex; flex-direction: column;">
                    <?php if (empty($tasksList)): ?>
                        <div style="padding: 24px; text-align: center; color: #94a3b8; font-size: 0.88rem;">
                            <i class="fa fa-check-circle-o" style="font-size: 1.4rem; color: #10b981; display: block; margin-bottom: 6px;"></i>
                            <?= t('No open tasks in this project.') ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tasksList as $task): ?>
                            <?php
                                // Task Code
                                $tCode = ! empty($task['reference']) ? strtoupper($task['reference']) : sprintf('T%03d', $task['id']);

                                // Priority
                                $pNum = ! empty($task['priority']) ? (int)$task['priority'] : 0;
                                $pLabel = $pNum > 0 ? 'P'.$pNum : '';

                                // Status
                                $statusTitle = ! empty($task['column_name']) ? $task['column_name'] : 'Open';
                                $statusClass = $this->taskTree->getStatusClass($statusTitle);

                                // Due Date
                                $isOverdue = ! empty($task['date_due']) && $task['date_due'] < time() && $task['is_active'] == 1;

                                // Subtasks counts
                                $subTotal = isset($task['nb_subtasks']) ? (int)$task['nb_subtasks'] : 0;
                                $subDone  = isset($task['nb_completed_subtasks']) ? (int)$task['nb_completed_subtasks'] : 0;

                                // Assignee
                                $assigneeName = ! empty($task['name']) ? $task['name'] : (! empty($task['username']) ? $task['username'] : t('Unassigned'));
                            ?>
                            <div class="sb-hover-row" style="padding: 12px 18px; border-bottom: 1px solid #f1f5f9; display: flex; flex-direction: column; gap: 8px;">
                                
                                <!-- Top Row: Code & Title -->
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                                    <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                                        <span style="font-family: monospace; font-size: 0.78rem; font-weight: 800; color: #4338ca; background: #e0e7ff; padding: 2px 7px; border-radius: 6px; flex-shrink: 0;">
                                            #<?= $tCode ?>
                                        </span>
                                        <a href="<?= $this->url->href('TaskViewController', 'show', array('task_id' => $task['id'], 'project_id' => $pId)) ?>"
                                           data-zp-open="<?= $this->url->href('TaskPanelController', 'show', array('plugin' => 'TaskManager', 'task_id' => $task['id'], 'project_id' => $pId)) ?>"
                                           style="font-size: 0.9rem; font-weight: 600; color: #1e293b; text-decoration: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer;">
                                            <?= $this->text->e($task['title']) ?>
                                        </a>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                                        <a href="<?= $this->url->href('TaskViewController', 'show', array('task_id' => $task['id'], 'project_id' => $pId)) ?>"
                                           data-zp-open="<?= $this->url->href('TaskPanelController', 'show', array('plugin' => 'TaskManager', 'task_id' => $task['id'], 'project_id' => $pId)) ?>"
                                           style="color: #94a3b8; padding: 3px 6px; border-radius: 4px; text-decoration: none; cursor: pointer;" title="<?= t('View in Drawer') ?>">
                                            <i class="fa fa-chevron-right" style="font-size: 0.75rem;"></i>
                                        </a>
                                    </div>
                                </div>

                                <!-- Bottom Row: Assignee, Status, Due Date, Subtasks, Priority -->
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 0.75rem;">
                                    <!-- Assignee -->
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <span style="width: 20px; height: 20px; border-radius: 50%; background: #4f46e5; color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 0.66rem; font-weight: 800;">
                                            <?= strtoupper(substr($assigneeName, 0, 1)) ?>
                                        </span>
                                        <span style="font-weight: 600; color: #475569;"><?= $this->text->e($assigneeName) ?></span>
                                    </div>

                                    <!-- Status Badge -->
                                    <span class="zg-status <?= $statusClass ?>" style="font-size: 0.72rem; font-weight: 700; padding: 2px 7px; border-radius: 6px;">
                                        <?= $this->text->e($statusTitle) ?>
                                    </span>

                                    <!-- Due Date -->
                                    <?php if (! empty($task['date_due'])): ?>
                                        <span style="color: <?= $isOverdue ? '#dc2626' : '#64748b' ?>; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa fa-calendar-o" style="font-size: 0.7rem;"></i> <?= date('M d, Y', $task['date_due']) ?>
                                        </span>
                                    <?php endif ?>

                                    <!-- Subtasks -->
                                    <?php if ($subTotal > 0): ?>
                                        <span style="color: #64748b; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; background: #f8fafc; padding: 1px 6px; border-radius: 4px; border: 1px solid #e2e8f0;">
                                            <i class="fa fa-tasks" style="color: #6366f1; font-size: 0.68rem;"></i> <?= $subDone ?>/<?= $subTotal ?> <?= t('done') ?>
                                        </span>
                                    <?php endif ?>

                                    <!-- Priority Badge -->
                                    <?php if ($pNum > 0): ?>
                                        <span style="font-weight: 800; font-size: 0.72rem; padding: 1px 6px; border-radius: 4px; background: <?= $pNum === 1 ? '#fee2e2' : ($pNum === 2 ? '#fef3c7' : '#f1f5f9') ?>; color: <?= $pNum === 1 ? '#b91c1c' : ($pNum === 2 ? '#b45309' : '#475569') ?>;">
                                            <?= $pLabel ?>
                                        </span>
                                    <?php endif ?>
                                </div>
                            </div>
                        <?php endforeach ?>
                    <?php endif ?>
                </div>
            </section>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?= $this->hook->render('template:dashboard:show', array('user' => $user)) ?>
