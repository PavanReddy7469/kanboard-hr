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
    /**
     * Task rows for the Tasks tab, built by the same code the project grid
     * uses so the two pages cannot disagree about a task.
     *
     * The tab used to build its own rows from a query that selected neither
     * the column title nor the owner's name nor the subtask counts. Status
     * fell back to the literal word "Open" on every row whatever column the
     * task was in, the owner could not be shown at all, and Subtasks was a
     * column of dashes.
     *
     * @param  array  $projectIds
     * @param  string $view
     * @param  string $search
     * @param  string $order
     * @param  string $direction
     * @return array
     */
    public function getTaskRowsForProjects(array $projectIds, $view = 'all', $search = '', $order = 'id', $direction = 'ASC')
    {
        return $this->gridModel->getTaskRowsForProjects($projectIds, $view, $search, $order, $direction);
    }

    /**
     * What the filter panel offers when it is looking at several projects.
     *
     * Status is the union of the scoped projects' column names rather than
     * their ids: two boards can both have a "Review" column with different
     * ids, and the panel filters on the name. Keying the list by name also
     * collapses the duplicates, so the same status is offered once.
     *
     * @param  array $projectIds
     * @return array
     */
    public function getFilterOptions(array $projectIds)
    {
        $projectIds = array_values(array_unique(array_map('intval', $projectIds)));

        if (empty($projectIds)) {
            return array(
                'columns' => array(), 'column_classes' => array(),
                'users' => array(), 'priorities' => array(), 'tags' => array(),
            );
        }

        $columns       = array();
        $columnClasses = array();

        /* One query for every board in scope rather than one per project:
           this is the landing page for the whole team and the scope is their
           entire portfolio. */
        $columnRows = $this->db
            ->table(\Kanboard\Model\ColumnModel::TABLE)
            ->columns('title')
            ->in('project_id', $projectIds)
            ->findAll();

        foreach ($columnRows as $row) {
            $columns[$row['title']]       = $row['title'];
            $columnClasses[$row['title']] = $this->helper->taskTree->getStatusClass($row['title']);
        }

        /* One scale wide enough for every project in scope. Somebody looking
           at three boards should not have to know which of them goes up to
           P10 before they can filter on it. */
        $priorities    = array();
        $priorityFloor = null;
        $priorityRoof  = null;

        $projectRows = $this->db
            ->table(\Kanboard\Model\ProjectModel::TABLE)
            ->columns('priority_start', 'priority_end')
            ->in('id', $projectIds)
            ->findAll();

        foreach ($projectRows as $row) {
            $start = (int) $row['priority_start'];
            $end   = (int) $row['priority_end'];

            $priorityFloor = $priorityFloor === null ? $start : min($priorityFloor, $start);
            $priorityRoof  = $priorityRoof === null ? $end : max($priorityRoof, $end);
        }

        if ($priorityFloor !== null && $priorityRoof >= $priorityFloor) {
            foreach (range(max(1, $priorityFloor), $priorityRoof) as $priority) {
                $priorities[$priority] = 'P'.$priority;
            }
        }

        /* Assignable users stay a per-project call: membership can come
           through a group as well as directly, and the role model is what
           knows that. */
        $users = array();

        foreach ($projectIds as $projectId) {
            foreach ($this->projectUserRoleModel->getAssignableUsersList($projectId, false) as $userId => $userName) {
                $users[$userId] = $userName;
            }
        }

        /* Columns get natural order, which puts "Sprint 2" before
           "Sprint 10". Names get plain string order: strnatcasecmp ignores
           the space in a name, so "P Sudheep" compares as "PSudheep" and
           lands after "Pavan Reddy". */
        asort($columns, SORT_NATURAL | SORT_FLAG_CASE);
        asort($users, SORT_STRING | SORT_FLAG_CASE);

        return array(
            'columns'        => $columns,
            'column_classes' => $columnClasses,
            'users'          => $users,
            'priorities'     => $priorities,
            'tags'           => $this->tagModel->getAllByProjectIds($projectIds),
        );
    }
}
