<?php

namespace Kanboard\Plugin\TaskManager\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Core\Security\Role;
use Kanboard\Plugin\TaskManager\Model\DeliverableModel;

/**
 * Completion evidence and its verification/approval: the project's Reports tab.
 *
 * Assignees submit a link to the finished work - a Google Drive document, a repo, a
 * live URL - and an admin or project manager approves or sends it back. Until
 * something is approved the task cannot be closed; the rule itself lives in
 * TaskManager\Model\TaskStatusModel.
 *
 * @package Kanboard\Plugin\TaskManager\Controller
 */
class DeliverableController extends BaseController
{
    /**
     * Check CSRF from either POST body or GET query parameters
     */
    protected function checkCSRF()
    {
        $token = $this->request->getStringParam('csrf_token') ?: $this->request->getRawValue('csrf_token');
        if (! $this->token->validateCSRFToken($token) && ! $this->token->validateReusableCSRFToken($token)) {
            throw new AccessForbiddenException();
        }
    }

    /**
     * The project's review queue.
     */
    public function index()
    {
        $project = $this->getProject();
        $status  = $this->request->getStringParam('status');
        $userId  = $this->userSession->getId();

        if (! array_key_exists($status, DeliverableModel::getStatusList())) {
            $status = '';
        }

        $this->response->html($this->helper->layout->app('TaskManager:deliverable/index', array(
            'project'     => $project,
            'title'       => $project['name'],
            'description' => $this->helper->projectHeader->getDescription($project),
            'rows'        => $this->deliverableModel->getAllByProject($project['id'], $status),
            'counts'      => $this->deliverableModel->getStatusCounts($project['id']),
            'statuses'    => DeliverableModel::getStatusList(),
            'filter'      => $status,
            'can_approve' => $this->canApprove($project),
            'open_tasks'  => $this->deliverableModel->getTasksAwaitingEvidence($project['id']),
            'values'      => array('task_id' => $this->request->getIntegerParam('task_id')),
            'errors'      => array(),
        )));
    }

    /**
     * The submission form, as a modal from a task or the Reports tab.
     */
    public function create()
    {
        $project = $this->getProject();

        $this->response->html($this->template->render('TaskManager:deliverable/create', array(
            'project'    => $project,
            'open_tasks' => $this->deliverableModel->getTasksAwaitingEvidence($project['id']),
            'values'     => array('task_id' => $this->request->getIntegerParam('task_id')),
            'errors'     => array(),
        )));
    }

    /**
     * Modal to enter review notes when requesting changes.
     */
    public function reviewModal()
    {
        $project = $this->getProject();
        $id = $this->request->getIntegerParam('deliverable_id');
        $deliverable = $this->deliverableModel->getById($id);

        if (empty($deliverable) || (int) $deliverable['project_id'] !== (int) $project['id']) {
            throw new AccessForbiddenException();
        }

        $this->response->html($this->template->render('TaskManager:deliverable/review_modal', array(
            'project'     => $project,
            'deliverable' => $deliverable,
        )));
    }

    /**
     * Record a submission from an assignee.
     */
    public function save()
    {
        $project = $this->getProject();
        $this->checkCSRF();

        $values = $this->request->getValues();
        $taskId = isset($values['task_id']) ? (int) $values['task_id'] : 0;
        $task   = $taskId > 0 ? $this->taskFinderModel->getById($taskId) : array();

        if (empty($task) || (int) $task['project_id'] !== (int) $project['id']) {
            $this->flash->failure(t('That task does not belong to this project.'));
            $this->response->redirect($this->helper->url->to('DeliverableController', 'index', array('plugin' => 'TaskManager', 'project_id' => $project['id'])));
            return;
        }

        if ($this->deliverableModel->normaliseUrl(isset($values['url']) ? $values['url'] : '') === '') {
            $this->flash->failure(t('Enter a valid link (e.g. Google Drive, GitHub, Live URL).'));
        } elseif ($this->deliverableModel->submit($taskId, $this->userSession->getId(), $values)) {
            // Automatically move task to 'Rev' (In Review) column if present
            $columns = $this->columnModel->getAll($project['id']);
            $revColId = 0;
            foreach ($columns as $col) {
                $titleLower = strtolower($col['title']);
                if ($titleLower === 'rev' || $titleLower === 'review' || strstr($titleLower, 'review')) {
                    $revColId = (int) $col['id'];
                    break;
                }
            }

            if ($revColId > 0 && (int) $task['column_id'] !== $revColId) {
                $this->taskPositionModel->movePosition($project['id'], $taskId, $revColId, 1, $task['swimlane_id']);
            }

            $this->flash->success(t('Completion report submitted for Admin verification.'));
        } else {
            $this->flash->failure(t('Unable to submit this completion report.'));
        }

        $this->response->redirect($this->helper->url->to('DeliverableController', 'index', array('plugin' => 'TaskManager', 'project_id' => $project['id'])));
    }

