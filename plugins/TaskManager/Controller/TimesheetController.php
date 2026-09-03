<?php

namespace Kanboard\Plugin\TaskManager\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Plugin\TaskManager\Model\TimesheetModel;

/**
 * Weekly timesheet: the grid, submit, approve and CSV export.
 *
 * @package Kanboard\Plugin\TaskManager\Controller
 */
class TimesheetController extends BaseController
{
    public function show()
    {
        $project   = $this->getProject();
        $userId    = $this->getTargetUserId($project);
        $weekStart = $this->getWeekStart();
        $sheet     = $this->timesheetModel->getOrDraft($userId, $project['id'], $weekStart);

        $this->response->html($this->helper->layout->project('TaskManager:timesheet/show', array(
            'project'    => $project,
            'title'      => $project['name'].' - '.t('Timesheet'),
            'user_id'    => $userId,
            'users'      => $this->projectUserRoleModel->getAssignableUsersList($project['id'], false),
            'week_start' => $weekStart,
            'days'       => $this->timesheetModel->getWeekDays($weekStart),
            'rows'       => $this->withRequestedTask($this->timeEntryModel->getWeekGrid($userId, $project['id'], $weekStart), $project['id']),
            'summary'    => $this->timeEntryModel->getWeekSummary($userId, $project['id'], $weekStart),
            'sheet'      => $sheet,
            'states'     => $this->timesheetModel->getStates(),
            'locked'     => $this->timesheetModel->isLocked($sheet) || ! $this->canEdit($project, $userId),
            'can_approve' => $this->helper->taskTree->canApproveTimesheets($project['id']),
            'tasks'      => $this->getLoggableTasks($project['id']),
            'pending'    => $this->helper->taskTree->canApproveTimesheets($project['id'])
                ? $this->timesheetModel->getPendingByProject($project['id'])
                : array(),
        ), 'TaskManager:project/no_sidebar'));
    }

    public function save()
    {
        $project   = $this->getProject();
        $userId    = $this->getTargetUserId($project);
        $weekStart = $this->getWeekStart();
        $sheet     = $this->timesheetModel->getOrDraft($userId, $project['id'], $weekStart);

        if ($this->timesheetModel->isLocked($sheet) || ! $this->canEdit($project, $userId)) {
            throw new AccessForbiddenException(t('This week is locked.'));
        }

        $values   = $this->request->getValues();
        $cells    = isset($values['hours']) && is_array($values['hours']) ? $values['hours'] : array();
        $billable = isset($values['billable']) && is_array($values['billable']) ? $values['billable'] : array();

        if ($this->timeEntryModel->saveWeek($userId, $project['id'], $weekStart, $cells, $billable)) {
            $this->flash->success(t('Timesheet saved.'));
        } else {
            $this->flash->failure(t('Unable to save this timesheet.'));
        }

        $this->redirectToWeek($project, $userId, $weekStart);
    }

    public function submit()
    {
        $project   = $this->getProject();
        $userId    = $this->getTargetUserId($project);
        $weekStart = $this->getWeekStart();
        $this->checkReusableGETCSRFParam();

        if (! $this->canEdit($project, $userId)) {
            throw new AccessForbiddenException(t('You can only submit your own timesheet.'));
        }

        $this->timesheetModel->submit($userId, $project['id'], $weekStart);
        $this->flash->success(t('Timesheet submitted for approval.'));
        $this->redirectToWeek($project, $userId, $weekStart);
    }

    public function decide()
    {
        $project = $this->getProject();
        $this->checkReusableGETCSRFParam();

        if (! $this->helper->taskTree->canApproveTimesheets($project['id'])) {
            throw new AccessForbiddenException(t('Only a Team Lead or above can approve timesheets.'));
        }

        $sheet = $this->timesheetModel->getById($this->request->getIntegerParam('timesheet_id'));

        if (empty($sheet) || $sheet['project_id'] != $project['id']) {
            throw new AccessForbiddenException(t('Timesheet not found in this project.'));
        }

        $state = $this->request->getStringParam('state') === TimesheetModel::STATE_APPROVED
            ? TimesheetModel::STATE_APPROVED
            : TimesheetModel::STATE_REJECTED;

        $this->timesheetModel->decide($sheet['id'], $this->userSession->getId(), $state);
        $this->flash->success($state === TimesheetModel::STATE_APPROVED ? t('Timesheet approved.') : t('Timesheet sent back.'));
        $this->redirectToWeek($project, $sheet['user_id'], $sheet['week_start']);
    }

