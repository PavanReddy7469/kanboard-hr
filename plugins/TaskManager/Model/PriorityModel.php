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
 * Priorities are also unique. A number is a queue position, so two tasks
 * cannot hold the same one: setting a task to 3 puts it third and pushes
 * whatever was third and below down by one. This was deliberately not done
 * at first, on the grounds that it turns P1-P10 from a scale into a list
 * position and renumbers most of the project the first time it runs. Both
 * of those are true; they are now the point rather than the objection,
 * because a scale on which sixteen of eighteen tasks read P5 is not
 * ordering anything.
 *
 * The renumber happens when somebody sets a priority, not on page load. A
 * GET that quietly rewrites eighteen rows is a surprise nobody asked for,
 * and the first deliberate change tidies the whole queue anyway.
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
     * Move a task to a queue position, pushing everything at or below that
     * position down by one, and renumber the queue so the values run 1..N
     * with no repeats and no gaps.
     *
     * Works whatever state the queue was in. The first call on a project
     * where sixteen tasks share P5 sorts all of them out, because the result
     * is computed from the whole ordered queue rather than patched into it.
     *
     * @param  integer $projectId
     * @param  integer $taskId
     * @param  integer $rank   the position asked for; clamped to the queue
     * @return array    rank: the position actually given, changed: how many
     *                  rows moved - which is not the same question. Picking
     *                  the position a task already holds leaves its own
     *                  number alone while still breaking every tie behind
     *                  it, so the caller cannot infer one from the other.
     */
    public function moveToRank($projectId, $taskId, $rank)
    {
        $projectId = (int) $projectId;
        $taskId    = (int) $taskId;
        $start     = $this->getScaleStart($projectId);

        $ordered = array();

        foreach ($this->getQueuedTasks($projectId) as $task) {
            $ordered[] = (int) $task['id'];
        }

        $assignments = self::reorder($ordered, $taskId, (int) $rank, $start);

        /* Read the current numbers once so only the rows that actually move
           are written. On a settled queue a repeat of the same choice writes
           nothing at all. */
        $current = array();

        foreach ($this->db->table(CoreTaskModel::TABLE)->eq('project_id', $projectId)->columns('id', 'priority')->findAll() as $row) {
            $current[(int) $row['id']] = (int) $row['priority'];
        }

        $this->db->startTransaction();
        $changed = 0;

        foreach ($assignments as $id => $priority) {
            if (! isset($current[$id]) || $current[$id] !== $priority) {
                $changed++;
                /* Direct update, not taskModificationModel: a renumber of
                   fifteen bystanders should not put fifteen notifications
                   through the queue. Same reasoning as closeGaps(). */
                $this->db->table(CoreTaskModel::TABLE)->eq('id', $id)->update(array('priority' => $priority));
            }
        }

        $this->db->closeTransaction();

        /* A queue of eighteen needs a scale that reaches eighteen, or the
           Edit form's own dropdown - which is built from the project's range
           - could not show the number this just assigned. The scale only
           ever grows. */
        $this->widenScale($projectId, $start + max(0, count($assignments) - 1));

        return array(
            'rank'    => isset($assignments[$taskId]) ? $assignments[$taskId] : 0,
            'changed' => $changed,
        );
    }

    /**
     * The arithmetic, with no database in it.
     *
     * @param  array   $orderedIds  the queue as it stands, best first
     * @param  integer $taskId      the task being moved
     * @param  integer $rank        the position asked for
     * @param  integer $start       the number the scale begins at
     * @return array   id => priority
     */
    public static function reorder(array $orderedIds, $taskId, $rank, $start = 1)
    {
        $taskId = (int) $taskId;
        $start  = max(1, (int) $start);

        /* Pull it out wherever it was - including "nowhere", when the task
           had no priority and is joining the queue for the first time. */
        $queue = array();

        foreach ($orderedIds as $id) {
            if ((int) $id !== $taskId) {
                $queue[] = (int) $id;
            }
        }

        /* Rank 0 means "no priority": the task leaves the queue and the rest
           closes up behind it. */
        $leaving = (int) $rank <= 0;

        if (! $leaving) {
            $position = (int) $rank - $start;          // 0-based
            $position = max(0, min($position, count($queue)));
            array_splice($queue, $position, 0, array($taskId));
        }

        $assignments = array();

        foreach ($queue as $index => $id) {
            $assignments[$id] = $start + $index;
        }

        if ($leaving) {
            $assignments[$taskId] = 0;
        }

        return $assignments;
    }

    /**
     * Raise a project's priority_end so the scale covers the queue. Never
     * lowers it: somebody may have set a wider range on purpose.
     *
     * @param  integer $projectId
     * @param  integer $needed
     * @return void
     */
    protected function widenScale($projectId, $needed)
    {
        $project = $this->projectModel->getById($projectId);

        if (empty($project) || (int) $project['priority_end'] >= (int) $needed) {
            return;
        }

        $this->db
            ->table(\Kanboard\Model\ProjectModel::TABLE)
            ->eq('id', (int) $projectId)
            ->update(array('priority_end' => (int) $needed));
    }

    /**
     * How many positions the queue has - so a picker can offer 1..N, and one
     * more for a task joining it.
     *
     * @param  integer $projectId
     * @return integer
     */
    public function getQueueLength($projectId)
    {
        return count($this->getQueuedTasks($projectId));
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
