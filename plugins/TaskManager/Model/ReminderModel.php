<?php

namespace Kanboard\Plugin\TaskManager\Model;

use Kanboard\Core\Base;
use Kanboard\Model\ProjectModel;
use Kanboard\Model\TaskModel;
use Kanboard\Model\UserModel;

/**
 * Which tasks are worth writing to somebody about today.
 *
 * Kanboard ships one date notification: notification:overdue-tasks, which
 * fires once a task is already late and then fires again every single day
 * until somebody closes it. That is the wrong shape for a reminder - by the
 * time it arrives the date has been missed, and the repetition trains people
 * to filter the sender.
 *
 * This adds the three that are actually useful, all of them before the fact:
 *
 *   start      the task's start date is today - begin it
 *   due_soon   the due date is REMIND_BEFORE_DAYS away - there is still time
 *   due_today  the due date is today - last call
 *
 * A note on vocabulary, because it caused a misunderstanding worth recording:
 * a Kanboard TASK has a start date and a due date and nothing else. There is
 * no task end date in the schema and none in the drawer, which shows exactly
 * "Start Date" and "Due Date". An end date exists only on a PROJECT
 * (projects.end_date, a 'Y-m-d' string), and that is handled separately by
 * projectsEndingOn() and goes to managers rather than to an assignee.
 *
 * Everything here is driven by an explicit "now", never by time() reached
 * from inside - a reminder job that cannot be told what day it is cannot be
 * tested, and this one is the kind of code nobody notices is broken until
 * the mail silently stops.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class ReminderModel extends Base
{
    /**
     * Bookkeeping table: one row per reminder actually sent.
     */
    const TABLE = 'taskmanager_reminders_sent';

    const KIND_START       = 'start';
    const KIND_DUE_SOON    = 'due_soon';
    const KIND_DUE_TODAY   = 'due_today';
    const KIND_PROJECT_END = 'project_end';

    /**
     * How much warning a due date gets.
     */
    const REMIND_BEFORE_DAYS = 3;

    /**
     * The half-open window [start, end) covering the calendar day that
     * contains $timestamp, in the server's timezone.
     *
     * Kanboard stores date_due as a unix timestamp, and the time of day in it
     * is not meaningful - the date picker writes midnight, but a task edited
     * through the API or imported from elsewhere can carry any time at all.
     * Comparing timestamps directly would therefore make "due today" mean
     * "due in the next 24 hours", which is a different question and gets the
     * answer wrong twice a day.
     *
     * @param  integer $timestamp
     * @return array   array(startInclusive, endExclusive)
     */
    public static function dayBounds($timestamp)
    {
        $start = mktime(0, 0, 0, (int) date('n', $timestamp), (int) date('j', $timestamp), (int) date('Y', $timestamp));

        /* Built by calendar arithmetic rather than +86400: on the days a
           timezone changes offset, a day is 23 or 25 hours long, and the
           cheap version silently drops or doubles an hour of tasks. */
        $end = mktime(0, 0, 0, (int) date('n', $start), (int) date('j', $start) + 1, (int) date('Y', $start));

        return array($start, $end);
    }

    /**
     * Which reminder, if any, a task has earned today.
     *
     * Pure: give it a task and a day and it answers the same thing forever.
     *
     * @param  array   $task  needs date_started, date_due, owner_id
     * @param  integer $now
     * @param  integer $beforeDays
     * @return string  one of the KIND_* values, or '' for nothing to say
     */
    public static function classify(array $task, $now, $beforeDays = self::REMIND_BEFORE_DAYS)
    {
        /* Nobody to write to. An unassigned task is a planning problem, not
           something to nag a mailing list about. */
        if (empty($task['owner_id'])) {
            return '';
        }

        $due     = isset($task['date_due']) ? (int) $task['date_due'] : 0;
        $started = isset($task['date_started']) ? (int) $task['date_started'] : 0;

        list($todayStart, $todayEnd) = self::dayBounds($now);

        /* The due date wins when a task both starts and is due today, because
           it is the more urgent of the two and two emails about one task on
           one morning is how people learn to ignore the sender. */
        if ($due > 0 && $due >= $todayStart && $due < $todayEnd) {
            return self::KIND_DUE_TODAY;
        }

        if ($due > 0 && $beforeDays > 0) {
            $soon = mktime(0, 0, 0, (int) date('n', $todayStart), (int) date('j', $todayStart) + (int) $beforeDays, (int) date('Y', $todayStart));
            $soonEnd = mktime(0, 0, 0, (int) date('n', $soon), (int) date('j', $soon) + 1, (int) date('Y', $soon));

            if ($due >= $soon && $due < $soonEnd) {
                return self::KIND_DUE_SOON;
            }
        }

        if ($started > 0 && $started >= $todayStart && $started < $todayEnd) {
            return self::KIND_START;
        }

        return '';
    }

    /**
     * How many whole days until the due date, counted in calendar days
     * rather than in 86400-second blocks.
     *
     * @param  integer $due
     * @param  integer $now
     * @return integer negative when the date has passed
     */
    public static function daysUntil($due, $now)
    {
        list($todayStart) = self::dayBounds($now);
        list($dueStart)   = self::dayBounds($due);

        return (int) round(($dueStart - $todayStart) / 86400);
    }

    /**
     * Every open task in an active project that carries either date.
     *
     * One query for the whole run: the alternative is a query per kind per
     * project, and the classification is cheap once the rows are in hand.
     *
     * @return array
     */
    public function getCandidateTasks()
    {
        return $this->db->table(TaskModel::TABLE)
            ->columns(
                TaskModel::TABLE.'.id',
                TaskModel::TABLE.'.title',
                TaskModel::TABLE.'.date_due',
                TaskModel::TABLE.'.date_started',
                TaskModel::TABLE.'.project_id',
                TaskModel::TABLE.'.owner_id',
                TaskModel::TABLE.'.priority',
                ProjectModel::TABLE.'.name AS project_name',
                UserModel::TABLE.'.username AS assignee_username',
                UserModel::TABLE.'.name AS assignee_name',
                UserModel::TABLE.'.email AS assignee_email',
                UserModel::TABLE.'.notifications_enabled AS assignee_notifications'
            )
            ->join(ProjectModel::TABLE, 'id', 'project_id')
            ->join(UserModel::TABLE, 'id', 'owner_id')
            ->eq(ProjectModel::TABLE.'.is_active', 1)
            ->eq(TaskModel::TABLE.'.is_active', 1)
            ->gt(TaskModel::TABLE.'.owner_id', 0)
            ->beginOr()
            ->neq(TaskModel::TABLE.'.date_due', 0)
            ->neq(TaskModel::TABLE.'.date_started', 0)
            ->closeOr()
            ->findAll();
    }

    /**
     * Active projects whose end date is today or REMIND_BEFORE_DAYS away.
     *
     * projects.end_date is a 'Y-m-d' string, not a timestamp, so this
     * compares strings - which is exactly right for that format and avoids
     * parsing a column that may legitimately be ''.
     *
     * @param  integer $now
     * @return array
     */
    public function getProjectsEndingSoon($now)
    {
        list($todayStart) = self::dayBounds($now);

        $today = date('Y-m-d', $todayStart);
        $soon  = date('Y-m-d', mktime(0, 0, 0, (int) date('n', $todayStart), (int) date('j', $todayStart) + self::REMIND_BEFORE_DAYS, (int) date('Y', $todayStart)));

        return $this->db->table(ProjectModel::TABLE)
            ->columns('id', 'name', 'end_date', 'owner_id')
            ->eq('is_active', 1)
            ->in('end_date', array($today, $soon))
            ->findAll();
    }

    /**
     * Whether this exact reminder has already gone out today.
     *
     * The job is expected to be run by cron, and cron is re-run by hand after
     * a failure, re-run by a second crontab somebody forgot about, and run
     * twice the morning a clock goes back. Without this the first of those
     * sends everyone a duplicate and the last sends it at 1am and again at
     * 1am.
     *
     * @param  integer $taskId
     * @param  integer $userId
     * @param  string  $kind
     * @param  string  $day    'Y-m-d'
     * @return boolean
     */
    public function alreadySent($taskId, $userId, $kind, $day)
    {
        return $this->db->table(self::TABLE)
            ->eq('task_id', (int) $taskId)
            ->eq('user_id', (int) $userId)
            ->eq('kind', $kind)
            ->eq('sent_on', $day)
            ->exists();
    }

    /**
     * @param  integer $taskId
     * @param  integer $userId
     * @param  string  $kind
     * @param  string  $day
     * @return boolean
     */
    public function markSent($taskId, $userId, $kind, $day)
    {
        return $this->db->table(self::TABLE)->insert(array(
            'task_id'       => (int) $taskId,
            'user_id'       => (int) $userId,
            'kind'          => $kind,
            'sent_on'       => $day,
            'date_creation' => time(),
        ));
    }

    /**
     * Drop bookkeeping rows older than $days.
     *
     * Left alone this table grows by one row per reminder forever. Nothing
     * reads a row older than the day it was written, so keeping a month is
     * already generous - it is kept at all so that "did we mail them?" can be
     * answered when somebody says they never got it.
     *
     * @param  integer $now
     * @param  integer $days
     * @return integer rows removed
     */
    public function purgeOlderThan($now, $days = 30)
    {
        list($todayStart) = self::dayBounds($now);
        $cutoff = date('Y-m-d', mktime(0, 0, 0, (int) date('n', $todayStart), (int) date('j', $todayStart) - (int) $days, (int) date('Y', $todayStart)));

        return $this->db->table(self::TABLE)->lt('sent_on', $cutoff)->remove();
    }
}
