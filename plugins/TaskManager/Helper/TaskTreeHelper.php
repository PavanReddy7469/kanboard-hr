<?php

namespace Kanboard\Plugin\TaskManager\Helper;

use Kanboard\Core\Base;
use Kanboard\Core\Security\Role;
use Kanboard\Model\ProjectRoleRestrictionModel;
use Kanboard\Model\TaskModel;

/**
 * Builds the SUPERBEE project tree and answers the questions the tree
 * template needs to ask.
 *
 * Project > Milestone > Task list > Task > Subtask
 *
 * Tasks that belong to no milestone or no task list are not hidden — they
 * collect in an "Unassigned" group so nothing can fall out of the view.
 *
 * @package Kanboard\Plugin\TaskManager\Helper
 */
class TaskTreeHelper extends Base
{
    /**
     * @param  integer $projectId
     * @param  array   $filters  assignee: '', 'nobody' or a user id;
     *                           status:   '', 'open', 'wip', 'rev' or 'closed'
     * @return array
     */
    public function getProjectTree($projectId, array $filters = array())
    {
        $columns = $this->columnModel->getList($projectId);
        $users   = $this->userModel->getActiveUsersList();

        $tasksByGroup = $this->groupTasks($projectId, $columns, $users, $filters);
        $listsByMilestone = $this->taskListModel->getGroupedByMilestone($projectId);
        $milestones = $this->milestoneModel->getAllWithProgress($projectId);

        /* With a filter on, a milestone or a list that matched nothing is
           noise: a screen of empty headers reads as "broken", not as "no
           results". Unfiltered, they stay - an empty milestone is a real
           part of the plan and hiding it would hide the place to add work. */
        $isFiltered = $this->hasFilters($filters);

        $tree = array();

        foreach ($milestones as $milestone) {
            $node = $this->buildMilestoneNode($milestone, $listsByMilestone, $tasksByGroup);

            if ($isFiltered) {
                $node['lists'] = array_values(array_filter($node['lists'], function (array $list) {
                    return $list['nb_tasks'] > 0;
                }));

                if (empty($node['lists']) && empty($node['tasks'])) {
                    continue;
                }
            }

            $tree[] = $node;
        }

        $this->sweepStrandedTasks($tasksByGroup, $milestones, $listsByMilestone);

        $orphan = $this->buildMilestoneNode(
            array(
                'id'          => 0,
                'title'       => t('Unassigned'),
                'date_start'  => 0,
                'date_due'    => 0,
                'date_reached' => 0,
                'owner_id'    => 0,
                'progress'    => 0,
                'nb_tasks'    => 0,
                'nb_closed'   => 0,
                'is_reached'  => false,
                'is_overdue'  => false,
            ),
            $listsByMilestone,
            $tasksByGroup
        );

        if ($isFiltered) {
            $orphan['lists'] = array_values(array_filter($orphan['lists'], function (array $list) {
                return $list['nb_tasks'] > 0;
            }));
        }

        if ($orphan['nb_rows'] > 0 && (! $isFiltered || ! empty($orphan['lists']) || ! empty($orphan['tasks']))) {
            $orphan['is_orphan'] = true;
            $tree[] = $orphan;
        }

        return $tree;
    }

    /**
     * The filter bar's current selection, read from the query string.
     *
     * @return array
     */
    public function getFilters()
    {
        return array(
            'assignee' => trim((string) $this->request->getStringParam('assignee_filter')),
            'status'   => strtolower(trim((string) $this->request->getStringParam('status_filter'))),
        );
    }

