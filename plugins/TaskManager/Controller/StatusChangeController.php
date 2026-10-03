<?php

namespace Kanboard\Plugin\TaskManager\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;

/**
 * Inline status changes from the grids and the task drawer.
 *
 * A task's status IS its column - that is what Kanboard stores and what the
 * board, the grid and the Kanban view all read - so changing status here goes
 * through TaskPositionModel::movePosition(), exactly the call the board makes
 * when you drag a card. Nothing writes to tasks.column_id directly, so the
 * move events, position bookkeeping and automatic actions all still fire.
 *
 * A project's status is is_active, which Kanboard exposes as Active/Archived
 * and nothing else.
 *
 * @package Kanboard\Plugin\TaskManager\Controller
 */
class StatusChangeController extends BaseController
{
    /**
     * Move a task to another column.
     */
    public function task()
    {
        $task = $this->getTask();
        $this->checkReusableGETCSRFParam();

        $projectId = (int) $task['project_id'];
        $columnId  = $this->request->getIntegerParam('column_id');
        $columns   = $this->columnModel->getList($projectId);

        if (! isset($columns[$columnId])) {
            throw new AccessForbiddenException(t('Unknown column.'));
        }

        if (! $this->helper->projectRole->canMoveTask($projectId, $task['column_id'], $columnId)
            || ! $this->helper->projectRole->canChangeTaskStatusInColumn($projectId, $columnId)) {
            throw new AccessForbiddenException(t("You don't have the permission to move this task"));
        }

        $moved = (int) $task['column_id'] === $columnId
            ? true
            : $this->taskPositionModel->movePosition($projectId, $task['id'], $columnId, 1, $task['swimlane_id']);

        if (! $moved) {
            $this->response->status(400);
            return;
        }

        $this->response->json(array(
            'ok'     => true,
            'label'  => $columns[$columnId],
            'class'  => $this->helper->taskTree->getStatusClass($columns[$columnId]),
        ));
    }

    /**
     * Assign a task to somebody, from the grid.
     *
     * The same pill the status uses, pointed at a different field. The save
     * goes through taskModificationModel rather than writing owner_id
     * straight to the row, so the assignee-change event fires and whoever
     * just picked up the work is told about it - which is most of the point
     * of assigning it.
     */
    public function owner()
    {
        $task = $this->getTask();
        $this->checkReusableGETCSRFParam();

        $projectId = (int) $task['project_id'];
        $ownerId   = $this->request->getIntegerParam('owner_id');

        if (! $this->helper->projectRole->canUpdateTask($task)
            || ! $this->helper->projectRole->canChangeAssignee($task)) {
            throw new AccessForbiddenException(t("You don't have the permission to change the assignee"));
        }

        $users = $this->projectUserRoleModel->getAssignableUsersList($projectId, false);

        /* 0 means unassigned, which is always allowed. Anyone else has to be
           assignable on this project: the list in the menu is built from the
           project, but the request is a URL and can say anything. */
        if ($ownerId !== 0 && ! isset($users[$ownerId])) {
            throw new AccessForbiddenException(t('That person cannot be assigned work on this project.'));
        }

        $saved = (int) $task['owner_id'] === $ownerId
            ? true
            : $this->taskModificationModel->update(array('id' => $task['id'], 'owner_id' => $ownerId));

        if (! $saved) {
            $this->response->status(400);
            return;
        }

        $this->response->json($this->describeOwner($ownerId, $users));
    }

    /**
     * Set a task's priority from the grid.
     *
     * A priority is a queue position, not a band, so this is a move rather
     * than a field write: choosing 3 puts the task third and pushes whatever
     * was third and below down by one. PriorityModel does the arithmetic over
     * the whole queue, which is why the first use on a project where sixteen
     * tasks share P5 sorts all of them out at once.
     *
     * Because other rows move too, the response asks the page to reload -
     * repainting only the pill that was clicked would leave the rest of the
     * column showing numbers that are no longer true.
     */
    public function priority()
    {
        $task = $this->getTask();
        $this->checkReusableGETCSRFParam();

        $projectId = (int) $task['project_id'];
        $rank      = $this->request->getIntegerParam('priority');

        if (! $this->helper->projectRole->canUpdateTask($task)) {
            throw new AccessForbiddenException(t("You don't have the permission to change this task"));
        }

        /* 0 is "no priority": the task leaves the queue. Anything else is a
           position, and out-of-range positions are clamped to the ends of
           the queue rather than refused - asking for 99 in a queue of 18
           plainly means "last". */
        if ($rank < 0) {
            $rank = 0;
        }

        $assigned = $this->priorityModel->moveToRank($projectId, $task['id'], $rank);

        $this->response->json(array(
            'ok'     => true,
            'label'  => $assigned > 0 ? 'P'.$assigned : t('None'),
            'class'  => 'zs-prio '.$this->helper->taskTree->getPriorityClass($assigned),
            /* Only when something actually moved. Repeating the same choice
               writes nothing and should not bounce the page. */
            'reload' => $assigned !== (int) $task['priority'],
        ));
    }

