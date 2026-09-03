<?php

namespace Kanboard\Plugin\TaskManager\Action;

use Kanboard\Action\Base;
use Kanboard\Model\TaskModel;

/**
 * When the last active P1 in a project closes, promote the tasks queued
 * behind it: P2 -> P1, P3 -> P2, and so on down to the configured floor.
 *
 * Phase 5 turns what used to be an always-on, invisible event subscriber into
 * a proper automatic action, so it appears under Project settings > Automatic
 * actions with a per-project on/off switch and a configurable depth.
 *
 * @package Kanboard\Plugin\TaskManager\Action
 */
class PriorityEscalation extends Base
{
    public function getDescription()
    {
        return t('SUPERBEE: escalate queued priorities when the last P1 closes');
    }

    public function getCompatibleEvents()
    {
        return array(
            TaskModel::EVENT_CLOSE,
            TaskModel::EVENT_MOVE_COLUMN,
        );
    }

    /**
     * Kanboard renders a parameter called "priority" as the project's own
     * priority dropdown, so the person picks the deepest priority the cascade
     * should reach.
     *
     * @return array
     */
    public function getActionRequiredParameters()
    {
        return array(
            'priority' => t('Escalate tasks down to this priority'),
        );
    }

    public function getEventRequiredParameters()
    {
        return array(
            'task_id',
            'task' => array(
                'project_id',
                'column_id',
                'is_active',
            ),
        );
    }

    /**
     * Only act when the task that just changed actually left the board.
     *
     * @param  array $data
     * @return boolean
     */
    public function hasRequiredCondition(array $data)
    {
        if (empty($data['task']['is_active'])) {
            return true;
        }

        return (int) $data['task']['column_id'] === $this->getClosedColumnId($data['task']['project_id']);
    }

    /**
     * @param  array $data
     * @return boolean
     */
    public function doAction(array $data)
    {
        $projectId   = (int) $data['task']['project_id'];
        $floor       = max(2, (int) $this->getParam('priority'));
        $closedColId = $this->getClosedColumnId($projectId);

        $activeP1Count = $this->db->table(TaskModel::TABLE)
            ->eq('project_id', $projectId)
            ->eq('is_active', 1)
            ->eq('priority', 1)
            ->neq('column_id', $closedColId)
            ->count();

        if ($activeP1Count > 0) {
            return false;
        }

        $tasks = $this->db->table(TaskModel::TABLE)
            ->eq('project_id', $projectId)
            ->eq('is_active', 1)
            ->gt('priority', 1)
            ->lte('priority', $floor)
            ->neq('column_id', $closedColId)
            ->findAll();

        foreach ($tasks as $task) {
            $this->db->table(TaskModel::TABLE)
                ->eq('id', $task['id'])
                ->update(array('priority' => (int) $task['priority'] - 1));
        }

        return count($tasks) > 0;
    }

    /**
     * The column a project uses for finished work, matched by name.
     *
     * @param  integer $projectId
     * @return integer
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
