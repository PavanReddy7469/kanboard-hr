<?php

namespace Kanboard\Plugin\TaskManager\Helper;

use Kanboard\Core\Base;

/**
 * Read-only access to dashboard figures from templates, so the workspace
 * hook can render without a controller of its own.
 *
 * Every method here mirrors one on DashboardModel. The project dashboard has
 * its own controller and does not need them; Home is rendered through a
 * template hook, which has no controller to load data in.
 *
 * @package Kanboard\Plugin\TaskManager\Helper
 */
class DashboardHelper extends Base
{
    /**
     * @param  integer $userId
     * @return array
     */
    public function getPortfolio($userId)
    {
        return $this->dashboardModel->getPortfolio($userId);
    }

    /**
     * The projects a user can see: the scope of every figure on Home.
     *
     * @param  integer $userId
     * @return array
     */
    public function getPortfolioProjectIds($userId)
    {
        return $this->dashboardModel->getPortfolioProjectIds($userId);
    }

    /**
     * @param  integer|array $projects
     * @return array
     */
    public function getTrend($projects)
    {
        return $this->dashboardModel->getTrend($projects);
    }

    /**
     * @param  integer|array $projects
     * @return array
     */
    public function getStatusDistribution($projects)
    {
        return $this->dashboardModel->getStatusDistribution($projects);
    }

    /**
     * @param  integer|array $projects
     * @return array
     */
    public function getWorkload($projects)
    {
        return $this->dashboardModel->getWorkload($projects);
    }

    /**
     * @param  integer|array $projects
     * @return array
     */
    public function getNeedsAttention($projects)
    {
        return $this->dashboardModel->getNeedsAttention($projects);
    }

    /**
     * @param  integer|array $projects
     * @return array
     */
    public function getKpis($projects)
    {
        return $this->dashboardModel->getKpis($projects);
    }

    /**
     * @param  integer|array $projects
     * @return array
     */
    public function getAllTasks($projects)
    {
        return $this->dashboardModel->getAllTasks($projects);
    }

    /**
     * @param  integer $projectId
     * @return array
     */
    public function getProjectTaskCards($projectId)
    {
        return $this->dashboardModel->getProjectTaskCards($projectId);
    }
}
