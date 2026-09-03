<?php
/* The Home dashboard strip with top-right Project Filter:
   - When "All Projects" is selected: renders executive portfolio analytics across all projects.
   - When a specific project is selected: scopes KPIs, trend charts, status distributions,
     workload, and attention items to that specific project.
*/

$allProjectIds = $this->dashboard->getPortfolioProjectIds($user['id']);
$portfolio     = $this->dashboard->getPortfolio($user['id']);

$rawProjectId  = isset($_GET['project_id']) ? $_GET['project_id'] : (isset($_REQUEST['project_id']) ? $_REQUEST['project_id'] : 0);
$selectedProjectId = (int) $rawProjectId;

$scopedProjectName = '';

if ($selectedProjectId > 0 && in_array($selectedProjectId, array_map('intval', $allProjectIds))) {
    $scopedProjectIds    = array($selectedProjectId);
    $scopedKpis          = $this->dashboard->getKpis($scopedProjectIds);
    $activeProjectsCount = 1;
    $openTasksCount      = $scopedKpis['open'];
    $overdueTasksCount   = $scopedKpis['overdue'];
    $dueSoonTasksCount   = $scopedKpis['due_soon'];
    $footProjects        = t('Selected project');
    $footOpen            = t('In this project');
    $displayProjectsList = array_filter($portfolio['projects'], function($p) use ($selectedProjectId) {
        return (int)$p['id'] === $selectedProjectId;
    });

    foreach ($portfolio['projects'] as $p) {
        if ((int)$p['id'] === $selectedProjectId) {
            $scopedProjectName = $p['name'];
            break;
        }
    }
} else {
    $selectedProjectId   = 0;
    $scopedProjectIds    = $allProjectIds;
    $activeProjectsCount = isset($portfolio['totals']['projects']) ? $portfolio['totals']['projects'] : count($allProjectIds);
    $openTasksCount      = isset($portfolio['totals']['open']) ? $portfolio['totals']['open'] : 0;
    $overdueTasksCount   = isset($portfolio['totals']['overdue']) ? $portfolio['totals']['overdue'] : 0;
    $dueSoonTasksCount   = isset($portfolio['totals']['due_soon']) ? $portfolio['totals']['due_soon'] : 0;
    $footProjects        = t('You have access to');
    $footOpen            = t('Across all projects');
    $displayProjectsList = $portfolio['projects'];
}
?>

