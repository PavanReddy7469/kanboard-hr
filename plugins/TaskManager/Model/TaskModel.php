<?php

namespace Kanboard\Plugin\TaskManager\Model;

/**
 * Deleting a task closes the hole it leaves in the priority queue.
 *
 * Every other way a position is freed - closing a task, moving it to a done
 * column, editing its priority - fires an event, so the automatic action
 * picks it up. Deletion fires nothing at all in Kanboard: TaskModel::remove()
 * deletes the row and returns. Without this, deleting the P3 of a project
 * would leave P1, P2, P4 behind, which is the one case the rest of the
 * feature would appear to have missed.
 *
 * The project's own switch is still what decides. This reads the same
 * automatic action a project has to enable, so a project that never asked
 * for renumbering does not get it through the back door.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class TaskModel extends \Kanboard\Model\TaskModel
{
    /**
     * @param  integer $task_id
     * @return boolean
     */
    public function remove($task_id)
    {
        /* Read the task first: after the delete there is nothing left to say
           which project it belonged to. */
        $task   = $this->taskFinderModel->getById($task_id);
        $result = parent::remove($task_id);

        if ($result && ! empty($task) && $this->priorityModel->isEnabledForProject($task['project_id'])) {
            $this->priorityModel->closeGaps($task['project_id']);
        }

        return $result;
    }
}
