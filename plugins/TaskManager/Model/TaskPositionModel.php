<?php

namespace Kanboard\Plugin\TaskManager\Model;

/**
 * Task positioning, with the status rule applied at the point of writing.
 *
 * A task's status IS its column, and this is the one method every path goes
 * through to change it: the board's drag-and-drop, the grid's status pill,
 * the API, the automatic actions, and the move a completion report performs
 * when it sends a task to review.
 *
 * It needs to be here because the board does not check. BoardAjaxController
 * asks canMoveTask($project, $src, $dst) - a question about columns, which
 * never loads the task and so cannot ask whose it is. Dragging a card is
 * therefore a way to set the status of work that is not yours, no matter
 * what the grid's pills allow, and the pills are the part people were
 * looking at.
 *
 * Only while somebody is logged in. Cron, the CLI and the installer run with
 * no session and are not the threat this guards against; refusing there
 * would break the scheduled actions instead.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class TaskPositionModel extends \Kanboard\Model\TaskPositionModel
{
    /**
     * @param  integer $project_id
     * @param  integer $task_id
     * @param  integer $column_id
     * @param  integer $position
     * @param  integer $swimlane_id
     * @param  boolean $fire_events
     * @param  boolean $onlyOpen
     * @return boolean
     */
    public function movePosition($project_id, $task_id, $column_id, $position, $swimlane_id = 0, $fire_events = true, $onlyOpen = true)
    {
        if ($this->userSession->isLogged()) {
            $task = $this->taskFinderModel->getById($task_id);

            /* An unknown task is refused rather than passed through: the
               parent would fail on it anyway, and deciding permission from
               an empty array would mean deciding it from nothing. */
            if (empty($task) || ! $this->helper->authority->canSetStatus($task)) {
                return false;
            }
        }

        return parent::movePosition($project_id, $task_id, $column_id, $position, $swimlane_id, $fire_events, $onlyOpen);
    }
}