<?php if (! empty($portfolio['projects'])): ?>
    <div class="sb-dash sb-workspace">
        <!-- Top Controls / Project Filter Bar -->
        <div class="sb-dash-header-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: white; padding: 14px 20px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 10px; background: <?= $selectedProjectId > 0 ? '#dbeafe' : '#e0e7ff' ?>; color: <?= $selectedProjectId > 0 ? '#1d4ed8' : '#4338ca' ?>;">
                    <i class="fa <?= $selectedProjectId > 0 ? 'fa-folder-open' : 'fa-th-large' ?>" style="font-size: 1.1rem;"></i>
                </span>
                <div>
                    <span style="font-size: 1rem; font-weight: 700; color: #0f172a; display: block;">
                        <?= $selectedProjectId > 0 ? $this->text->e($scopedProjectName) : t('Organization Portfolio View') ?>
                    </span>
                    <span style="font-size: 0.8rem; color: #64748b;">
                        <?= $selectedProjectId > 0 ? t('Project dashboard view: analytics scoped specifically to this project') : t('Showing aggregated portfolio analytics across whole organization') ?>
                    </span>
                </div>
            </div>

            <!-- Top Right Project Filter Dropdown -->
            <form id="sb-dashboard-filter-form" method="get" action="<?= $this->url->dir() ?>" style="margin: 0; display: inline-flex; align-items: center; gap: 10px;">
                <input type="hidden" name="controller" value="DashboardController">
                <input type="hidden" name="action" value="show">
                
                <label for="sb-dashboard-project-filter" style="font-size: 0.86rem; font-weight: 700; color: #334155; display: inline-flex; align-items: center; gap: 6px; margin: 0; line-height: 1; cursor: pointer;">
                    <i class="fa fa-filter" style="color: #4f46e5; font-size: 0.95rem; display: inline-flex; align-items: center; justify-content: center;"></i>
                    <span><?= t('View by Project:') ?></span>
                </label>
                
                <div style="position: relative; display: inline-flex; align-items: center;">
                    <select id="sb-dashboard-project-filter" name="project_id" data-zg-submit style="padding: 7px 32px 7px 12px; font-size: 0.88rem; font-weight: 600; color: #1e293b; background-color: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; cursor: pointer; outline: none; appearance: none; -webkit-appearance: none; box-shadow: 0 1px 2px rgba(0,0,0,0.04); height: 36px; line-height: normal;">
                        <option value="0" <?= $selectedProjectId === 0 ? 'selected="selected"' : '' ?>>
                            <?= t('★ All Projects (Whole Portfolio)') ?>
                        </option>
                        <?php foreach ($portfolio['projects'] as $proj): ?>
                            <option value="<?= $proj['id'] ?>" <?= $selectedProjectId === (int)$proj['id'] ? 'selected="selected"' : '' ?>>
                                <?= $this->text->e($proj['name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                    <i class="fa fa-caret-down" style="position: absolute; right: 11px; pointer-events: none; color: #64748b; font-size: 0.85rem;"></i>
                </div>
                
                <?php if ($selectedProjectId > 0): ?>
                    <a href="<?= $this->url->href('DashboardController', 'show') ?>" class="btn btn-sm" style="font-size: 0.8rem; padding: 7px 12px; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; height: 36px; box-sizing: border-box;" title="<?= t('Reset to all projects') ?>">
                        <i class="fa fa-times"></i> <?= t('Reset') ?>
                    </a>
                <?php endif ?>
            </form>
        </div>

        <!-- KPI Cards -->
        <div class="sb-kpis">
            <div class="sb-kpi">
                <span class="sb-kpi-label"><?= $selectedProjectId > 0 ? t('Project Status') : t('Active projects') ?></span>
                <span class="sb-kpi-value"><?= $activeProjectsCount ?></span>
                <span class="sb-kpi-foot"><?= $footProjects ?></span>
            </div>
            <div class="sb-kpi">
                <span class="sb-kpi-label"><?= t('Open tasks') ?></span>
                <span class="sb-kpi-value"><?= $openTasksCount ?></span>
                <span class="sb-kpi-foot"><?= $footOpen ?></span>
            </div>
            <div class="sb-kpi <?= $overdueTasksCount > 0 ? 'is-bad' : '' ?>">
                <span class="sb-kpi-label"><?= t('Overdue tasks') ?></span>
                <span class="sb-kpi-value"><?= $overdueTasksCount ?></span>
                <span class="sb-kpi-foot"><?= $overdueTasksCount > 0 ? t('Needs a decision') : t('Nothing late') ?></span>
            </div>
            <div class="sb-kpi <?= $dueSoonTasksCount > 0 ? 'is-warn' : '' ?>">
                <span class="sb-kpi-label"><?= t('Due this week') ?></span>
                <span class="sb-kpi-value"><?= $dueSoonTasksCount ?></span>
                <span class="sb-kpi-foot"><?= t('Next 7 days') ?></span>
            </div>
        </div>

        <!-- Projects at a glance -->
        <div class="sb-card sb-portfolio">
            <div class="sb-card-head">
                <h3><?= $selectedProjectId > 0 ? t('Project Progress') : t('Projects at a glance') ?></h3>
                <span class="sb-card-note"><?= $selectedProjectId > 0 ? t('Completion status') : t('Most at risk first') ?></span>
            </div>
            <div class="sb-card-body">
                <ul class="sb-portfolio-list">
                    <?php foreach ($displayProjectsList as $row): ?>
                        <li>
                            <a class="sb-portfolio-name" href="<?= $this->url->href('ProjectOverviewController', 'show', array('project_id' => $row['id'])) ?>">
                                <?= $this->text->e($row['name']) ?>
                            </a>
                            <span class="sb-progress">
                                <span class="sb-progress-fill" style="width: <?= $row['progress'] ?>%"></span>
                            </span>
                            <span class="sb-portfolio-figures">
                                <em><?= t('%d open', $row['open']) ?></em>
                                <?php if ($row['overdue'] > 0): ?>
                                    <em class="is-bad"><?= t('%d late', $row['overdue']) ?></em>
                                <?php endif ?>
                                <strong><?= $row['progress'] ?>%</strong>
                            </span>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        </div>

        <!-- Analytics Grid (Trend, Distribution, Workload, Needs Attention) -->
        <div class="sb-dash-grid">
            <?= $this->render('TaskManager:dashboard/trend', array(
                'trend' => $this->dashboard->getTrend($scopedProjectIds),
            )) ?>

            <?= $this->render('TaskManager:dashboard/distribution', array(
                'distribution' => $this->dashboard->getStatusDistribution($scopedProjectIds),
            )) ?>

            <?= $this->render('TaskManager:dashboard/workload', array(
                'workload' => $this->dashboard->getWorkload($scopedProjectIds),
            )) ?>

            <?= $this->render('TaskManager:dashboard/attention', array(
                'attention'    => $this->dashboard->getNeedsAttention($scopedProjectIds),
                'show_project' => ($selectedProjectId === 0),
            )) ?>
        </div>
    </div>
<?php endif ?>
