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
