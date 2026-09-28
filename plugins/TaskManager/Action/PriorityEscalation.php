<?php

namespace Kanboard\Plugin\TaskManager\Action;

use Kanboard\Action\Base;
use Kanboard\Model\TaskModel;

/**
 * Keep a project's priorities contiguous: no gaps in the queue.
 *
 * It used to fire only when the last active P1 in a project closed, shifting
 * everything from P2 down to a configured floor up by one. That covered one
 * hole - the top one - and left every other hole in place. A project that had
 * closed its P3 and P5 read P1, P2, P4, P6 for good.
 *
 * Now any change that can free a position triggers a pass that renumbers the
 * priorities in use onto consecutive numbers. Ties survive: two tasks sharing
 * P2 share the new number too. The pass is idempotent, so firing on more
 * events costs nothing when there is nothing to close.
 *
 * Still an automatic action rather than an always-on subscriber, which was a
 * deliberate decision in Phase 5: it appears under Project settings >
 * Automatic actions and each project decides for itself.
 *
 * @package Kanboard\Plugin\TaskManager\Action
 */
class PriorityEscalation extends Base
{
    public function getDescription()
    {
        return t('SUPERBEE: close gaps in the priority queue after any change');
    }

    /**
     * Every event that can leave a hole.
     *
     * task.update covers a priority edited by hand. task.close and
     * task.move.column cover work leaving the board. task.open and
     * task.move.project cover a position arriving or leaving, which cannot
     * create a gap on its own but can follow one.
     *
     * Deletion is missing because Kanboard fires no event for it; that path
     * is handled in the task model, reading this action's own per-project
     * switch so nothing is renumbered without opting in.
     *
     * @return array
     */
    public function getCompatibleEvents()
    {
        return array(
            TaskModel::EVENT_CLOSE,
            TaskModel::EVENT_OPEN,
            TaskModel::EVENT_UPDATE,
            TaskModel::EVENT_MOVE_COLUMN,
            TaskModel::EVENT_MOVE_PROJECT,
        );
    }

    /**
     * Nothing to configure. The old floor parameter chose how deep the
     * one-step shift reached; a full renumber has no depth to choose.
     *
     * @return array
     */
    public function getActionRequiredParameters()
    {
        return array();
    }

    public function getEventRequiredParameters()
    {
        return array(
            'task_id',
            'task' => array(
                'project_id',
            ),
        );
    }

    /**
     * No condition. Working out in advance whether a hole exists would mean
     * doing the same query the pass does, and the pass already returns
     * without writing when there is nothing to close.
     *
     * @param  array $data
     * @return boolean
     */
    public function hasRequiredCondition(array $data)
    {
        return true;
    }

    /**
     * @param  array $data
     * @return boolean  whether anything was renumbered
     */
    public function doAction(array $data)
    {
        return $this->priorityModel->closeGaps($data['task']['project_id']) > 0;
    }
}
