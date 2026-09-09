<?php

namespace Kanboard\Plugin\TaskManager\Model;

use Kanboard\Model\TaskModel;

/**
 * The completion gate.
 *
 * A task is only finished when someone has approved evidence that it is: the
 * assignee submits a link to the deliverable, an admin or project manager
 * approves it, and only then can the task be closed.
 *
 * This overrides core's taskStatusModel in the container rather than patching
 * app/, and it hooks close() specifically because every path that closes a
 * task funnels through it - the close button, the task view, closeMultipleTasks,
 * closeTasksBySwimlaneAndColumn, automatic actions and the JSON-RPC API all
 * call this one method. Gating anywhere higher up would leave a way round.
 *
 * Reopening is deliberately not gated: getting a task back out of "closed" must
 * never be blocked, or a wrong approval could strand it.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class TaskStatusModel extends \Kanboard\Model\TaskStatusModel
{
    /**
     * Why the last close() refused, for the caller to show the user.
     *
     * @var string
     */
    protected $lastRefusal = '';

    /**
     * @param  integer $task_id
     * @return boolean
     */
    public function close($task_id)
    {
        $this->lastRefusal = '';

        if (! $this->canClose($task_id)) {
            $this->lastRefusal = t('This task needs an approved deliverable before it can be closed.');
            return false;
        }

        $task = $this->taskFinderModel->getById($task_id);
        $closed = parent::close($task_id);

        if ($closed && ! empty($task)) {
            $this->promoteAfterClose($task);
        }

        return $closed;
    }

    /**
     * Close the ranking up behind a finished task.
     *
     * Priority here is a queue position, not a label: P1 is the work that
     * comes first. When P1 finishes, whatever was behind it is now first, so
     * every open task ranked below the finished one moves up a step. Gaps
     * survive - a project running P1, P2, P5 becomes P1, P4 - because a gap
     * someone left is usually deliberate.
     *
     * The finished task's own priority is cleared. It has no place in a queue
     * it has left, and leaving it at P1 would show two P1s side by side.
     *
     * Scope is the project. Ranks are only comparable within one.
     *
     * @param  array $task  the task as it was before closing
     */
    protected function promoteAfterClose(array $task)
    {
        $priority  = (int) $task['priority'];
        $projectId = (int) $task['project_id'];

        // A task that never carried a rank does not vacate a place in the queue.
        if ($priority <= 0 || $projectId <= 0) {
            return;
        }

        $this->db->startTransaction();

        try {
            $this->db->table(TaskModel::TABLE)
                ->eq('id', (int) $task['id'])
                ->update(array('priority' => 0));

            /* One statement rather than a row at a time: the set being moved
               is every open task ranked below the one that just finished, and
               they all move by the same step. */
            $this->db->getConnection()->exec(sprintf(
                'UPDATE %s SET priority = priority - 1 WHERE project_id = %d AND is_active = %d AND priority > %d',
                TaskModel::TABLE,
                $projectId,
                TaskModel::STATUS_OPEN,
                $priority
            ));

            $this->db->closeTransaction();
        } catch (\Exception $e) {
            $this->db->cancelTransaction();
        }
    }

    /**
     * Whether the gate is open for this task.
     *
     * Already-closed tasks pass: re-closing one is a no-op and must not start
     * failing because the rule arrived after it was finished.
     *
     * @param  integer $task_id
     * @return boolean
     */
    public function canClose($task_id)
    {
        if ($this->isClosed($task_id)) {
            return true;
        }

        return $this->deliverableModel->hasApproved($task_id);
    }

    /**
     * @return string
     */
    public function getLastRefusal()
    {
        return $this->lastRefusal;
    }
}
