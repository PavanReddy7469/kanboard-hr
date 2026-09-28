<?php

namespace Kanboard\Plugin\TaskManager\Model;

use Kanboard\Core\Base;
use Kanboard\Model\TaskModel as CoreTaskModel;

/**
 * Keeps a project's priorities contiguous.
 *
 * A priority is a queue position, and a queue with holes in it stops being
 * readable: P1, P2, P4, P7 leaves everyone guessing whether P3 and P5 were
 * finished, deleted, or never existed. Holes appear constantly - a task
 * closes, is deleted, or has its priority changed by hand - so closing them
 * has to be something that happens on its own rather than a tidy-up someone
 * remembers to do.
 *
 * What is deliberately NOT done here: numbers are not made unique. Two tasks
 * can both be P2 and stay that way. Ranking every task against every other
 * would mean renumbering nearly the whole project the first time this ran,
 * and P1-P10 would stop being a scale and become a list position.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class PriorityModel extends Base
{
    /**
     * The automatic action that switches this on, per project.
     */
    const ACTION_NAME = '\Kanboard\Plugin\TaskManager\Action\PriorityEscalation';

    /**
     * Renumber a project's priorities so the values in use run without gaps.
     *
     * Ties and order are preserved: the distinct values in use are mapped
     * onto consecutive numbers from the project's own scale start, so two
     * tasks sharing a priority still share the new one.
     *
     * Tasks with no priority (0) are left alone - the SUPERBEE scale allows
     * a task to have none, and giving one a number here would invent a
     * position nobody chose.
     *
     * @param  integer $projectId
     * @return integer  how many tasks were renumbered
     */
    public function closeGaps($projectId)
    {
        $projectId = (int) $projectId;
        $tasks     = $this->getQueuedTasks($projectId);

        if (empty($tasks)) {
            return 0;
        }

        $map     = $this->buildMap($tasks, $this->getScaleStart($projectId));
        $changed = 0;

        $this->db->startTransaction();

        foreach ($tasks as $task) {
            $old = (int) $task['priority'];

            if ($map[$old] !== $old) {
                /* A direct update, not taskModificationModel: an update fires
                   task.update, which is one of the events that brings us back
                   here. The pass is idempotent so it would settle, but a
                   renumber should not put every task it touches through the
                   notification queue. */
                $this->db
                    ->table(CoreTaskModel::TABLE)
                    ->eq('id', $task['id'])
                    ->update(array('priority' => $map[$old]));

                $changed++;
            }
        }

        $this->db->closeTransaction();

        return $changed;
    }

    /**
     * Whether this project asked for it.
     *
     * The switch is the automatic action under Project settings. Task
     * deletion fires no event in Kanboard, so the one path that cannot go
     * through the action has to read the same switch directly - otherwise
     * deleting a task would renumber projects that never opted in.
     *
     * @param  integer $projectId
     * @return boolean
     */
    public function isEnabledForProject($projectId)
    {
        return $this->db
            ->table(\Kanboard\Model\ActionModel::TABLE)
            ->eq('project_id', (int) $projectId)
            ->eq('action_name', self::ACTION_NAME)
            ->exists();
    }

    /**
     * The tasks that hold a queue position: open, not parked in a "done"
     * column, and carrying a priority at all.
     *
     * @param  integer $projectId
     * @return array
     */
    protected function getQueuedTasks($projectId)
    {
        $query = $this->db
            ->table(CoreTaskModel::TABLE)
            ->eq('project_id', $projectId)
            ->eq('is_active', CoreTaskModel::STATUS_OPEN)
            ->gt('priority', 0);

        $closedColumnId = $this->getClosedColumnId($projectId);

        if ($closedColumnId > 0) {
            $query->neq('column_id', $closedColumnId);
        }

        return $query->asc('priority')->asc('id')->findAll();
    }

    /**
     * Distinct priority in use => the consecutive number it becomes.
     *
     * @param  array   $tasks
     * @param  integer $start
     * @return array
     */
    protected function buildMap(array $tasks, $start)
    {
        $inUse = array();

        foreach ($tasks as $task) {
            $inUse[(int) $task['priority']] = true;
        }

        $inUse = array_keys($inUse);
        sort($inUse, SORT_NUMERIC);

        $map = array();

        foreach ($inUse as $index => $priority) {
            $map[$priority] = $start + $index;
        }

        return $map;
    }

    /**
     * Where a project's scale begins. Never below 1: 0 means "no priority"
     * in this fork, so renumbering into it would silently unset one.
     *
     * @param  integer $projectId
     * @return integer
     */
    protected function getScaleStart($projectId)
    {
        $project = $this->projectModel->getById($projectId);
        $start   = empty($project) ? 1 : (int) $project['priority_start'];

        return max(1, $start);
    }

    /**
     * The column a project uses for finished work, matched by name. Work
     * sitting in it is done even when the task was never formally closed,
     * so it does not hold a queue position.
     *
     * @param  integer $projectId
     * @return integer  0 when the project has no such column
     */
    protected function getClosedColumnId($projectId)
    {
        $names = array('closed', 'done', 'complete', 'completed', 'finished');

        foreach ($this->columnModel->getAll($projectId) as $column) {
            if (in_array(strtolower(trim($column['title'])), $names, true)) {
                return (int) $column['id'];
            }
        }

        return 0;
    }
}
