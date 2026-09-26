<?php

namespace Kanboard\Plugin\TaskManager\Model;

use Kanboard\Core\Base;
use Kanboard\Model\ProjectModel as CoreProjectModel;
use Kanboard\Model\SubtaskModel;
use Kanboard\Model\TaskModel;

/**
 * Rows for the two Zoho-shaped grids: All Projects, and a project's Tasks.
 *
 * Presentation only. Every column here is a different arrangement of data the
 * platform already stores — no new concepts, no new writes.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class GridModel extends Base
{
    const FILTER_ALL      = 'all';
    const FILTER_OPEN     = 'open';
    const FILTER_CLOSED   = 'closed';
    const FILTER_OVERDUE  = 'overdue';
    const FILTER_MINE     = 'mine';

    const PAGE_SIZE = 50;

    const MODE_LIST   = 'list';
    const MODE_GANTT  = 'gantt';
    const MODE_KANBAN = 'kanban';

    /**
     * Saved-view options on the Projects grid.
     *
     * @return array
     */
    public function getProjectViews()
    {
        return array(
            self::FILTER_ALL    => t('All Projects'),
            self::FILTER_OPEN   => t('Active Projects'),
            self::FILTER_CLOSED => t('Archived Projects'),
        );
    }

    /**
     * Saved-view options on the Tasks grid.
     *
     * @return array
     */
    public function getTaskViews()
    {
        return array(
            self::FILTER_OPEN    => t('All Open'),
            self::FILTER_CLOSED  => t('All Closed'),
            self::FILTER_ALL     => t('All Tasks'),
            self::FILTER_OVERDUE => t('Overdue'),
            self::FILTER_MINE    => t('My Tasks'),
        );
    }

    /**
     * The three ways the Tasks page can draw itself.
     *
     * @return array
     */
    public function getViewModes()
    {
        return array(
            self::MODE_LIST   => t('List'),
            self::MODE_GANTT  => t('Gantt'),
            self::MODE_KANBAN => t('Kanban'),
        );
    }

    /**
     * Zoom levels on the Gantt mode, mapped onto the scale names the Gantt
     * plugin's own setGanttScale() already understands.
     *
     * @return array
     */
    public function getGanttScales()
    {
        return array(
            'year'    => t('Years'),
            'quarter' => t('Quarters'),
            'month'   => t('Months'),
            'week'    => t('Weeks'),
            'day'     => t('Days'),
        );
    }

    /**
     * Gantt and Kanban read through Kanboard's own query lexer, which knows
     * open/closed and nothing about the list-only views, so those two modes
     * offer the three views that translate cleanly.
     *
     * @param  string $mode
     * @return array
     */
    public function getViewsForMode($mode)
    {
        $views = $this->getTaskViews();

        if ($mode === self::MODE_LIST) {
            return $views;
        }

        return array(
            self::FILTER_OPEN   => $views[self::FILTER_OPEN],
            self::FILTER_CLOSED => $views[self::FILTER_CLOSED],
            self::FILTER_ALL    => $views[self::FILTER_ALL],
        );
    }

    /**
     * The saved view as a Kanboard search string, for the formatters that
     * expect one.
     *
     * @param  string $view
     * @return string
     */
    public function getSearchQueryForView($view)
    {
        if ($view === self::FILTER_CLOSED) {
            return 'status:closed';
        }

        if ($view === self::FILTER_ALL) {
            return '';
        }

        return 'status:open';
    }

    /**
     * Group-by options on the Tasks grid.
     *
     * @return array
     */
    public function getGroupings()
    {
        return array(
            'none'     => t('None'),
            'status'   => t('Status'),
            'owner'    => t('Owner'),
            'priority' => t('Priority'),
        );
    }

    /**
     * One row per project the user can see.
     *
     * @param  integer $userId
     * @param  string  $view
     * @param  string  $search
     * @return array
     */
    public function getProjectRows($userId, $view = self::FILTER_ALL, $search = '')
    {
        if ($this->userModel->isAdmin($userId)) {
            $projectIds = $this->projectModel->getAllIds();
        } else {
            $projectIds = $this->projectPermissionModel->getProjectIds($userId);
        }

        if (empty($projectIds)) {
            return array();
        }

        $query = $this->db->table(CoreProjectModel::TABLE)
            ->columns('id', 'name', 'identifier', 'is_active', 'owner_id', 'start_date', 'end_date', 'description')
            ->in('id', $projectIds);

        if ($view === self::FILTER_OPEN) {
            $query->eq('is_active', 1);
        } elseif ($view === self::FILTER_CLOSED) {
            $query->eq('is_active', 0);
        }

        if ($search !== '') {
            $query->ilike('name', '%'.$search.'%');
        }

        $projects   = $query->asc('name')->findAll();
        $users      = $this->userModel->getActiveUsersList();
        $taskStats  = $this->getTaskStatsByProject($projectIds);
        $milestones = $this->getMilestoneCounts($projectIds);
        $taskDates  = $this->getProjectTaskDates($projectIds);
        $rows       = array();

        foreach ($projects as $project) {
            $id    = (int) $project['id'];
            $stats = isset($taskStats[$id]) ? $taskStats[$id] : array('total' => 0, 'open' => 0, 'closed' => 0);

            $statusMeta = $this->projectMetadataModel->get($id, 'project_status', '');
            if ($statusMeta === 'closed' || (int) $project['is_active'] === 0) {
                $statusKey   = 'closed';
                $statusLabel = t('Closed');
                $statusClass = 'status-closed';
            } elseif ($statusMeta === 'on_hold') {
                $statusKey   = 'on_hold';
                $statusLabel = t('On Hold');
                $statusClass = 'status-rev';
            } else {
                $statusKey   = 'active';
                $statusLabel = t('Active');
                $statusClass = 'status-wip';
            }

            $startDate = isset($taskDates[$id]['min_start']) && $taskDates[$id]['min_start'] !== null ? date('Y-m-d', $taskDates[$id]['min_start']) : $project['start_date'];
            $endDate   = isset($taskDates[$id]['max_end']) && $taskDates[$id]['max_end'] !== null ? date('Y-m-d', $taskDates[$id]['max_end']) : $project['end_date'];

            $rows[] = array(
                'id'           => $id,
                'code'         => $this->getProjectCode($project),
                'name'         => $project['name'],
                'is_active'    => (int) $project['is_active'],
                'status_key'   => $statusKey,
                'status_label' => $statusLabel,
                'status_class' => $statusClass,
                'owner'        => isset($users[$project['owner_id']]) ? $users[$project['owner_id']] : '',
                'owner_id'     => (int) $project['owner_id'],
                'total'        => $stats['total'],
                'open'         => $stats['open'],
                'closed'       => $stats['closed'],
                'progress'     => $stats['total'] > 0 ? (int) round($stats['closed'] / $stats['total'] * 100) : 0,
                'milestones'   => isset($milestones[$id]) ? $milestones[$id] : 0,
                'start_date'   => $startDate,
                'end_date'     => $endDate,
                'days_left'    => $this->getDaysLeft($endDate),
            );
        }

        return $rows;
    }

    /**
     * Compute the earliest task start date and latest task due date across projects.
     *
     * @param  array $projectIds
     * @return array
     */
    public function getProjectTaskDates(array $projectIds)
    {
        if (empty($projectIds)) {
            return array();
        }

        $tasks = $this->db->table(TaskModel::TABLE)
            ->columns('project_id', 'date_started', 'date_due', 'date_creation')
            ->in('project_id', $projectIds)
            ->findAll();

        $dates = array();
        foreach ($tasks as $t) {
            $pid = (int) $t['project_id'];
            if (! isset($dates[$pid])) {
                $dates[$pid] = array('min_start' => null, 'max_end' => null);
            }

            $tStart = ! empty($t['date_started']) ? (int) $t['date_started'] : (! empty($t['date_creation']) ? (int) $t['date_creation'] : null);
            $tEnd   = ! empty($t['date_due']) ? (int) $t['date_due'] : (! empty($t['date_started']) ? (int) $t['date_started'] : null);

            if ($tStart !== null) {
                if ($dates[$pid]['min_start'] === null || $tStart < $dates[$pid]['min_start']) {
                    $dates[$pid]['min_start'] = $tStart;
                }
            }

            if ($tEnd !== null) {
                if ($dates[$pid]['max_end'] === null || $tEnd > $dates[$pid]['max_end']) {
                    $dates[$pid]['max_end'] = $tEnd;
                }
            }
        }

        return $dates;
    }

    /**
     * Synchronize a project's start_date and end_date in the database from its tasks.
     *
     * @param  integer $projectId
     */
    public function syncProjectDates($projectId)
    {
        $projectId = (int) $projectId;
        if ($projectId <= 0) {
            return;
        }

        $dates = $this->getProjectTaskDates(array($projectId));
        if (isset($dates[$projectId])) {
            $update = array();
            if ($dates[$projectId]['min_start'] !== null) {
                $update['start_date'] = date('Y-m-d', $dates[$projectId]['min_start']);
            }
            if ($dates[$projectId]['max_end'] !== null) {
                $update['end_date'] = date('Y-m-d', $dates[$projectId]['max_end']);
            }
            if (! empty($update)) {
                $this->db->table(CoreProjectModel::TABLE)->eq('id', $projectId)->update($update);
            }
        }
    }

    /**
     * One row per task in a project.
     *
     * @param  integer $projectId
     * @param  string  $view
     * @param  string  $search
     * @param  string  $order
     * @param  string  $direction
     * @return array
     */
    public function getTaskRows($projectId, $view = self::FILTER_OPEN, $search = '', $order = 'id', $direction = 'ASC')
    {
        $query = $this->db->table(TaskModel::TABLE)
            ->columns('id', 'title', 'column_id', 'owner_id', 'is_active', 'priority', 'color_id', 'reference',
                      'date_started', 'date_due', 'date_creation', 'date_completed',
                      'time_estimated', 'time_spent', 'position')
            ->eq('project_id', $projectId);

        if ($view === self::FILTER_OPEN) {
            $query->eq('is_active', 1);
        } elseif ($view === self::FILTER_CLOSED) {
            $query->eq('is_active', 0);
        } elseif ($view === self::FILTER_OVERDUE) {
            $query->eq('is_active', 1)->gt('date_due', 0)->lt('date_due', (int) strtotime('today'));
        } elseif ($view === self::FILTER_MINE) {
            $query->eq('is_active', 1)->eq('owner_id', $this->userSession->getId());
        }

        if ($search !== '') {
            $isAdvancedFilter = (strpos($search, ':') !== false);
            if ($isAdvancedFilter && isset($this->taskLexer)) {
                $lexerBuilder = $this->taskLexer
                    ->build($search)
                    ->withFilter(new \Kanboard\Filter\TaskProjectFilter($projectId));
                $tasks = $lexerBuilder->getQuery()->findAll();
            } else {
                $fuzzyIds = array();
                if (isset($this->fuzzySearchModel)) {
                    $fuzzyIds = $this->fuzzySearchModel->findTaskIdsByFuzzyTitle($search, $projectId);
                }

                $query->beginOr();
                $query->ilike('title', '%'.$search.'%');
                $query->ilike('description', '%'.$search.'%');
                if (! empty($fuzzyIds)) {
                    $query->in('id', $fuzzyIds);
                }
                $query->closeOr();
                $tasks = $query->findAll();
            }
        } else {
            $tasks = $query->findAll();
        }
        $users    = $this->userModel->getActiveUsersList();
        $columns  = $this->columnModel->getList($projectId);
        $project  = $this->projectModel->getById($projectId);
        $progress = $this->getSubtaskProgress($projectId);
        $tags     = $this->getTagsByTask($projectId);
        $subtasks = $this->getSubtasksByTask($projectId);
        $rows     = array();

        foreach ($tasks as $task) {
            $id = (int) $task['id'];

            $rows[] = array(
                'id'         => $id,
                'code'       => $this->getTaskCode($task),
                'title'      => $task['title'],
                'owner'      => isset($users[$task['owner_id']]) ? $users[$task['owner_id']] : '',
                'owner_id'   => (int) $task['owner_id'],
                'column_id'  => (int) $task['column_id'],
                'status'     => isset($columns[$task['column_id']]) ? $columns[$task['column_id']] : '',
                'status_class' => $this->helper->taskTree->getStatusClass(isset($columns[$task['column_id']]) ? $columns[$task['column_id']] : ''),
                'is_active'  => (int) $task['is_active'],
                'tags'       => isset($tags[$id]) ? $tags[$id] : array(),
                'start_date' => (int) $task['date_started'],
                'date_due'   => (int) $task['date_due'],
                'duration'   => $this->getDuration($task['date_started'], $task['date_due']),
                'priority'   => (int) $task['priority'],
                'progress'   => isset($progress[$id]) ? $progress[$id] : 0,
                'color_id'   => $task['color_id'],
                'is_overdue' => ! empty($task['date_due']) && $task['is_active'] == 1 && $task['date_due'] < (int) strtotime('today'),
                'subtasks'   => isset($subtasks[$id]) ? $subtasks[$id] : array(),
            );
        }

        return $this->sortRows($rows, $order, $direction);
    }

    /**
     * Every subtask in the project, grouped by task.
     *
     * One query for the whole grid rather than one per row: the grid renders
     * up to a page of tasks, and a query each would be a page of round trips
     * for something almost always small.
     *
     * @param  integer $projectId
     * @return array   task_id => list of subtasks
     */
    protected function getSubtasksByTask($projectId)
    {
        $rows = $this->db->table(SubtaskModel::TABLE)
            ->columns(
                SubtaskModel::TABLE.'.id',
                SubtaskModel::TABLE.'.title',
                SubtaskModel::TABLE.'.status',
                SubtaskModel::TABLE.'.task_id',
                SubtaskModel::TABLE.'.user_id',
                SubtaskModel::TABLE.'.time_estimated',
                SubtaskModel::TABLE.'.time_spent',
                SubtaskModel::TABLE.'.position'
            )
            ->join(TaskModel::TABLE, 'id', 'task_id')
            ->eq(TaskModel::TABLE.'.project_id', $projectId)
            ->asc(SubtaskModel::TABLE.'.position')
            ->findAll();

        $users    = $this->userModel->getActiveUsersList();
        $statuses = $this->subtaskModel->getStatusList();
        $grouped  = array();

        foreach ($rows as $row) {
            $taskId = (int) $row['task_id'];

            $grouped[$taskId][] = array(
                'id'           => (int) $row['id'],
                'title'        => $row['title'],
                'status'       => (int) $row['status'],
                'status_label' => isset($statuses[$row['status']]) ? $statuses[$row['status']] : '',
                'status_class' => $this->helper->taskTree->getSubtaskStatusClass($row['status']),
                'owner'        => isset($users[$row['user_id']]) ? $users[$row['user_id']] : '',
                'owner_id'     => (int) $row['user_id'],
                'time_estimated' => (float) $row['time_estimated'],
                'time_spent'     => (float) $row['time_spent'],
            );
        }

        return $grouped;
    }

    /**
     * Slice a decorated row set into a page, and describe the page.
     *
     * Both grids decorate rows in PHP (status classes, durations, tag lists),
     * so the slice happens after decoration rather than as SQL LIMIT. That
     * keeps sorting and grouping correct across the whole set; the queries
     * feeding it are aggregates now, so the cost is one pass over the rows
     * rather than a query per row.
     *
     * @param  array   $rows
     * @param  integer $page
     * @param  integer $perPage
     * @return array
     */
    public function paginate(array $rows, $page = 1, $perPage = self::PAGE_SIZE)
    {
        $total = count($rows);
        $perPage = max(1, (int) $perPage);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) $page));
        $offset = ($page - 1) * $perPage;

        return array(
            'rows'     => array_slice($rows, $offset, $perPage),
            'total'    => $total,
            'page'     => $page,
            'pages'    => $pages,
            'per_page' => $perPage,
            'from'     => $total === 0 ? 0 : $offset + 1,
            'to'       => min($total, $offset + $perPage),
            'has_prev' => $page > 1,
            'has_next' => $page < $pages,
        );
    }

    /**
     * A project's columns are its task statuses, with the badge class each one
     * maps onto. Used by the inline status control.
     *
     * @param  integer $projectId
     * @return array  array(id => label, id => class)
     */
    public function getStatusOptions($projectId)
    {
        $labels  = $this->columnModel->getList($projectId);
        $classes = array();

        foreach ($labels as $id => $label) {
            $classes[$id] = $this->helper->taskTree->getStatusClass($label);
        }

        return array($labels, $classes);
    }

    /**
     * Split rows into the buckets the Group By control asks for.
     *
     * @param  array  $rows
     * @param  string $grouping
     * @return array   label => rows
     */
    public function groupRows(array $rows, $grouping)
    {
        if ($grouping === 'none' || ! array_key_exists($grouping, $this->getGroupings())) {
            return array('' => $rows);
        }

        $groups = array();

        foreach ($rows as $row) {
            if ($grouping === 'status') {
                $key = $row['status'] !== '' ? $row['status'] : t('No status');
            } elseif ($grouping === 'owner') {
                $key = $row['owner'] !== '' ? $row['owner'] : t('Unassigned');
            } else {
                $key = $row['priority'] > 0 ? 'P'.$row['priority'] : t('No priority');
            }

            $groups[$key][] = $row;
        }

        ksort($groups);

        return $groups;
    }

    /**
     * "PA-1"-style code. Falls back to the numeric id when a project has no
     * identifier set, so the column is never blank.
     *
     * @param  array $project
     * @return string
     */
    public function getProjectCode(array $project)
    {
        if (empty($project)) {
            return '';
        }

        if (! empty($project['identifier'])) {
            return strtoupper(substr($project['identifier'], 0, 4));
        }

        return sprintf('P%03d', $project['id']);
    }

    /**
     * A task's code, as PROJECTCODE-NNN: MC01-001, MC01-002, FW02-001.
     *
     * Never truncated. It used to be cut to four characters, which suited the
     * random code Kanboard generates and would now reduce every task in a
     * project to the project's own code.
     *
     * @param  array $task
     * @return string
     */
    public function getTaskCode(array $task)
    {
        if (empty($task)) {
            return '';
        }

        if (! empty($task['reference'])) {
            return strtoupper($task['reference']);
        }

        return sprintf('T%03d', $task['id']);
    }

    /**
     * Working days between start and due, as Zoho shows it.
     *
     * @param  mixed $start
     * @param  mixed $due
     * @return integer  0 when either end is missing
     */
    protected function getDuration($start, $due)
    {
        $start = (int) $start;
        $due   = (int) $due;

        if ($start <= 0 || $due <= 0 || $due < $start) {
            return 0;
        }

        return (int) round(($due - $start) / 86400) + 1;
    }

    /**
     * Days between today and a project's end date.
     *
     * @param  string $endDate
     * @return integer|null  null when no end date is set
     */
    protected function getDaysLeft($endDate)
    {
        if (empty($endDate)) {
            return null;
        }

        $end = strtotime($endDate);

        if ($end === false) {
            return null;
        }

        return (int) floor(($end - (int) strtotime('today')) / 86400);
    }

    /**
     * project_id => total / open / closed counts.
     *
     * @param  array $projectIds
     * @return array
     */
    protected function getTaskStatsByProject(array $projectIds)
    {
        $stats = array();

        // GROUP BY in the database. Counting in PHP meant loading every task
        // row of every project the user can see just to render a count column.
        $rows = $this->db->table(TaskModel::TABLE)
            ->columns('project_id', 'is_active', 'COUNT(*) AS total')
            ->in('project_id', $projectIds)
            ->groupBy('project_id', 'is_active')
            ->findAll();

        foreach ($rows as $row) {
            $id = (int) $row['project_id'];
            $count = (int) $row['total'];

            if (! isset($stats[$id])) {
                $stats[$id] = array('total' => 0, 'open' => 0, 'closed' => 0);
            }

            $stats[$id]['total'] += $count;
            $stats[$id][$row['is_active'] == 1 ? 'open' : 'closed'] += $count;
        }

        return $stats;
    }

    /**
     * project_id => milestone count. Zoho calls these Phases.
     *
     * @param  array $projectIds
     * @return array
     */
    protected function getMilestoneCounts(array $projectIds)
    {
        $counts = array();

        $rows = $this->db->table(MilestoneModel::TABLE)
            ->columns('project_id', 'COUNT(*) AS total')
            ->in('project_id', $projectIds)
            ->groupBy('project_id')
            ->findAll();

        foreach ($rows as $row) {
            $counts[(int) $row['project_id']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * task_id => percent of its subtasks marked done.
     *
     * @param  integer $projectId
     * @return array
     */
    protected function getSubtaskProgress($projectId)
    {
        $totals = array();

        // One grouped row per (task, status) rather than one row per subtask.
        $rows = $this->db->table(SubtaskModel::TABLE)
            ->columns(SubtaskModel::TABLE.'.task_id', SubtaskModel::TABLE.'.status', 'COUNT(*) AS total')
            ->join(TaskModel::TABLE, 'id', 'task_id')
            ->eq(TaskModel::TABLE.'.project_id', $projectId)
            ->groupBy(SubtaskModel::TABLE.'.task_id', SubtaskModel::TABLE.'.status')
            ->findAll();

        foreach ($rows as $row) {
            $taskId = (int) $row['task_id'];
            $count = (int) $row['total'];

            if (! isset($totals[$taskId])) {
                $totals[$taskId] = array('done' => 0, 'total' => 0);
            }

            $totals[$taskId]['total'] += $count;

            if ((int) $row['status'] === SubtaskModel::STATUS_DONE) {
                $totals[$taskId]['done'] += $count;
            }
        }

        $progress = array();

        foreach ($totals as $taskId => $counts) {
            $progress[$taskId] = $counts['total'] > 0 ? (int) round($counts['done'] / $counts['total'] * 100) : 0;
        }

        return $progress;
    }

    /**
     * task_id => list of tag names.
     *
     * @param  integer $projectId
     * @return array
     */
    protected function getTagsByTask($projectId)
    {
        $tags = array();

        $rows = $this->db->table('task_has_tags')
            ->columns('task_has_tags.task_id', 'tags.name')
            ->join('tags', 'id', 'tag_id')
            ->join(TaskModel::TABLE, 'id', 'task_id', 'task_has_tags')
            ->eq(TaskModel::TABLE.'.project_id', $projectId)
            ->findAll();

        foreach ($rows as $row) {
            $tags[(int) $row['task_id']][] = $row['name'];
        }

        return $tags;
    }

    /**
     * @param  array  $rows
     * @param  string $order
     * @param  string $direction
     * @return array
     */
    protected function sortRows(array $rows, $order, $direction)
    {
        $allowed = array('id', 'title', 'owner', 'status', 'start_date', 'date_due', 'duration', 'priority', 'progress');

        if (! in_array($order, $allowed, true)) {
            $order = 'id';
        }

        $factor = strtoupper($direction) === 'DESC' ? -1 : 1;

        usort($rows, function ($a, $b) use ($order, $factor) {
            $left  = $a[$order];
            $right = $b[$order];

            if (is_string($left) || is_string($right)) {
                return strcasecmp((string) $left, (string) $right) * $factor;
            }

            if ($left == $right) {
                return 0;
            }

            return ($left < $right ? -1 : 1) * $factor;
        });

        return $rows;
    }
}
