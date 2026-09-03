<?php

namespace Kanboard\Plugin\TaskManager\Model;

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

        return parent::close($task_id);
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