    /**
     * The same, for a subtask - which carries its own assignee rather than
     * inheriting the task's.
     */
    public function subtaskOwner()
    {
        $task = $this->getTask();
        $this->checkReusableGETCSRFParam();

        $subtaskId = $this->request->getIntegerParam('subtask_id');
        $ownerId   = $this->request->getIntegerParam('owner_id');
        $subtask   = $this->subtaskModel->getById($subtaskId);

        if (empty($subtask) || (int) $subtask['task_id'] !== (int) $task['id']) {
            throw new AccessForbiddenException(t('That subtask does not belong to this task.'));
        }

        if (! $this->helper->projectRole->canUpdateTask($task)
            || ! $this->helper->projectRole->canChangeAssignee($task)) {
            throw new AccessForbiddenException(t("You don't have the permission to change the assignee"));
        }

        $users = $this->projectUserRoleModel->getAssignableUsersList((int) $task['project_id'], false);

        if ($ownerId !== 0 && ! isset($users[$ownerId])) {
            throw new AccessForbiddenException(t('That person cannot be assigned work on this project.'));
        }

        $saved = (int) $subtask['user_id'] === $ownerId
            ? true
            : $this->subtaskModel->update(array('id' => $subtaskId, 'user_id' => $ownerId));

        if (! $saved) {
            $this->response->status(400);
            return;
        }

        $this->response->json($this->describeOwner($ownerId, $users));
    }

    /**
     * What the pill should read once the save has gone through.
     *
     * @param  integer $ownerId
     * @param  array   $users
     * @return array
     */
    protected function describeOwner($ownerId, array $users)
    {
        return array(
            'ok'    => true,
            'label' => $ownerId === 0 ? t('Unassigned') : $users[$ownerId],
            'class' => $ownerId === 0 ? 'zs-owner is-unassigned' : 'zs-owner',
        );
    }

    /**
     * Change a subtask's status from the grid.
     *
     * Subtasks carry Kanboard's own three states - Todo, In progress, Done -
     * rather than the project's columns. Columns belong to the board, and a
     * subtask never sits on one. The control is the same pill used for tasks,
     * so the two read alike even though what they set is different.
     */
    public function subtask()
    {
        $task = $this->getTask();
        $this->checkReusableGETCSRFParam();

        $subtaskId = $this->request->getIntegerParam('subtask_id');
        $status    = $this->request->getIntegerParam('status', -1);
        $subtask   = $this->subtaskModel->getById($subtaskId);

        if (empty($subtask) || (int) $subtask['task_id'] !== (int) $task['id']) {
            throw new AccessForbiddenException(t('That subtask does not belong to this task.'));
        }

        $statuses = $this->subtaskModel->getStatusList();

        if (! isset($statuses[$status])) {
            throw new AccessForbiddenException(t('Unknown status.'));
        }

        if (! $this->helper->projectRole->canUpdateTask($task)) {
            throw new AccessForbiddenException(t("You don't have the permission to change this subtask"));
        }

        $saved = $this->subtaskModel->update(array(
            'id'     => $subtaskId,
            'status' => $status,
        ));

        if (! $saved) {
            $this->response->status(400);
            return;
        }

        $this->response->json(array(
            'ok'    => true,
            'label' => $statuses[$status],
            'class' => $this->helper->taskTree->getSubtaskStatusClass($status),
        ));
    }

    /**
     * Archive or reactivate a project.
     */
    public function project()
    {
        $project = $this->getProject();
        $this->checkReusableGETCSRFParam();

        if (! $this->helper->user->hasProjectAccess('ProjectStatusController', 'disable', $project['id'])) {
            throw new AccessForbiddenException();
        }

        $state = $this->request->getStringParam('state', 'active');

        if ($state === 'closed') {
            $this->projectModel->disable($project['id']);
            $this->projectMetadataModel->set($project['id'], 'project_status', 'closed');
            $label = t('Closed');
            $class = 'status-closed';
        } elseif ($state === 'on_hold') {
            $this->projectModel->enable($project['id']);
            $this->projectMetadataModel->set($project['id'], 'project_status', 'on_hold');
            $label = t('On Hold');
            $class = 'status-rev';
        } else {
            $this->projectModel->enable($project['id']);
            $this->projectMetadataModel->set($project['id'], 'project_status', 'active');
            $label = t('Active');
            $class = 'status-wip';
        }

        $this->response->json(array(
            'ok'    => true,
            'label' => $label,
            'class' => $class,
        ));
    }
}