    /**
     * CSV of every entry in the shown week, for payroll.
     */
    public function export()
    {
        $project = $this->getProject();

        if (! $this->helper->taskTree->canApproveTimesheets($project['id'])) {
            throw new AccessForbiddenException(t('Only a Team Lead or above can export timesheets.'));
        }

        $weekStart = $this->getWeekStart();
        $weekEnd   = (int) strtotime('+6 day', $weekStart);
        $rows      = $this->timeEntryModel->getExportRows($project['id'], $weekStart, $weekEnd);

        $this->response->withFileDownload('timesheet-'.date('Y-m-d', $weekStart).'.csv');
        $this->response->csv($rows);
    }

    /**
     * Import Kanboard's existing subtask timer records into the timesheet.
     */
    public function backfill()
    {
        $project = $this->getProject();
        $this->checkReusableGETCSRFParam();

        if (! $this->helper->taskTree->canApproveTimesheets($project['id'])) {
            throw new AccessForbiddenException(t('Only a Team Lead or above can import time.'));
        }

        $count = $this->timeEntryModel->backfillFromSubtaskTimeTracking($project['id']);
        $this->flash->success(t('%d time record(s) imported.', $count));

        $this->response->redirect($this->helper->url->to('TimesheetController', 'show', array(
            'project_id' => $project['id'],
            'plugin'     => 'TaskManager',
        )));
    }

    /**
     * Whose sheet is being shown. Leads may look at anyone's; everyone else
     * only ever sees their own.
     *
     * @param  array $project
     * @return integer
     */
    protected function getTargetUserId(array $project)
    {
        $requested = $this->request->getIntegerParam('user_id');
        $self      = $this->userSession->getId();

        if ($requested > 0 && $requested != $self && $this->helper->taskTree->canApproveTimesheets($project['id'])) {
            return $requested;
        }

        return $self;
    }

    /**
     * @param  array   $project
     * @param  integer $userId
     * @return boolean
     */
    protected function canEdit(array $project, $userId)
    {
        return (int) $userId === (int) $this->userSession->getId();
    }

    /**
     * @return integer
     */
    protected function getWeekStart()
    {
        $requested = $this->request->getIntegerParam('week');

        return $this->timesheetModel->getWeekStart($requested > 0 ? $requested : null);
    }

    /**
     * A task picked from the "add a task" dropdown gets an empty row so the
     * person has somewhere to type. Nothing is written until they save.
     *
     * @param  array   $rows
     * @param  integer $projectId
     * @return array
     */
    protected function withRequestedTask(array $rows, $projectId)
    {
        $taskId = $this->request->getIntegerParam('add_task');

        if ($taskId <= 0 || isset($rows[$taskId])) {
            return $rows;
        }

        $task = $this->taskFinderModel->getById($taskId);

        if (empty($task) || $task['project_id'] != $projectId) {
            return $rows;
        }

        $rows[$taskId] = array(
            'task_id'     => $taskId,
            'title'       => $task['title'],
            'is_billable' => 1,
            'days'        => array(),
            'total'       => 0.0,
        );

        return $rows;
    }

    /**
     * @param  integer $projectId
     * @return array
     */
    protected function getLoggableTasks($projectId)
    {
        $tasks = array(0 => t('Add a task to log time against...'));

        foreach ($this->taskFinderModel->getAll($projectId) as $task) {
            $tasks[$task['id']] = '#'.$task['id'].' '.$task['title'];
        }

        return $tasks;
    }

    /**
     * @param  array   $project
     * @param  integer $userId
     * @param  integer $weekStart
     * @return void
     */
    protected function redirectToWeek(array $project, $userId, $weekStart)
    {
        $this->response->redirect($this->helper->url->to('TimesheetController', 'show', array(
            'project_id' => $project['id'],
            'user_id'    => $userId,
            'week'       => $weekStart,
            'plugin'     => 'TaskManager',
        )));
    }
}
