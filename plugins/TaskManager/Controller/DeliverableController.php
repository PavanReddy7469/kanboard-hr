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
            'max_size'    => get_upload_max_size(),
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
            'max_size'   => get_upload_max_size(),
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
     * Why the upload was refused, for the flash message. Empty when nothing
     * was attached, which is not a failure - a link is evidence too.
     *
     * @var string
     */
    protected $uploadFailure = '';

    /**
     * Store the attached report, if there is one, and return its file id.
     *
     * PHP refuses an oversized upload before any of this runs: the request
     * body is discarded and $_FILES arrives empty, with nothing to explain
     * itself. That is what "upload of documents not working" looked like,
     * and why the error codes below are reported in words rather than left
     * to a silent redirect.
     *
     * @param  array $task
     * @return integer  0 when nothing was attached
     */
    protected function storeEvidenceFile(array $task)
    {
        $this->uploadFailure = '';

        /* A request that arrived with a body but left us neither fields nor
           files is PHP saying it discarded the whole thing for exceeding
           post_max_size - there is no entry in $_FILES to read an error code
           from. Both have to be empty: $_FILES alone carries a real per-file
           error code, and that deserves its own message below rather than
           being swallowed by this one. */
        if (empty($_POST) && empty($_FILES) && ! empty($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 0) {
            $this->uploadFailure = t('That file is larger than this server accepts (%s). Nothing was saved.', $this->helper->text->bytes(get_upload_max_size()));
            return 0;
        }

        if (! isset($_FILES['evidence']) || ! is_array($_FILES['evidence'])) {
            return 0;
        }

        $file = $_FILES['evidence'];
        $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        if ($error === UPLOAD_ERR_NO_FILE) {
            return 0;
        }

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            $this->uploadFailure = t('That file is larger than this server accepts (%s). Nothing was saved.', $this->helper->text->bytes(get_upload_max_size()));
            return 0;
        }

        if ($error === UPLOAD_ERR_PARTIAL) {
            $this->uploadFailure = t('The upload was cut off before it finished. Nothing was saved.');
            return 0;
        }

        if ($error !== UPLOAD_ERR_OK || empty($file['size'])) {
            $this->uploadFailure = t('Unable to store that file. Nothing was saved.');
            return 0;
        }

        $before = $this->taskFileModel->getAll($task['id']);
        $known  = array();

        foreach ($before as $row) {
            $known[(int) $row['id']] = true;
        }

        /* uploadFiles() wants the shape of a multi-file field, and returns
           only true or false - so the id of what it just stored is found by
           looking at what is on the task now that was not a moment ago. */
        $stored = $this->taskFileModel->uploadFiles($task['id'], array(
            'name'     => array($file['name']),
            'tmp_name' => array($file['tmp_name']),
            'size'     => array($file['size']),
            'error'    => array($file['error']),
        ));

        if (! $stored) {
            $this->uploadFailure = t('Unable to store that file - check the permissions of the data folder. Nothing was saved.');
            return 0;
        }

        foreach ($this->taskFileModel->getAll($task['id']) as $row) {
            if (! isset($known[(int) $row['id']])) {
                return (int) $row['id'];
            }
        }

        return 0;
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

        /* Evidence can arrive either way now. An attached file goes through
           Kanboard's own task-file model, so it lands beside everything else
           on the task rather than in a second store with its own rules, and
           the submission simply records which file it was. */
        $values['file_id'] = $this->storeEvidenceFile($task);

        $hasLink = $this->deliverableModel->normaliseUrl(isset($values['url']) ? $values['url'] : '') !== '';
        $hasFile = $values['file_id'] > 0;

        if ($this->uploadFailure !== '') {
            $this->flash->failure($this->uploadFailure);
        } elseif (! $hasLink && ! $hasFile) {
            $this->flash->failure(t('Attach the document, or paste a link to it - a report needs one or the other.'));
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
