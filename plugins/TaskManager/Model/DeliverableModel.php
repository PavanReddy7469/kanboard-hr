<?php

namespace Kanboard\Plugin\TaskManager\Model;

use Kanboard\Core\Base;
use Kanboard\Model\ProjectModel as CoreProjectModel;
use Kanboard\Model\TaskModel;
use Kanboard\Model\UserModel;

/**
 * Completion evidence: the link an assignee submits to show a task is done,
 * and the decision an approver makes on it.
 *
 * The deliverable itself is never uploaded here - it stays in Drive, a repo or
 * wherever it lives, and this records only the pointer and the decision. That
 * keeps the Kanboard host out of the business of storing other people's
 * documents, and means a link stays current when its owner edits the file.
 *
 * A task may collect several submissions over its life: rejected, reworked,
 * resubmitted. The closure gate asks one question of that history - is any of
 * them approved.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class DeliverableModel extends Base
{
    const TABLE = 'taskmanager_deliverables';

    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * @return array
     */
    public static function getStatusList()
    {
        return array(
            self::STATUS_PENDING  => t('Awaiting review'),
            self::STATUS_APPROVED => t('Approved'),
            self::STATUS_REJECTED => t('Changes requested'),
        );
    }

    /**
     * Record a submission against a task. Always lands as pending: submitting
     * is never self-approval, even when an admin does it.
     *
     * @param  integer $taskId
     * @param  integer $userId  the person submitting
     * @param  array   $values  url, title, note
     * @return integer|boolean  the new id, or false
     */
    public function submit($taskId, $userId, array $values)
    {
        $task = $this->taskFinderModel->getById($taskId);

        if (empty($task)) {
            return false;
        }

        $url = $this->normaliseUrl(isset($values['url']) ? $values['url'] : '');

        if ($url === '') {
            return false;
        }

        return $this->db->table(self::TABLE)->persist(array(
            'task_id'        => (int) $taskId,
            'project_id'     => (int) $task['project_id'],
            'user_id'        => (int) $userId,
            'title'          => isset($values['title']) ? trim($values['title']) : '',
            'url'            => $url,
            'note'           => isset($values['note']) ? trim($values['note']) : '',
            'status'         => self::STATUS_PENDING,
            'reviewer_id'    => 0,
            'review_note'    => '',
            'date_submitted' => time(),
            'date_reviewed'  => 0,
        ));
    }

    /**
     * A bare "drive.google.com/..." pasted from the address bar is not a
     * usable href; it would resolve against our own host. Anything without a
     * scheme gets https, and anything that is not http(s) is refused outright
     * so a submission can never carry a javascript: payload.
     *
     * @param  string $url
     * @return string  the usable URL, or '' if it cannot be one
     */
    public function normaliseUrl($url)
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        if (! preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
            $url = 'https://'.$url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, array('http', 'https'), true)) {
            return '';
        }

        return parse_url($url, PHP_URL_HOST) === null ? '' : $url;
    }

    /**
     * Approve or reject a submission.
     *
     * @param  integer $id
     * @param  string  $status
     * @param  integer $reviewerId
     * @param  string  $note
     * @return boolean
     */
    public function decide($id, $status, $reviewerId, $note = '')
    {
        if (! in_array($status, array(self::STATUS_APPROVED, self::STATUS_REJECTED), true)) {
            return false;
        }

        return $this->db->table(self::TABLE)->eq('id', (int) $id)->update(array(
            'status'        => $status,
            'reviewer_id'   => (int) $reviewerId,
            'review_note'   => trim($note),
            'date_reviewed' => time(),
        ));
    }

    /**
     * The question the closure gate asks.
     *
     * @param  integer $taskId
     * @return boolean
     */
    public function hasApproved($taskId)
    {
        return $this->db->table(self::TABLE)
            ->eq('task_id', (int) $taskId)
            ->eq('status', self::STATUS_APPROVED)
            ->count() > 0;
    }

    /**
     * @param  integer $id
     * @return array
     */
    public function getById($id)
    {
        return $this->db->table(self::TABLE)->eq('id', (int) $id)->findOne();
    }

    /**
     * Everything submitted against one task, newest first.
     *
     * @param  integer $taskId
     * @return array
     */
    public function getAllByTask($taskId)
    {
        return $this->decorate($this->db->table(self::TABLE)
            ->eq('task_id', (int) $taskId)
            ->desc('date_submitted')
            ->desc('id')
            ->findAll());
    }

    /**
     * The project's review queue. Pending first - that is the work - then the
     * decided ones, most recent decision first.
     *
     * @param  integer $projectId
     * @param  string  $status  optional filter
     * @return array
     */
    public function getAllByProject($projectId, $status = '')
    {
        $query = $this->db->table(self::TABLE)->eq('project_id', (int) $projectId);

        if ($status !== '') {
            $query->eq('status', $status);
        }

        $rows = $this->decorate($query->findAll());

        usort($rows, function ($a, $b) {
            $aPending = $a['status'] === self::STATUS_PENDING ? 0 : 1;
            $bPending = $b['status'] === self::STATUS_PENDING ? 0 : 1;

            if ($aPending !== $bPending) {
                return $aPending - $bPending;
            }

            return $b['date_submitted'] - $a['date_submitted'];
        });

        return $rows;
    }

    /**
     * status => count, for the filter chips. Always has all three keys.
     *
     * @param  integer $projectId
     * @return array
     */
    public function getStatusCounts($projectId)
    {
        $counts = array(self::STATUS_PENDING => 0, self::STATUS_APPROVED => 0, self::STATUS_REJECTED => 0);

        $rows = $this->db->table(self::TABLE)
            ->columns('status', 'COUNT(*) AS total')
            ->eq('project_id', (int) $projectId)
            ->groupBy('status')
            ->findAll();

        foreach ($rows as $row) {
            if (isset($counts[$row['status']])) {
                $counts[$row['status']] = (int) $row['total'];
            }
        }

        return $counts;
    }

    /**
     * Open tasks in the project that have nothing approved yet, so an assignee
     * can pick one to submit against without hunting through the board.
     *
     * @param  integer $projectId
     * @param  integer $userId  0 for everyone's
     * @return array
     */
    public function getTasksAwaitingEvidence($projectId, $userId = 0)
    {
        $query = $this->db->table(TaskModel::TABLE)
            ->columns(TaskModel::TABLE.'.id', TaskModel::TABLE.'.title', TaskModel::TABLE.'.owner_id')
            ->eq(TaskModel::TABLE.'.project_id', (int) $projectId)
            ->eq(TaskModel::TABLE.'.is_active', TaskModel::STATUS_OPEN);

        if ($userId > 0) {
            $query->eq(TaskModel::TABLE.'.owner_id', (int) $userId);
        }

        $tasks = $query->asc(TaskModel::TABLE.'.id')->findAll();

        if (empty($tasks)) {
            return array();
        }

        $approved = array();

        foreach ($this->db->table(self::TABLE)
                     ->columns('task_id')
                     ->eq('project_id', (int) $projectId)
                     ->eq('status', self::STATUS_APPROVED)
                     ->findAll() as $row) {
            $approved[(int) $row['task_id']] = true;
        }

        $out = array();

        foreach ($tasks as $task) {
            if (! isset($approved[(int) $task['id']])) {
                $out[] = $task;
            }
        }

        return $out;
    }

    /**
     * Attach the names the templates need, in two queries rather than one per
     * row.
     *
     * @param  array $rows
     * @return array
     */
    protected function decorate(array $rows)
    {
        if (empty($rows)) {
            return array();
        }

        $users = $this->db->table(UserModel::TABLE)->columns('id', 'username', 'name')->findAll();
        $names = array();

        foreach ($users as $user) {
            $names[(int) $user['id']] = $user['name'] !== '' && $user['name'] !== null ? $user['name'] : $user['username'];
        }

        $taskIds = array();

        foreach ($rows as $row) {
            $taskIds[(int) $row['task_id']] = (int) $row['task_id'];
        }

        $tasks = $this->db->table(TaskModel::TABLE)
            ->columns('id', 'title', 'is_active', 'project_id')
            ->in('id', array_values($taskIds))
            ->findAll();

        $taskById = array();

        foreach ($tasks as $task) {
            $taskById[(int) $task['id']] = $task;
        }

        foreach ($rows as $index => $row) {
            $taskId = (int) $row['task_id'];
            $rows[$index]['id']             = (int) $row['id'];
            $rows[$index]['task_id']        = $taskId;
            $rows[$index]['project_id']     = (int) $row['project_id'];
            $rows[$index]['user_id']        = (int) $row['user_id'];
            $rows[$index]['reviewer_id']    = (int) $row['reviewer_id'];
            $rows[$index]['date_submitted'] = (int) $row['date_submitted'];
            $rows[$index]['date_reviewed']  = (int) $row['date_reviewed'];
            $rows[$index]['submitter']      = isset($names[(int) $row['user_id']]) ? $names[(int) $row['user_id']] : '';
            $rows[$index]['reviewer']       = isset($names[(int) $row['reviewer_id']]) ? $names[(int) $row['reviewer_id']] : '';
            $rows[$index]['task_title']     = isset($taskById[$taskId]) ? $taskById[$taskId]['title'] : '';
            $rows[$index]['task_is_active'] = isset($taskById[$taskId]) ? (int) $taskById[$taskId]['is_active'] : 1;
        }

        return $rows;
    }
}