    /**
     * Approve a submission, which opens the gate on its task and marks it completed.
     */
    public function approve()
    {
        $this->decide(DeliverableModel::STATUS_APPROVED, t('Completion report approved! Task has been verified and marked as Completed.'));
    }

    /**
     * Send a submission back for rework.
     */
    public function reject()
    {
        $this->decide(DeliverableModel::STATUS_REJECTED, t('Changes requested. The task has been moved back to WIP for assignee revision.'));
    }

    /**
     * @param  string $status
     * @param  string $message
     */
    protected function decide($status, $message)
    {
        $project = $this->getProject();
        $this->checkCSRF();

        if (! $this->canApprove($project)) {
            throw new AccessForbiddenException(t('Only an administrator or a project manager can approve a completion report.'));
        }

        $id  = $this->request->getIntegerParam('deliverable_id');
        $row = $this->deliverableModel->getById($id);

        if (empty($row) || (int) $row['project_id'] !== (int) $project['id']) {
            $this->flash->failure(t('That submission does not belong to this project.'));
            $this->response->redirect($this->helper->url->to('DeliverableController', 'index', array('plugin' => 'TaskManager', 'project_id' => $project['id'])));
            return;
        }

        $reviewNote = $this->request->getRawValue('review_note') ?: $this->request->getStringParam('review_note', '');

        if ($this->deliverableModel->decide($id, $status, $this->userSession->getId(), $reviewNote)) {
            $taskId = (int) $row['task_id'];
            $task   = $this->taskFinderModel->getById($taskId);

            if (! empty($task)) {
                $columns = $this->columnModel->getAll($project['id']);

                if ($status === DeliverableModel::STATUS_APPROVED) {
                    // Find 'Closed' column
                    $closedColId = 0;
                    foreach ($columns as $col) {
                        if (strtolower($col['title']) === 'closed') {
                            $closedColId = (int) $col['id'];
                            break;
                        }
                    }

                    if ($closedColId > 0 && (int) $task['column_id'] !== $closedColId) {
                        $this->taskPositionModel->movePosition($project['id'], $taskId, $closedColId, 1, $task['swimlane_id']);
                    }

                    // Close the task
                    $this->taskStatusModel->close($taskId);
                } elseif ($status === DeliverableModel::STATUS_REJECTED) {
                    /* Withdrawing the approval has to withdraw the closure too.
                       A task is only allowed to sit closed while an approved
                       deliverable backs it; rejecting the one that justified
                       closing it would otherwise leave the task complete with
                       nothing approved - exactly the state this whole feature
                       exists to prevent. Reopen before moving, so the card is
                       moved as an open task. */
                    if (! $this->deliverableModel->hasApproved($taskId) && empty($task['is_active'])) {
                        $this->taskStatusModel->open($taskId);
                    }

                    // Move back to WIP
                    $wipColId = 0;
                    foreach ($columns as $col) {
                        $titleLower = strtolower($col['title']);
                        if ($titleLower === 'wip' || $titleLower === 'in progress' || $titleLower === 'work in progress') {
                            $wipColId = (int) $col['id'];
                            break;
                        }
                    }

                    if ($wipColId > 0 && (int) $task['column_id'] !== $wipColId) {
                        $this->taskPositionModel->movePosition($project['id'], $taskId, $wipColId, 1, $task['swimlane_id']);
                    }
                }
            }

            $this->flash->success($message);
        } else {
            $this->flash->failure(t('Unable to record that decision.'));
        }

        $this->response->redirect($this->helper->url->to('DeliverableController', 'index', array('plugin' => 'TaskManager', 'project_id' => $project['id'])));
    }

    /**
     * Approving is for application admins and this project's managers.
     *
     * @param  array $project
     * @return boolean
     */
    protected function canApprove(array $project)
    {
        if ($this->userSession->isAdmin()) {
            return true;
        }

        return $this->helper->projectRole->getProjectUserRole($project['id']) === Role::PROJECT_MANAGER;
    }
}
