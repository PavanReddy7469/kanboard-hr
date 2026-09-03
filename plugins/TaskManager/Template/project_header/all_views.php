<?php
/* Replaces app/Template/project_header/views.php via setTemplateOverride.
   Project Tabs:
   1. Tasks (Default Landing)
   2. Task Lists
   3. Reports (Deliverables verification)
   4. Calendar & Gantt (via hooks)
   5. Overflow: Board, Task Tree, Analytics, Workflow Rules.
*/
$pid    = $project['id'];
$search = isset($filters['search']) ? $filters['search'] : '';
?>
<ul class="views zg-tabs">

    <?= $this->hook->render('template:project-header:view-switcher-before-project-overview', array('project' => $project, 'filters' => $filters)) ?>

    <li class="<?= $this->app->checkMenuSelection('TaskGridController') || $this->app->checkMenuSelection('ProjectOverviewController') ? 'active' : '' ?>">
        <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $pid)) ?>">
            <i class="fa fa-fw fa-check-square-o" aria-hidden="true"></i><?= t('Tasks') ?>
        </a>
    </li>

    <li class="<?= $this->app->checkMenuSelection('TaskGroupController') ? 'active' : '' ?>">
        <a href="<?= $this->url->href('TaskGroupController', 'index', array('plugin' => 'TaskManager', 'project_id' => $pid)) ?>">
            <i class="fa fa-fw fa-list-ul" aria-hidden="true"></i><?= t('Task Lists') ?>
        </a>
    </li>

    <li class="<?= $this->app->checkMenuSelection('DeliverableController') ? 'active' : '' ?>">
        <a href="<?= $this->url->href('DeliverableController', 'index', array('plugin' => 'TaskManager', 'project_id' => $pid)) ?>">
            <i class="fa fa-fw fa-check-circle-o" aria-hidden="true"></i><?= t('Reports') ?>
        </a>
    </li>

    <?php /* Calendar and Gantt come from their own plugins through this hook. */ ?>
    <?= $this->hook->render('template:project-header:view-switcher', array('project' => $project, 'filters' => $filters)) ?>

    <li class="zg-tab-more">
        <div class="dropdown">
            <a href="#" class="dropdown-menu dropdown-menu-link-icon" aria-label="<?= t('More views') ?>"><strong><i class="fa fa-ellipsis-h" aria-hidden="true"></i></strong></a>
            <ul>
                <?php /* BoardViewController now redirects to this grid, so a "Board"
                         entry pointing at it just bounced the person back to the tab
                         they were already on. The grid's own Kanban mode is the board. */ ?>
                <li><?= $this->url->link(t('Board'), 'TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $pid, 'mode' => 'kanban')) ?></li>
                <li><?= $this->url->link(t('Dashboard'), 'ProjectDashboardController', 'show', array('plugin' => 'TaskManager', 'project_id' => $pid)) ?></li>
                <li><?= $this->url->link(t('Task Tree'), 'TaskManagerController', 'show', array('plugin' => 'TaskManager', 'project_id' => $pid)) ?></li>
                <li><?= $this->url->link(t('Analytics'), 'AnalyticController', 'taskDistribution', array('project_id' => $pid)) ?></li>
                <?php if ($this->user->hasProjectAccess('ActionController', 'index', $pid)): ?>
                    <li><?= $this->url->link(t('Workflow Rules'), 'ActionController', 'index', array('project_id' => $pid)) ?></li>
                <?php endif ?>
            </ul>
        </div>
    </li>
</ul>
