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
            'can_move'       => $this->helper->user->hasProjectAccess('BoardAjaxController', 'save', $task['project_id']),
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

            /* Reference material the task points at rather than carries:
               specifications, drawings, repositories, shared drives. Kanboard
               already models these as external links; they simply were not
               being shown anywhere in this panel. */
            'links'        => $this->taskExternalLinkModel->getAll($task['id']),
            'dependencies' => $this->dependencyModel->getAllByTask($task['id']),
            'entries'      => $this->getTimeEntries($task['id']),
            'transitions'  => $this->transitionModel->getAllByTask($task['id']),
            'can_edit'     => $this->helper->user->hasProjectAccess('TaskModificationController', 'edit', $task['project_id']),
        )));
    }

    /**
     * Days between start and due, matching the grid's Duration column.
     *
     * @param  array $task
     * @return integer
     */
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
