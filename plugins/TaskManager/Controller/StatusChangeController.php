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