    /**
     * The route the filter form should submit back to: the page it is on.
     *
     * The form used to post a hard-coded controller and action, so choosing
     * a name on the Task Tree page navigated to a different page altogether
     * and the filter appeared to do nothing. The partial is shared, so the
     * target has to come from where it is being rendered.
     *
     * @return array  hidden form fields, name => value
     */
    public function getFilterRoute()
    {
        /* The router, not the request: with URL rewriting on, the controller
           and action come from the route table and are not in the query
           string at all. */
        $controller = $this->router->getController();
        $action     = $this->router->getAction();
        $plugin     = $this->router->getPlugin();

        $route = array(
            'controller' => $controller !== '' ? $controller : 'TaskManagerController',
            'action'     => $action !== '' ? $action : 'show',
            'project_id' => $this->request->getIntegerParam('project_id'),
        );

        if ($plugin !== '') {
            $route['plugin'] = $plugin;
        }

        return $route;
    }

    /**
     * @param  array $filters
     * @return boolean
     */
    public function hasFilters(array $filters)
    {
        foreach (array('assignee', 'status') as $key) {
            if (isset($filters[$key]) && trim((string) $filters[$key]) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * How many tasks a tree holds, counting the tasks themselves and not the
     * milestone or task-list headers above them.
     *
     * @param  array $tree
     * @return integer
     */
    public function countTasks(array $tree)
    {
        $total = 0;

        foreach ($tree as $milestone) {
            $total += count($milestone['tasks']);

            foreach ($milestone['lists'] as $list) {
                $total += count($list['tasks']);
            }
        }

        return $total;
    }

    /**
     * Move into "Unassigned" any task no milestone node will render.
     *
     * The tree reaches for a task at [milestone_id][task_list_id]. A pair
     * that names nothing on screen - a milestone that was archived, a list
     * that was deleted or has since moved under a different milestone -
     * matches no node, and the task is simply not drawn. The models now keep
     * those two columns in step, but old rows predate that, and a direct
     * database edit is not bound by it either.
     *
     * This is the backstop for the promise made at the top of this class:
     * nothing falls out of the view. A task in the wrong group looks
     * misfiled, which someone can fix; a task that is invisible looks
     * deleted, which nobody thinks to fix.
     *
     * @param  array $tasksByGroup     Modified in place.
     * @param  array $milestones       The milestone nodes actually rendered.
     * @param  array $listsByMilestone
     */
    protected function sweepStrandedTasks(array &$tasksByGroup, array $milestones, array $listsByMilestone)
    {
        $rendered = array(0 => true);

        foreach ($milestones as $milestone) {
            $rendered[(int) $milestone['id']] = true;
        }

        /* The lists the "Unassigned" node will draw for itself. Tasks in
           those are already accounted for and must not be swept up. */
        $orphanLists = array();

        if (isset($listsByMilestone[0])) {
            foreach ($listsByMilestone[0] as $taskList) {
                $orphanLists[(int) $taskList['id']] = true;
            }
        }

        $stranded = array();

        foreach ($tasksByGroup as $groupMilestoneId => $lists) {
            $milestoneId = (int) $groupMilestoneId;

            foreach ($lists as $groupListId => $tasks) {
                $listId = (int) $groupListId;

                if ($milestoneId === 0 && ($listId === 0 || isset($orphanLists[$listId]))) {
                    continue;
                }

                if (isset($rendered[$milestoneId]) && $milestoneId !== 0) {
                    /* buildMilestoneNode already drew this milestone. It
                       consumed [id][0] and every list under it; anything
                       else left here names a list that is not under it. */
                    if ($listId === 0 || $this->listBelongsTo($listsByMilestone, $milestoneId, $listId)) {
                        continue;
                    }
                }

                $stranded = array_merge($stranded, $tasks);
                unset($tasksByGroup[$milestoneId][$listId]);
            }
        }

        if (! empty($stranded)) {
            $existing = isset($tasksByGroup[0][0]) ? $tasksByGroup[0][0] : array();
            $tasksByGroup[0][0] = array_merge($existing, $stranded);
        }
    }

    /**
     * @param  array   $listsByMilestone
     * @param  integer $milestoneId
     * @param  integer $listId
     * @return boolean
     */
    protected function listBelongsTo(array $listsByMilestone, $milestoneId, $listId)
    {
        if (! isset($listsByMilestone[$milestoneId])) {
            return false;
        }

        foreach ($listsByMilestone[$milestoneId] as $taskList) {
            if ((int) $taskList['id'] === $listId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array $milestone
     * @param  array $listsByMilestone
     * @param  array $tasksByGroup
     * @return array
     */
    protected function buildMilestoneNode(array $milestone, array $listsByMilestone, array $tasksByGroup)
    {
        $milestoneId = (int) $milestone['id'];
        $milestone['is_orphan'] = false;
        $milestone['lists'] = array();
        $milestone['tasks'] = isset($tasksByGroup[$milestoneId][0]) ? $tasksByGroup[$milestoneId][0] : array();

        $rows   = count($milestone['tasks']);
        $tasks  = $milestone['tasks'];

        if (isset($listsByMilestone[$milestoneId])) {
            foreach ($listsByMilestone[$milestoneId] as $taskList) {
                $listId = (int) $taskList['id'];
                $taskList['tasks'] = isset($tasksByGroup[$milestoneId][$listId]) ? $tasksByGroup[$milestoneId][$listId] : array();
                $taskList['nb_tasks'] = count($taskList['tasks']);
                $rows += $taskList['nb_tasks'] + 1;
                $tasks = array_merge($tasks, $taskList['tasks']);
                $milestone['lists'][] = $taskList;
            }
        }

        // Count from the rows actually on screen, so the pill can never
        // disagree with what is under it.
        $closed = 0;

        foreach ($tasks as $task) {
            if (empty($task['is_active'])) {
                $closed++;
            }
        }

        $milestone['nb_tasks']  = count($tasks);
        $milestone['nb_closed'] = $closed;
        $milestone['progress']  = $milestone['nb_tasks'] > 0 ? (int) round($closed / $milestone['nb_tasks'] * 100) : 0;
        $milestone['nb_rows']   = $rows;

        return $milestone;
    }

    /**
     * @param  integer $projectId
     * @param  array   $columns
     * @param  array   $users
     * @param  array   $filters
     * @return array  [milestone_id][task_list_id] => tasks
     */
    protected function groupTasks($projectId, array $columns, array $users, array $filters = array())
    {
        $grouped      = array();
        $dependencies = $this->dependencyModel->getAllByProjectGroupedByTask($projectId);

        foreach ($this->getAllTasks($projectId) as $task) {
            $task['subtasks']       = $this->decorateSubtasks($this->subtaskModel->getAll($task['id']));
            $task['column_title']   = isset($columns[$task['column_id']]) ? $columns[$task['column_id']] : t('Open');
            $task['assignee_name']  = isset($users[$task['owner_id']]) ? $users[$task['owner_id']] : t('Unassigned');
            $task['priority_label'] = $this->getPriorityLabel($task['priority']);
            $task['priority_class'] = $this->getPriorityClass($task['priority']);
            $task['status_class']   = $this->getStatusClass($task['column_title']);
            $task['dependencies']   = isset($dependencies[$task['id']]) ? $dependencies[$task['id']] : array();

            $task = $this->applyFilters($task, $filters);

            if ($task === null) {
                continue;
            }

            $milestoneId = isset($task['milestone_id']) ? (int) $task['milestone_id'] : 0;
            $taskListId  = isset($task['task_list_id']) ? (int) $task['task_list_id'] : 0;

            $grouped[$milestoneId][$taskListId][] = $task;
        }

        return $grouped;
    }

    /**
     * Every task in the project, open and closed.
     *
     * taskFinderModel->getAll() defaults to open tasks only, which quietly
     * emptied two things this table was built for: it has a Completion Date
     * column that could never be filled, and a Closed option on the status
     * filter that could never match anything.
     *
     * @param  integer $projectId
     * @return array
     */
    protected function getAllTasks($projectId)
    {
        $open   = $this->taskFinderModel->getAll($projectId, TaskModel::STATUS_OPEN);
        $closed = $this->taskFinderModel->getAll($projectId, TaskModel::STATUS_CLOSED);

        $tasks = array_merge($open, $closed);

        /* getAll() sorts by id within each status; merged, the closed ones
           would otherwise all land after the open ones. */
        usort($tasks, function (array $a, array $b) {
            return (int) $a['id'] - (int) $b['id'];
        });

        return $tasks;
    }

    /**
     * Decide whether a task survives the filter bar, and narrow its subtasks.
     *
     * The assignee filter reaches subtasks as well as tasks, because a
     * subtask carries its own assignee and is often the only thing in a task
     * that belongs to the person asking. A task is kept when it is theirs or
     * when one of its subtasks is, and the subtasks shown are narrowed to
     * theirs - which is what the sentence above the table says is happening.
     *
     * @param  array $task
     * @param  array $filters
     * @return array|null  null when the task is filtered out
     */
    protected function applyFilters(array $task, array $filters)
    {
        $status = isset($filters['status']) ? strtolower(trim((string) $filters['status'])) : '';

        if ($status !== '' && $task['status_class'] !== 'status-'.$status) {
            return null;
        }

        $assignee = isset($filters['assignee']) ? trim((string) $filters['assignee']) : '';

        if ($assignee === '') {
            return $task;
        }

        $ownerId    = (int) $task['owner_id'];
        $taskIsTheirs = $assignee === 'nobody' ? $ownerId === 0 : $ownerId === (int) $assignee;

        $matchingSubtasks = array();

        foreach ($task['subtasks'] as $subtask) {
            $subtaskOwner = isset($subtask['user_id']) ? (int) $subtask['user_id'] : 0;
            $subtaskIsTheirs = $assignee === 'nobody'
                ? $subtaskOwner === 0
                : $subtaskOwner === (int) $assignee;

            if ($subtaskIsTheirs) {
                $matchingSubtasks[] = $subtask;
            }
        }

        if (! $taskIsTheirs && empty($matchingSubtasks)) {
            return null;
        }

        $task['subtasks'] = $matchingSubtasks;

        return $task;
    }

    /**
     * Subtasks carry their own assignee and, since Phase 2, their own due date.
     *
     * @param  array $subtasks
     * @return array
     */
    protected function decorateSubtasks(array $subtasks)
    {
        foreach ($subtasks as &$subtask) {
            $subtask['assignee_name'] = ! empty($subtask['username'])
                ? ($subtask['name'] ?: $subtask['username'])
                : t('Unassigned');

            $subtask['date_due'] = isset($subtask['date_due']) ? (int) $subtask['date_due'] : 0;

            if ($subtask['status'] == 2) {
                $subtask['status_class'] = 'status-closed';
                $subtask['status_label'] = t('Done');
            } elseif ($subtask['status'] == 1) {
                $subtask['status_class'] = 'status-wip';
                $subtask['status_label'] = t('WIP');
            } else {
                $subtask['status_class'] = 'status-open';
                $subtask['status_label'] = t('To Do');
            }
        }

        return $subtasks;
    }

    /**
     * "#118 FS +2d" for the Dependencies column.
     *
     * @param  array $dependency
     * @return string
     */
    public function getDependencyLabel(array $dependency)
    {
        return $this->dependencyModel->getShortLabel($dependency);
    }

    /**
     * @param  integer $projectId
     * @return array
     */
    public function getAssigneeList($projectId)
    {
        return $this->projectUserRoleModel->getAssignableUsersList($projectId, false);
    }

    /**
     * May add and delete tasks, milestones, task lists and dependencies.
     *
     * Read from Kanboard's own per-project role restrictions rather than
     * hand-rolled here. Restrictions are negative: a custom role with no
     * task_creation rule against it is allowed. Application admins and the
     * core Project Manager role are always allowed, as everywhere else in
     * Kanboard.
     *
     * @param  integer $projectId
     * @return boolean
     */
    public function canManageTasks($projectId)
    {
        return $this->isAllowed($projectId, ProjectRoleRestrictionModel::RULE_TASK_CREATION);
    }

    /**
     * May approve or send back a submitted timesheet, export payroll CSV and
     * look at other people's weeks.
     *
     * Kanboard has no native rule for timesheet approval, so this is pinned to
     * the deletion guardrail: a role trusted to remove work is trusted to sign
     * off on the hours booked against it.
     *
     * @param  integer $projectId
     * @return boolean
     */
    public function canApproveTimesheets($projectId)
    {
        return $this->isAllowed($projectId, ProjectRoleRestrictionModel::RULE_TASK_SUPPRESSION)
            && $this->canManageTasks($projectId);
    }

    /**
     * The project role the logged-in user holds, or an empty string.
     *
     * @param  integer $projectId
     * @return string
     */
    public function getProjectRole($projectId)
    {
        return (string) $this->projectUserRoleModel->getUserRole($projectId, $this->userSession->getId());
    }

    /**
     * True when nothing takes the given rule away from the current user.
     *
     * @param  integer $projectId
     * @param  string  $rule
     * @return boolean
     */
    protected function isAllowed($projectId, $rule)
    {
        $userId = $this->userSession->getId();

        if ($this->userModel->isAdmin($userId)) {
            return true;
        }

        $role = $this->getProjectRole($projectId);

        if ($role === Role::PROJECT_MANAGER) {
            return true;
        }

        if (! $this->role->isCustomProjectRole($role)) {
            return false;
        }

        foreach ($this->projectRoleRestrictionModel->getAllByRole($projectId, $role) as $restriction) {
            if ($restriction['rule'] === $rule) {
                return false;
            }
        }

        return true;
    }

    /**
     * Map a column title onto one of the four lifecycle badge styles.
     *
     * Projects name their columns freely (Backlog, Ready, Work in progress,
     * Done...), so matching on the literal title leaves most badges unstyled.
     *
     * @param  string $columnTitle
     * @return string
     */
    /**
     * The pill colour for a subtask's status.
     *
     * Subtasks use Kanboard's own three states rather than project columns,
     * so they map straight onto the same visual language the task pills use:
     * open, work in progress, done.
     *
     * @param  integer $status
     * @return string
     */
    public function getSubtaskStatusClass($status)
    {
        switch ((int) $status) {
            case \Kanboard\Model\SubtaskModel::STATUS_DONE:
                return 'status-closed';
            case \Kanboard\Model\SubtaskModel::STATUS_INPROGRESS:
                return 'status-wip';
            default:
                return 'status-open';
        }
    }

    public function getStatusClass($columnTitle)
    {
        $title = strtolower(trim($columnTitle));

        $map = array(
            'closed' => array('closed', 'done', 'complete', 'completed', 'finished', 'archived'),
            'wip'    => array('wip', 'work in progress', 'in progress', 'doing', 'active', 'started'),
            'rev'    => array('rev', 'review', 'in review', 'qa', 'testing', 'verification'),
        );

        foreach ($map as $class => $titles) {
            if (in_array($title, $titles, true)) {
                return 'status-'.$class;
            }
        }

        return 'status-open';
    }

    /**
     * "P4", or an empty string when no priority is set.
     *
     * @param  mixed $priority
     * @return string
     */
    public function getPriorityLabel($priority)
    {
        $value = (int) $priority;

        return $value > 0 ? 'P'.$value : '';
    }

    /**
     * Four badge bands across P1-P10, so P4 and above stop borrowing the P3 style.
     *
     * @param  mixed $priority
     * @return string
     */
    public function getPriorityClass($priority)
    {
        $value = (int) $priority;

        if ($value <= 0) {
            return 'prio-none';
        }

        if ($value >= 7) {
            return 'prio-p7';
        }

        if ($value >= 4) {
            return 'prio-p4';
        }

        return 'prio-p'.$value;
    }
}
