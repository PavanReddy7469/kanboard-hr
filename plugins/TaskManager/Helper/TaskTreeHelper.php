<?php

namespace Kanboard\Plugin\TaskManager\Helper;

use Kanboard\Core\Base;
use Kanboard\Core\Security\Role;
use Kanboard\Model\ProjectRoleRestrictionModel;

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
     * @return array
     */
    public function getProjectTree($projectId)
    {
        $columns = $this->columnModel->getList($projectId);
        $users   = $this->userModel->getActiveUsersList();

        $tasksByGroup = $this->groupTasks($projectId, $columns, $users);
        $listsByMilestone = $this->taskListModel->getGroupedByMilestone($projectId);
        $milestones = $this->milestoneModel->getAllWithProgress($projectId);

        $tree = array();

        foreach ($milestones as $milestone) {
            $tree[] = $this->buildMilestoneNode($milestone, $listsByMilestone, $tasksByGroup);
        }

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

        if ($orphan['nb_rows'] > 0) {
            $orphan['is_orphan'] = true;
            $tree[] = $orphan;
        }

        return $tree;
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
     * @return array  [milestone_id][task_list_id] => tasks
     */
    protected function groupTasks($projectId, array $columns, array $users)
    {
        $grouped      = array();
        $dependencies = $this->dependencyModel->getAllByProjectGroupedByTask($projectId);

        foreach ($this->taskFinderModel->getAll($projectId) as $task) {
            $task['subtasks']       = $this->decorateSubtasks($this->subtaskModel->getAll($task['id']));
            $task['column_title']   = isset($columns[$task['column_id']]) ? $columns[$task['column_id']] : t('Open');
            $task['assignee_name']  = isset($users[$task['owner_id']]) ? $users[$task['owner_id']] : t('Unassigned');
            $task['priority_label'] = $this->getPriorityLabel($task['priority']);
            $task['priority_class'] = $this->getPriorityClass($task['priority']);
            $task['status_class']   = $this->getStatusClass($task['column_title']);
            $task['dependencies']   = isset($dependencies[$task['id']]) ? $dependencies[$task['id']] : array();

            $milestoneId = isset($task['milestone_id']) ? (int) $task['milestone_id'] : 0;
            $taskListId  = isset($task['task_list_id']) ? (int) $task['task_list_id'] : 0;

            $grouped[$milestoneId][$taskListId][] = $task;
        }

        return $grouped;
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
