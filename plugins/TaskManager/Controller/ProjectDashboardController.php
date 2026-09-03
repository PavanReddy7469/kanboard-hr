<?php

namespace Kanboard\Plugin\TaskManager\Controller;

use Kanboard\Controller\BaseController;

/**
 * The project dashboard: KPIs, distribution, workload, what needs attention,
 * a 30-day trend and the activity feed.
 *
 * @package Kanboard\Plugin\TaskManager\Controller
 */
class ProjectDashboardController extends BaseController
{
    public function show()
    {
        $project = $this->getProject();

        $this->response->html($this->helper->layout->project('TaskManager:dashboard/show', array(
            'project'      => $project,
            'title'        => $project['name'].' - '.t('Dashboard'),
            'kpis'         => $this->dashboardModel->getKpis($project['id']),
            'distribution' => $this->dashboardModel->getStatusDistribution($project['id']),
            'workload'     => $this->dashboardModel->getWorkload($project['id']),
            'attention'    => $this->dashboardModel->getNeedsAttention($project['id']),
            'trend'        => $this->dashboardModel->getTrend($project['id']),
            'events'       => $this->helper->projectActivity->getProjectEvents($project['id'], 12),
            'is_lead'      => $this->helper->taskTree->canManageTasks($project['id']),
        ), 'TaskManager:project/no_sidebar'));
    }
}
