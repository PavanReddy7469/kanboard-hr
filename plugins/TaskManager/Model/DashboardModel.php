<?php

namespace Kanboard\Plugin\TaskManager\Model;

use Kanboard\Core\Base;
use Kanboard\Model\ColumnModel;
use Kanboard\Model\TaskModel;

/**
 * Every number the project dashboard puts on screen.
 *
 * All of it is derived from tasks, subtasks, dependencies and time entries at
 * request time. Nothing here depends on Kanboard's nightly cronjob having run,
 * because on a small or freshly-installed instance project_daily_column_stats
 * is empty and a dashboard that renders blank is worse than no dashboard.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class DashboardModel extends Base
{
    const TREND_DAYS     = 30;
    const DUE_SOON_DAYS  = 7;
    const ATTENTION_MAX  = 12;

    /**
     * "3" or "1,2,5" => task rows, for the life of one request.
     *
     * @var array
     */
    protected $taskCache = array();

    /**
     * Every method below takes either a single project id or a list of them,
     * so the same figures serve one project's dashboard and the portfolio-wide
     * cards on Home without a second implementation.
     *
     * @param  integer|array $projects
     * @return array  a sorted, de-duplicated list of positive integers
     */
    protected function toProjectIds($projects)
    {
        $ids = array();

        foreach (is_array($projects) ? $projects : array($projects) as $id) {
            $id = (int) $id;

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        $ids = array_values($ids);
        sort($ids);

        return $ids;
    }

    /**
     * The projects a user can see, which is the scope of every figure on Home.
     *
     * @param  integer $userId
     * @return array
     */
    public function getPortfolioProjectIds($userId)
    {
        if ($this->userSession->isAdmin()) {
            return array_map('intval', $this->projectModel->getAllIds());
        }

        return array_map('intval', $this->projectPermissionModel->getActiveProjectIds($userId));
    }

    /**
     * The tile row across the top.
     *
     * @param  integer|array $projects
     * @return array
     */
    public function getKpis($projects)
    {
        $tasks     = $this->getAllTasks($projects);
        $today     = (int) strtotime('today');
        $dueCutoff = (int) strtotime('+'.self::DUE_SOON_DAYS.' day', $today);
        $since     = (int) strtotime('-30 day', $today);

        $kpis = array(
            'total'       => count($tasks),
            'open'        => 0,
            'closed'      => 0,
            'overdue'     => 0,
            'due_soon'    => 0,
            'completed'   => 0,
            'unassigned'  => 0,
            'in_progress' => 0,
        );

        foreach ($tasks as $task) {
            if ($task['is_active'] == 1) {
                $kpis['open']++;

                if (empty($task['owner_id'])) {
                    $kpis['unassigned']++;
                }

                if (! empty($task['column_title'])) {
                    $cTitle = strtolower($task['column_title']);
                    if (strpos($cTitle, 'progress') !== false || strpos($cTitle, 'wip') !== false) {
                        $kpis['in_progress']++;
                    }
                }

                if (! empty($task['date_due'])) {
                    if ($task['date_due'] < $today) {
                        $kpis['overdue']++;
                    } elseif ($task['date_due'] < $dueCutoff) {
                        $kpis['due_soon']++;
                    }
                }
            } else {
                $kpis['closed']++;

                if (! empty($task['date_completed']) && $task['date_completed'] >= $since) {
                    $kpis['completed']++;
                }
            }
        }

        $kpis['progress'] = $kpis['total'] > 0 ? (int) round($kpis['closed'] / $kpis['total'] * 100) : 0;

        return $kpis;
    }

    /**
     * One row per status: how many tasks sit in it, and its share.
     *
     * Grouped by column title rather than column id. Across a portfolio the
     * same "Work in progress" exists once per project, and half a dozen
     * identical slices would say nothing; within a single project the two are
     * the same thing.
     *
     * @param  integer|array $projects
     * @return array
     */
    public function getStatusDistribution($projects)
    {
        $ids = $this->toProjectIds($projects);

        if (empty($ids)) {
            return array('columns' => array(), 'total' => 0);
        }

        $counts   = array();
        $titleFor = $this->getColumnTitles($ids);

        foreach ($titleFor as $title) {
            if (! isset($counts[$title])) {
                $counts[$title] = array(
                    'title'   => $title,
                    'class'   => $this->helper->taskTree->getStatusClass($title),
                    'total'   => 0,
                    'percent' => 0,
                );
            }
        }

        $total = 0;

        foreach ($this->getAllTasks($ids) as $task) {
            $columnId = (int) $task['column_id'];

            if (isset($titleFor[$columnId])) {
                $counts[$titleFor[$columnId]]['total']++;
                $total++;
            }
        }

        if ($total > 0) {
            foreach ($counts as $title => $row) {
                $counts[$title]['percent'] = round($row['total'] / $total * 100, 1);
            }
        }

        return array('columns' => array_values($counts), 'total' => $total);
    }

    /**
     * column_id => title, in board order, across every project in scope.
     *
     * @param  array $projectIds
     * @return array
     */
    protected function getColumnTitles(array $projectIds)
    {
        $titles = array();

        $rows = $this->db->table(ColumnModel::TABLE)
            ->columns('id', 'title', 'position')
            ->in('project_id', $projectIds)
            ->asc('position')
            ->asc('id')
            ->findAll();

        foreach ($rows as $row) {
            $titles[(int) $row['id']] = $row['title'];
        }

        return $titles;
    }

    /**
     * Open work per assignee, with the overdue slice called out.
     *
     * @param  integer|array $projects
     * @return array
     */
    public function getWorkload($projects)
    {
        $ids     = $this->toProjectIds($projects);
        $users   = $this->userModel->getActiveUsersList();
        $today   = (int) strtotime('today');
        $columns = $this->getColumnTitles($ids);
        $rows    = array();
        $peak    = 0;

        foreach ($this->getAllTasks($ids) as $task) {
            if ($task['is_active'] != 1) {
                continue;
            }

            // Exclude tasks sitting in Closed or Done columns
            if (isset($columns[$task['column_id']])) {
                $colName = mb_strtolower(trim($columns[$task['column_id']]), 'UTF-8');
                if (in_array($colName, array('closed', 'done', 'completed', 'resolved'))) {
                    continue;
                }
            }

            $userId = (int) $task['owner_id'];
            $name   = $userId > 0 && isset($users[$userId]) ? $users[$userId] : t('Unassigned');

            if (! isset($rows[$userId])) {
                $rows[$userId] = array(
                    'user_id' => $userId,
                    'name'    => $name,
                    'open'    => 0,
                    'overdue' => 0,
                    'hours'   => 0.0,
                );
            }

            $rows[$userId]['open']++;

            if (! empty($task['date_due']) && $task['date_due'] < $today) {
                $rows[$userId]['overdue']++;
            }

            $peak = max($peak, $rows[$userId]['open']);
        }

        foreach ($this->getHoursByUser($ids) as $userId => $hours) {
            if (isset($rows[$userId])) {
                $rows[$userId]['hours'] = $hours;
            }
        }

        // Unassigned last, everyone else by load.
        uasort($rows, function ($a, $b) {
            if ($a['user_id'] === 0 || $b['user_id'] === 0) {
                return $a['user_id'] === 0 ? 1 : -1;
            }

            return $b['open'] - $a['open'];
        });

        return array('rows' => array_values($rows), 'peak' => max(1, $peak));
    }

    /**
     * The tasks a lead should look at first, and why.
     *
     * A task can collect several reasons; they are ranked so the worst rise to
     * the top rather than the list being ordered by task id.
     *
     * @param  integer|array $projects
     * @return array
     */
    public function getNeedsAttention($projects)
    {
        $ids = $this->toProjectIds($projects);

        if (empty($ids)) {
            return array();
        }

        $today     = (int) strtotime('today');
        $dueCutoff = (int) strtotime('+'.self::DUE_SOON_DAYS.' day', $today);
        $users     = $this->userModel->getActiveUsersList();
        $columns   = $this->getColumnTitles($ids);
        // Private projects are hidden by getList() unless asked for; access is
        // already scoped by whoever passed the ids in.
        $names     = $this->projectModel->getList(false, false);
        $blocked   = $this->getBlockedTaskIds($ids);
        $items     = array();

        foreach ($this->getAllTasks($ids) as $task) {
            if ($task['is_active'] != 1) {
                continue;
            }

            $reasons = array();
            $weight  = 0;

            if (! empty($task['date_due']) && $task['date_due'] < $today) {
                $days      = max(1, (int) floor(($today - $task['date_due']) / 86400));
                $reasons[] = array('label' => t('%d day(s) overdue', $days), 'tone' => 'bad');
                $weight   += 100 + $days;
            } elseif (! empty($task['date_due']) && $task['date_due'] < $dueCutoff) {
                $reasons[] = array('label' => t('Due soon'), 'tone' => 'warn');
                $weight   += 40;
            }

            if (isset($blocked[$task['id']])) {
                $reasons[] = array('label' => t('Blocked by %d open task(s)', $blocked[$task['id']]), 'tone' => 'bad');
                $weight   += 60;
            }

            if (empty($task['owner_id'])) {
                $reasons[] = array('label' => t('Unassigned'), 'tone' => 'warn');
                $weight   += 30;
            }

            if ((int) $task['priority'] === 1) {
                $reasons[] = array('label' => t('P1'), 'tone' => 'bad');
                $weight   += 20;
            }

            if (empty($task['date_due'])) {
                $reasons[] = array('label' => t('No due date'), 'tone' => 'mute');
                $weight   += 5;
            }

            if (empty($reasons)) {
                continue;
            }

            $projectId = (int) $task['project_id'];

            $items[] = array(
                'id'         => (int) $task['id'],
                'project_id' => $projectId,
                'project'    => isset($names[$projectId]) ? $names[$projectId] : '',
                'title'      => $task['title'],
                'assignee'   => empty($task['owner_id']) ? '' : (isset($users[$task['owner_id']]) ? $users[$task['owner_id']] : ''),
                'column'     => isset($columns[$task['column_id']]) ? $columns[$task['column_id']] : '',
                'priority'   => (int) $task['priority'],
                'date_due'   => (int) $task['date_due'],
                'reasons'    => $reasons,
                'weight'     => $weight,
            );
        }

        usort($items, function ($a, $b) {
            return $b['weight'] - $a['weight'];
        });

        return array_slice($items, 0, self::ATTENTION_MAX);
    }

    /**
     * Open-task count for each of the last N days, plus what closed on each.
     *
     * Reconstructed from date_creation and date_completed so it is correct on
     * day one rather than waiting for the nightly stats job to accumulate.
     *
     * @param  integer|array $projects
     * @return array
     */
    public function getTrend($projects)
    {
        $tasks  = $this->getAllTasks($projects);
        $today  = (int) strtotime('today');
        $days   = self::TREND_DAYS;
        $starts = array();

        for ($i = 0; $i < $days; $i++) {
            $starts[$i] = (int) strtotime('-'.($days - 1 - $i).' day', $today);
        }

        $windowStart = $starts[0];
        $windowEnd   = $starts[$days - 1] + 86399;

        /* Bucket each task once instead of testing every task on every day:
           the old version was 30 x (number of tasks) comparisons per load. */
        $created   = array_fill(0, $days, 0);
        $completed = array_fill(0, $days, 0);
        $closedOn  = array_fill(0, $days, 0);

        foreach ($tasks as $task) {
            $creation = (int) $task['date_creation'];

            if ($creation > $windowEnd) {
                continue;
            }

            $created[$this->dayIndex($creation, $windowStart, $days)]++;

            $done = (int) $task['date_completed'];

            if ($done > 0 && $done <= $windowEnd) {
                $index = $this->dayIndex($done, $windowStart, $days);
                $completed[$index]++;

                /* Only count it as "closed on this day" when it actually
                   closed inside the window, not merely before it. */
                if ($done >= $windowStart) {
                    $closedOn[$index]++;
                }
            }
        }

        $points        = array();
        $createdSoFar  = 0;
        $doneSoFar     = 0;
        $peak          = 1;

        for ($i = 0; $i < $days; $i++) {
            $createdSoFar += $created[$i];
            $doneSoFar    += $completed[$i];
            $open = max(0, $createdSoFar - $doneSoFar);
            $peak = max($peak, $open);

            $points[] = array('day' => $starts[$i], 'open' => $open, 'closed' => $closedOn[$i]);
        }

        return array('points' => $points, 'peak' => $peak);
    }

    /**
     * Which day bucket a timestamp falls in. Anything older than the window
     * lands in the first bucket, so its effect is carried into the running
     * totals rather than dropped.
     *
     * @param  integer $timestamp
     * @param  integer $windowStart
     * @param  integer $days
     * @return integer
     */
    protected function dayIndex($timestamp, $windowStart, $days)
    {
        if ($timestamp < $windowStart) {
            return 0;
        }

        return min($days - 1, (int) floor(($timestamp - $windowStart) / 86400));
    }

    /**
     * One row per project the user can see, for the workspace overview strip
     * on Kanboard's own dashboard.
     *
     * @param  integer $userId
     * @return array
     */
    public function getPortfolio($userId)
    {
        $projectIds = $this->getPortfolioProjectIds($userId);

        if (empty($projectIds)) {
            return array('projects' => array(), 'totals' => array('open' => 0, 'overdue' => 0, 'due_soon' => 0, 'projects' => 0));
        }

        $today     = (int) strtotime('today');
        $dueCutoff = (int) strtotime('+'.self::DUE_SOON_DAYS.' day', $today);
        $projects  = $this->db->table(ProjectModel::TABLE)->columns('id', 'name', 'identifier')->in('id', $projectIds)->findAll();
        $rows      = array();
        $totals    = array('open' => 0, 'overdue' => 0, 'due_soon' => 0, 'projects' => count($projectIds));

        foreach ($projects as $proj) {
            $projectId = (int) $proj['id'];
            $rows[$projectId] = array(
                'id'         => $projectId,
                'name'       => $proj['name'],
                'identifier' => ! empty($proj['identifier']) ? $proj['identifier'] : '',
                'total'      => 0,
                'open'       => 0,
                'closed'     => 0,
                'overdue'    => 0,
                'due_soon'   => 0,
                'progress'   => 0,
            );
        }

        $tasks = $this->db->table(TaskModel::TABLE)
            ->columns('project_id', 'is_active', 'date_due')
            ->in('project_id', $projectIds)
            ->findAll();

        foreach ($tasks as $task) {
            $projectId = (int) $task['project_id'];

            if (! isset($rows[$projectId])) {
                continue;
            }

            $rows[$projectId]['total']++;

            if ($task['is_active'] != 1) {
                $rows[$projectId]['closed']++;
                continue;
            }

            $rows[$projectId]['open']++;
            $totals['open']++;

            if (empty($task['date_due'])) {
                continue;
            }

            if ($task['date_due'] < $today) {
                $rows[$projectId]['overdue']++;
                $totals['overdue']++;
            } elseif ($task['date_due'] < $dueCutoff) {
                $rows[$projectId]['due_soon']++;
                $totals['due_soon']++;
            }
        }

        foreach ($rows as $projectId => $row) {
            if ($row['total'] > 0) {
                $rows[$projectId]['progress'] = (int) round($row['closed'] / $row['total'] * 100);
            }
        }

        uasort($rows, function ($a, $b) {
            if ($a['overdue'] !== $b['overdue']) {
                return $b['overdue'] - $a['overdue'];
            }

            return $b['open'] - $a['open'];
        });

        return array('projects' => array_values($rows), 'totals' => $totals);
    }

    /**
     * task_id => number of open predecessors.
     *
     * @param  integer|array $projects
     * @return array
     */
    protected function getBlockedTaskIds($projects)
    {
        $blocked = array();
        $ids     = $this->toProjectIds($projects);

        if (empty($ids)) {
            return $blocked;
        }

        $rows = $this->db->table(DependencyModel::TABLE)
            ->columns(DependencyModel::TABLE.'.task_id', DependencyModel::TABLE.'.depends_on_id')
            ->in(DependencyModel::TABLE.'.project_id', $ids)
            ->findAll();

        if (empty($rows)) {
            return $blocked;
        }

        $states = $this->db->table(TaskModel::TABLE)
            ->in('project_id', $ids)
            ->columns('id', 'is_active')
            ->findAll();

        $active = array();

        foreach ($states as $state) {
            $active[(int) $state['id']] = (int) $state['is_active'] === 1;
        }

        foreach ($rows as $row) {
            $predecessor = (int) $row['depends_on_id'];

            if (! empty($active[$predecessor])) {
                $taskId = (int) $row['task_id'];
                $blocked[$taskId] = isset($blocked[$taskId]) ? $blocked[$taskId] + 1 : 1;
            }
        }

        return $blocked;
    }

    /**
     * @param  integer|array $projects
     * @return float
     */
    protected function getHoursThisWeek($projects)
    {
        $ids = $this->toProjectIds($projects);

        if (empty($ids)) {
            return 0.0;
        }

        $weekStart = $this->timesheetModel->getWeekStart();
        $weekEnd   = (int) strtotime('+7 day', $weekStart);

        $total = $this->db->table(TimeEntryModel::TABLE)
            ->in('project_id', $ids)
            ->gte('work_date', $weekStart)
            ->lt('work_date', $weekEnd)
            ->sum('hours');

        return round((float) $total, 1);
    }

    /**
     * user_id => hours logged this week.
     *
     * @param  integer|array $projects
     * @return array
     */
    protected function getHoursByUser($projects)
    {
        $ids   = $this->toProjectIds($projects);
        $hours = array();

        if (empty($ids)) {
            return $hours;
        }

        $weekStart = $this->timesheetModel->getWeekStart();
        $weekEnd   = (int) strtotime('+7 day', $weekStart);

        $rows = $this->db->table(TimeEntryModel::TABLE)
            ->columns('user_id', 'SUM(hours) AS total')
            ->in('project_id', $ids)
            ->gte('work_date', $weekStart)
            ->lt('work_date', $weekEnd)
            ->groupBy('user_id')
            ->findAll();

        foreach ($rows as $row) {
            $hours[(int) $row['user_id']] = round((float) $row['total'], 1);
        }

        return $hours;
    }

    /**
     * Open and closed tasks in one fetch — every metric on this page reads the
     * same list rather than issuing its own COUNT query.
     *
     * @param  integer|array $projects
     * @return array
     */
    public function getAllTasks($projects)
    {
        $ids = $this->toProjectIds($projects);

        if (empty($ids)) {
            return array();
        }

        // Every section of the dashboard reads this same list, and each one
        // used to issue its own identical query. Memoised per request, keyed
        // by the set of projects asked for.
        $key = implode(',', $ids);

        if (isset($this->taskCache[$key])) {
            return $this->taskCache[$key];
        }

        $this->taskCache[$key] = $this->db->table(TaskModel::TABLE)
            ->columns('id', 'title', 'project_id', 'column_id', 'owner_id', 'is_active', 'priority', 'reference',
                      'date_due', 'date_creation', 'date_completed', 'time_estimated', 'time_spent')
            ->in('project_id', $ids)
            ->findAll();

        return $this->taskCache[$key];
    }

    /**
     * Get enriched open task cards for home dashboard project sections
     *
     * @param  integer $projectId
     * @return array
     */
    public function getProjectTaskCards($projectId)
    {
        $projectId = (int) $projectId;
        if ($projectId <= 0) {
            return array();
        }

        $tasks = $this->db->table(TaskModel::TABLE)
            ->columns(
                TaskModel::TABLE.'.id',
                TaskModel::TABLE.'.title',
                TaskModel::TABLE.'.reference',
                TaskModel::TABLE.'.priority',
                TaskModel::TABLE.'.date_due',
                TaskModel::TABLE.'.is_active',
                TaskModel::TABLE.'.column_id',
                TaskModel::TABLE.'.owner_id',
                ColumnModel::TABLE.'.title as column_name',
                \Kanboard\Model\UserModel::TABLE.'.name as name',
                \Kanboard\Model\UserModel::TABLE.'.username as username'
            )
            ->join(ColumnModel::TABLE, 'id', 'column_id', TaskModel::TABLE)
            ->left(\Kanboard\Model\UserModel::TABLE, 'users', 'id', TaskModel::TABLE, 'owner_id')
            ->eq(TaskModel::TABLE.'.project_id', $projectId)
            ->eq(TaskModel::TABLE.'.is_active', 1)
            ->asc(TaskModel::TABLE.'.position')
            ->findAll();

        foreach ($tasks as &$task) {
            $task['nb_subtasks'] = (int) $this->db->table(\Kanboard\Model\SubtaskModel::TABLE)->eq('task_id', $task['id'])->count();
            $task['nb_completed_subtasks'] = (int) $this->db->table(\Kanboard\Model\SubtaskModel::TABLE)->eq('task_id', $task['id'])->eq('status', 2)->count();
        }

        return $tasks;
    }
}
