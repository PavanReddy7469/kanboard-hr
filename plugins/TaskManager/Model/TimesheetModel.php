<?php

namespace Kanboard\Plugin\TaskManager\Model;

use Kanboard\Core\Base;

/**
 * The weekly envelope around a person's time entries on one project.
 *
 * One sheet per person, per project, per week. Submitting locks the cells so
 * a week cannot be edited out from under the approver.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class TimesheetModel extends Base
{
    const TABLE = 'taskmanager_timesheets';

    const STATE_DRAFT     = 'draft';
    const STATE_SUBMITTED = 'submitted';
    const STATE_APPROVED  = 'approved';
    const STATE_REJECTED  = 'rejected';

    /**
     * @return array
     */
    public function getStates()
    {
        return array(
            self::STATE_DRAFT     => t('Draft'),
            self::STATE_SUBMITTED => t('Submitted'),
            self::STATE_APPROVED  => t('Approved'),
            self::STATE_REJECTED  => t('Rejected'),
        );
    }

    /**
     * Monday 00:00 of the week containing the given timestamp.
     *
     * @param  integer|null $timestamp
     * @return integer
     */
    public function getWeekStart($timestamp = null)
    {
        $timestamp = $timestamp ?: time();

        return (int) strtotime('monday this week', $timestamp);
    }

    /**
     * The seven day timestamps of a week, Monday first.
     *
     * @param  integer $weekStart
     * @return array
     */
    public function getWeekDays($weekStart)
    {
        $days = array();

        for ($i = 0; $i < 7; $i++) {
            $days[] = (int) strtotime('+'.$i.' day', $weekStart);
        }

        return $days;
    }

    /**
     * @param  integer $userId
     * @param  integer $projectId
     * @param  integer $weekStart
     * @return array|null
     */
    public function find($userId, $projectId, $weekStart)
    {
        return $this->db->table(self::TABLE)
            ->eq('user_id', $userId)
            ->eq('project_id', $projectId)
            ->eq('week_start', $weekStart)
            ->findOne();
    }

    /**
     * Existing sheet, or a synthetic draft when nothing has been saved yet.
     *
     * @param  integer $userId
     * @param  integer $projectId
     * @param  integer $weekStart
     * @return array
     */
    public function getOrDraft($userId, $projectId, $weekStart)
    {
        $sheet = $this->find($userId, $projectId, $weekStart);

        if (! empty($sheet)) {
            return $sheet;
        }

        return array(
            'id'          => 0,
            'user_id'     => (int) $userId,
            'project_id'  => (int) $projectId,
            'week_start'  => (int) $weekStart,
            'state'       => self::STATE_DRAFT,
            'approver_id' => 0,
            'decided_at'  => 0,
            'note'        => '',
        );
    }

    /**
     * @param  integer $userId
     * @param  integer $projectId
     * @param  integer $weekStart
     * @return integer  the sheet id
     */
    public function getOrCreateId($userId, $projectId, $weekStart)
    {
        $sheet = $this->find($userId, $projectId, $weekStart);

        if (! empty($sheet)) {
            return (int) $sheet['id'];
        }

        return (int) $this->db->table(self::TABLE)->persist(array(
            'user_id'    => (int) $userId,
            'project_id' => (int) $projectId,
            'week_start' => (int) $weekStart,
            'state'      => self::STATE_DRAFT,
        ));
    }

    /**
     * Submitted and approved weeks are read-only.
     *
     * @param  array $sheet
     * @return boolean
     */
    public function isLocked(array $sheet)
    {
        return in_array($sheet['state'], array(self::STATE_SUBMITTED, self::STATE_APPROVED), true);
    }

    /**
     * @param  integer $userId
     * @param  integer $projectId
     * @param  integer $weekStart
     * @return boolean
     */
    public function submit($userId, $projectId, $weekStart)
    {
        $id = $this->getOrCreateId($userId, $projectId, $weekStart);

        return $this->db->table(self::TABLE)->eq('id', $id)->update(array(
            'state'       => self::STATE_SUBMITTED,
            'approver_id' => 0,
            'decided_at'  => 0,
        ));
    }

    /**
     * @param  integer $timesheetId
     * @param  integer $approverId
     * @param  string  $state
     * @param  string  $note
     * @return boolean
     */
    public function decide($timesheetId, $approverId, $state, $note = '')
    {
        if (! in_array($state, array(self::STATE_APPROVED, self::STATE_REJECTED), true)) {
            return false;
        }

        return $this->db->table(self::TABLE)->eq('id', $timesheetId)->update(array(
            'state'       => $state,
            'approver_id' => (int) $approverId,
            'decided_at'  => time(),
            'note'        => $note,
        ));
    }

    /**
     * Sheets awaiting a decision on a project.
     *
     * @param  integer $projectId
     * @return array
     */
    public function getPendingByProject($projectId)
    {
        return $this->db->table(self::TABLE)
            ->eq('project_id', $projectId)
            ->eq('state', self::STATE_SUBMITTED)
            ->asc('week_start')
            ->findAll();
    }

    /**
     * @param  integer $timesheetId
     * @return array|null
     */
    public function getById($timesheetId)
    {
        return $this->db->table(self::TABLE)->eq('id', $timesheetId)->findOne();
    }
}
