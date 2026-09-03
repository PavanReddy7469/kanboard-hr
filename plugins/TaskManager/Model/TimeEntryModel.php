<?php

namespace Kanboard\Plugin\TaskManager\Model;

use Kanboard\Core\Base;
use Kanboard\Model\SubtaskModel;
use Kanboard\Model\TaskModel;

/**
 * Individual hours logged against a task on a given day.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class TimeEntryModel extends Base
{
    const TABLE = 'taskmanager_time_entries';

    /**
     * Every entry for one person, project and week.
     *
     * @param  integer $userId
     * @param  integer $projectId
     * @param  integer $weekStart
     * @return array
     */
    public function getWeek($userId, $projectId, $weekStart)
    {
        $weekEnd = (int) strtotime('+7 day', $weekStart);

        return $this->db->table(self::TABLE)
            ->eq('user_id', $userId)
            ->eq('project_id', $projectId)
            ->gte('work_date', $weekStart)
            ->lt('work_date', $weekEnd)
            ->asc('task_id')
            ->asc('work_date')
            ->findAll();
    }

    /**
     * The week shaped for the grid: one row per task, hours keyed by day.
     *
     * @param  integer $userId
     * @param  integer $projectId
     * @param  integer $weekStart
     * @return array
     */
    public function getWeekGrid($userId, $projectId, $weekStart)
    {
        $rows = array();

        foreach ($this->getWeek($userId, $projectId, $weekStart) as $entry) {
            $taskId = (int) $entry['task_id'];

            if (! isset($rows[$taskId])) {
                $task = $this->taskFinderModel->getById($taskId);

                $rows[$taskId] = array(
                    'task_id'     => $taskId,
                    'title'       => empty($task) ? t('Deleted task') : $task['title'],
                    'is_billable' => (int) $entry['is_billable'],
                    'days'        => array(),
                    'total'       => 0.0,
                );
            }

            $day = (int) $entry['work_date'];
            $rows[$taskId]['days'][$day] = (float) $entry['hours'];
            $rows[$taskId]['total'] += (float) $entry['hours'];
        }

        return $rows;
    }

    /**
     * Replace a week's entries with what the grid submitted.
     *
     * Cells arrive as hours[task_id][work_date]. A blank or zero cell removes
     * the entry rather than storing a zero, so the grid and the table agree
     * on what "nothing logged" looks like.
     *
     * @param  integer $userId
     * @param  integer $projectId
     * @param  integer $weekStart
     * @param  array   $cells
     * @param  array   $billable   task_id => "1" for billable rows
     * @return boolean
     */
    public function saveWeek($userId, $projectId, $weekStart, array $cells, array $billable)
    {
        $timesheetId = $this->timesheetModel->getOrCreateId($userId, $projectId, $weekStart);
        $days        = $this->timesheetModel->getWeekDays($weekStart);
        $validDays   = array_flip($days);

        $this->db->startTransaction();

        try {
            $weekEnd = (int) strtotime('+7 day', $weekStart);

            $this->db->table(self::TABLE)
                ->eq('user_id', $userId)
                ->eq('project_id', $projectId)
                ->gte('work_date', $weekStart)
                ->lt('work_date', $weekEnd)
                ->remove();

            foreach ($cells as $taskId => $dayValues) {
                $taskId = (int) $taskId;
                $task   = $this->taskFinderModel->getById($taskId);

                if (empty($task) || $task['project_id'] != $projectId) {
                    continue;
                }

                $isBillable = empty($billable[$taskId]) ? 0 : 1;

                foreach ($dayValues as $workDate => $hours) {
                    $workDate = (int) $workDate;
                    $hours    = round((float) str_replace(',', '.', $hours), 2);

                    if (! isset($validDays[$workDate]) || $hours <= 0) {
                        continue;
                    }

                    $this->db->table(self::TABLE)->insert(array(
                        'user_id'       => (int) $userId,
                        'task_id'       => $taskId,
                        'project_id'    => (int) $projectId,
                        'timesheet_id'  => $timesheetId,
                        'work_date'     => $workDate,
                        'hours'         => $hours,
                        'is_billable'   => $isBillable,
                        'date_creation' => time(),
                    ));
                }
            }

            $this->db->closeTransaction();
        } catch (\Exception $e) {
            $this->db->cancelTransaction();
            return false;
        }

        $this->syncTaskTotals($projectId);

        return true;
    }

    /**
     * Totals for the summary tiles.
     *
     * @param  integer $userId
     * @param  integer $projectId
     * @param  integer $weekStart
     * @return array
     */
    public function getWeekSummary($userId, $projectId, $weekStart)
    {
        $summary = array(
            'total'        => 0.0,
            'billable'     => 0.0,
            'non_billable' => 0.0,
            'by_day'       => array(),
        );

        foreach ($this->timesheetModel->getWeekDays($weekStart) as $day) {
            $summary['by_day'][$day] = 0.0;
        }

        foreach ($this->getWeek($userId, $projectId, $weekStart) as $entry) {
            $hours = (float) $entry['hours'];
            $day   = (int) $entry['work_date'];

            $summary['total'] += $hours;
            $summary[$entry['is_billable'] ? 'billable' : 'non_billable'] += $hours;

            if (isset($summary['by_day'][$day])) {
                $summary['by_day'][$day] += $hours;
            }
        }

        return $summary;
    }

    /**
     * Rows for the CSV export.
     *
     * @param  integer $projectId
     * @param  integer $from
     * @param  integer $to
     * @return array
     */
    public function getExportRows($projectId, $from, $to)
    {
        $rows = array(array('Date', 'User', 'Task ID', 'Task', 'Hours', 'Billable', 'Note'));
        $users = $this->userModel->getActiveUsersList();

        $entries = $this->db->table(self::TABLE)
            ->eq('project_id', $projectId)
            ->gte('work_date', $from)
            ->lte('work_date', $to)
            ->asc('work_date')
            ->asc('user_id')
            ->findAll();

        foreach ($entries as $entry) {
            $task = $this->taskFinderModel->getById($entry['task_id']);

            $rows[] = array(
                date('Y-m-d', $entry['work_date']),
                isset($users[$entry['user_id']]) ? $users[$entry['user_id']] : $entry['user_id'],
                $entry['task_id'],
                empty($task) ? '' : $task['title'],
                (float) $entry['hours'],
                $entry['is_billable'] ? 'yes' : 'no',
                (string) $entry['note'],
            );
        }

        return $rows;
    }

    /**
     * Push the sum of logged hours back onto tasks.time_spent so the rest of
     * Kanboard (board tooltips, analytics) keeps agreeing with the timesheet.
     *
     * @param  integer $projectId
     * @return void
     */
    public function syncTaskTotals($projectId)
    {
        $totals = array();

        $entries = $this->db->table(self::TABLE)
            ->columns('task_id', 'SUM(hours) AS total')
            ->eq('project_id', $projectId)
            ->groupBy('task_id')
            ->findAll();

        foreach ($entries as $row) {
            $totals[(int) $row['task_id']] = (float) $row['total'];
        }

        foreach ($totals as $taskId => $total) {
            $this->db->table(TaskModel::TABLE)->eq('id', $taskId)->update(array('time_spent' => $total));
        }
    }

    /**
     * One-off import of Kanboard's existing subtask timer records.
     *
     * Only runs for rows that have not been imported already, so it is safe
     * to run twice.
     *
     * @param  integer $projectId
     * @return integer  number of entries created
     */
    public function backfillFromSubtaskTimeTracking($projectId)
    {
        $imported = 0;

        $records = $this->db->table('subtask_time_tracking')
            ->columns(
                'subtask_time_tracking.id',
                'subtask_time_tracking.user_id',
                'subtask_time_tracking.start',
                'subtask_time_tracking.time_spent',
                SubtaskModel::TABLE.'.task_id'
            )
            ->join(SubtaskModel::TABLE, 'id', 'subtask_id')
            ->gt('subtask_time_tracking.time_spent', 0)
            ->findAll();

        foreach ($records as $record) {
            $task = $this->taskFinderModel->getById($record['task_id']);

            if (empty($task) || $task['project_id'] != $projectId) {
                continue;
            }

            $workDate = (int) strtotime('today', $record['start'] ?: time());
            $note     = 'Imported from subtask timer #'.$record['id'];

            $exists = $this->db->table(self::TABLE)->eq('note', $note)->exists();

            if ($exists) {
                continue;
            }

            $weekStart = $this->timesheetModel->getWeekStart($workDate);

            $this->db->table(self::TABLE)->insert(array(
                'user_id'       => (int) $record['user_id'],
                'task_id'       => (int) $record['task_id'],
                'project_id'    => (int) $projectId,
                'timesheet_id'  => $this->timesheetModel->getOrCreateId($record['user_id'], $projectId, $weekStart),
                'work_date'     => $workDate,
                'hours'         => round((float) $record['time_spent'], 2),
                'is_billable'   => 1,
                'note'          => $note,
                'date_creation' => time(),
            ));

            $imported++;
        }

        if ($imported > 0) {
            $this->syncTaskTotals($projectId);
        }

        return $imported;
    }
}
