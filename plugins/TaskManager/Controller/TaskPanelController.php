<?php

namespace Kanboard\Plugin\TaskManager\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Model\TaskModel;

/**
 * The Zoho-style task detail drawer.
 *
 * Returns a fragment, not a page: the grid stays where it is and the panel
 * slides in over it. Every field is read here and edited through Kanboard's
 * own modals, so there is no second way to write a task.
 *
 * @package Kanboard\Plugin\TaskManager\Controller
 */
class TaskPanelController extends BaseController
{
    /**
     * Tabs along the bottom of the panel, in Zoho's order.
     *
     * @return array
     */
    public static function getTabs()
    {
        return array(
            'comments'  => 'Comments',
            'subtasks'  => 'Subtasks',
            'documents' => 'Documents',
            'links'     => 'Dependency',
            'timeline'  => 'Status Timeline',
        );
    }

    public function show()
    {
        $task    = $this->getTask();
        $project = $this->projectModel->getById($task['project_id']);
        $tab     = $this->request->getStringParam('tab', 'comments');

        if (! array_key_exists($tab, self::getTabs())) {
            $tab = 'comments';
        }

        list($columns, $columnClasses) = $this->gridModel->getStatusOptions($task['project_id']);

        $this->response->html($this->template->render('TaskManager:grid/panel', array(
            'columns'        => $columns,
            'column_classes' => $columnClasses,
            /* The authority helper alone. The board's ACL entry is
               PROJECT_MANAGER and says nothing about whose task this is. */
            'can_move'       => $this->helper->authority->canSetStatus($task),
            'task'         => $task,
            'project'      => $project,
            'code'         => $this->gridModel->getTaskCode($task),
            'tab'          => $tab,
            'tabs'         => self::getTabs(),
            'status_class' => $this->helper->taskTree->getStatusClass($task['column_title']),
            'duration'     => $this->getDuration($task),
            'progress'     => $this->getProgress($task['id']),
            'recurrence'   => $this->getRecurrenceLabel($task),
            'comments'     => $this->commentModel->getAll($task['id']),
            'subtasks'     => $this->subtaskModel->getAll($task['id']),
            'files'        => $this->taskFileModel->getAll($task['id']),

            /* What the server will actually accept, so the Documents tab can
               say so before somebody waits out an upload that was never
               going to be allowed. */
            'max_size'     => get_upload_max_size(),

            /* Reference material the task points at rather than carries:
               specifications, drawings, repositories, shared drives. Kanboard
               already models these as external links; they simply were not
               being shown anywhere in this panel. */
            'links'        => $this->taskExternalLinkModel->getAll($task['id']),
            'dependencies' => $this->dependencyModel->getAllByTask($task['id']),
            'entries'      => $this->getTimeEntries($task['id']),
            'transitions'  => $this->transitionModel->getAllByTask($task['id']),
            'can_edit'     => $this->helper->user->hasProjectAccess('TaskModificationController', 'edit', $task['project_id']),

            /* Owner and Priority are pickers here too, not just in the grid.
               Reading a task and changing one of its fields are the same
               moment; sending someone to the Edit modal to move a task from
               P5 to P1 was a dialog for a single click. Same widget, same
               endpoints, same permission checks as the grid. */
            'assignable_users' => array(0 => t('Unassigned'))
                + $this->projectUserRoleModel->getAssignableUsersList($task['project_id'], false),
            'can_assign'       => $this->helper->authority->canSetOwner($task),
            'priority_options' => $this->getPriorityChoices($project, 0),
            'priority_classes' => $this->getPriorityChoices($project, 1),
            'can_prioritise'   => $this->helper->authority->canSetPriority($task),
        )));
    }

    /**
     * Days between start and due, matching the grid's Duration column.
     *
     * @param  array $task
     * @return integer
     */
    /**
     * The positions a priority picker may offer: every place in the queue,
     * one more for a task joining it, and None for leaving it.
     *
     * Not the project's priority_end. That is a ceiling the queue grows
     * past - eighteen tasks need eighteen positions whatever the project
     * settings say - and PriorityModel widens it as the queue grows.
     *
     * @param  array   $project
     * @param  integer $which  0 for labels, 1 for css classes
     * @return array
     */
    protected function getPriorityChoices(array $project, $which)
    {
        $start  = max(1, (int) $project['priority_start']);
        $length = $this->priorityModel->getQueueLength($project['id']);

        $options = array(0 => t('None'));
        $classes = array(0 => $this->helper->taskTree->getPriorityClass(0));

        foreach (range($start, $start + $length) as $p) {
            $options[$p] = 'P'.$p;
            $classes[$p] = $this->helper->taskTree->getPriorityClass($p);
        }

        return $which === 0 ? $options : $classes;
    }

    protected function getDuration(array $task)
    {
        $start = (int) $task['date_started'];
        $due   = (int) $task['date_due'];

        if ($start <= 0 || $due <= 0 || $due < $start) {
            return 0;
        }

        return (int) round(($due - $start) / 86400) + 1;
    }

    /**
     * Percent of subtasks done, matching the grid's % Completion column.
     *
     * @param  integer $taskId
     * @return integer
     */
    protected function getProgress($taskId)
    {
        $subtasks = $this->subtaskModel->getAll($taskId);

        if (empty($subtasks)) {
            return 0;
        }

        $done = 0;

        foreach ($subtasks as $subtask) {
            if ((int) $subtask['status'] === \Kanboard\Model\SubtaskModel::STATUS_DONE) {
                $done++;
            }
        }

        return (int) round($done / count($subtasks) * 100);
    }

    /**
     * Kanboard has real recurrence; Zoho's Reminder and Billing Type have no
     * equivalent here, so the panel shows this row and omits those two rather
     * than inventing fields that store nothing.
     *
     * @param  array $task
     * @return string
     */
    protected function getRecurrenceLabel(array $task)
    {
        if (empty($task['recurrence_status']) || (int) $task['recurrence_status'] === TaskModel::RECURRING_STATUS_NONE) {
            return t('None');
        }

        $factor    = max(1, (int) $task['recurrence_factor']);
        $timeframe = (int) $task['recurrence_timeframe'];

        if ($timeframe === TaskModel::RECURRING_TIMEFRAME_MONTHS) {
            return t('Every %d month(s)', $factor);
        }

        if ($timeframe === TaskModel::RECURRING_TIMEFRAME_YEARS) {
            return t('Every %d year(s)', $factor);
        }

        return t('Every %d day(s)', $factor);
    }

    /**
     * Timesheet hours booked against this task.
     *
     * @param  integer $taskId
     * @return array
     */
    protected function getTimeEntries($taskId)
    {
        $users   = $this->userModel->getActiveUsersList();
        $entries = $this->db->table(\Kanboard\Plugin\TaskManager\Model\TimeEntryModel::TABLE)
            ->eq('task_id', $taskId)
            ->desc('work_date')
            ->findAll();

        foreach ($entries as &$entry) {
            $entry['user'] = isset($users[$entry['user_id']]) ? $users[$entry['user_id']] : '';
        }

        return $entries;
    }
}
