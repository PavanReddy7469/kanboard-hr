<?php

namespace Kanboard\Plugin\TaskManager\Model;

use Kanboard\Core\Base;
use Kanboard\Model\TaskModel;

/**
 * Task lists — the grouping between a milestone and its tasks.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class TaskListModel extends Base
{
    const TABLE = 'taskmanager_task_lists';

    /**
     * @param  integer $projectId
     * @return array
     */
    public function getAll($projectId)
    {
        return $this->db
            ->table(self::TABLE)
            ->eq('project_id', $projectId)
            ->asc('position')
            ->asc('id')
            ->findAll();
    }

    /**
     * @param  integer $taskListId
     * @return array|null
     */
    public function getById($taskListId)
    {
        return $this->db->table(self::TABLE)->eq('id', $taskListId)->findOne();
    }

    /**
     * @param  integer $projectId
     * @param  boolean $prepend
     * @return array
     */
    public function getList($projectId, $prepend = true)
    {
        $result = array();

        if ($prepend) {
            $result[0] = t('No task list');
        }

        foreach ($this->getAll($projectId) as $row) {
            $result[$row['id']] = $row['title'];
        }

        return $result;
    }

    /**
     * Task lists grouped by the milestone they belong to.
     *
     * @param  integer $projectId
     * @return array  milestone_id => array of task lists
     */
    public function getGroupedByMilestone($projectId)
    {
        $grouped = array();

        foreach ($this->getAll($projectId) as $taskList) {
            $grouped[(int) $taskList['milestone_id']][] = $taskList;
        }

        return $grouped;
    }

    /**
     * @param  array $values
     * @return boolean|integer
     */
    public function create(array $values)
    {
        $values = $this->prepare($values);
        $values['position'] = (int) $this->db->table(self::TABLE)->eq('project_id', $values['project_id'])->count() + 1;

        return $this->db->table(self::TABLE)->persist($values);
    }

    /**
     * Moving a list to another milestone takes its tasks with it.
     *
     * The overview finds a task at [milestone_id][task_list_id]. Leave the
     * tasks behind on the old milestone and they are looked for under a list
     * that is no longer there - every task in the list drops off the tree at
     * once, with nothing on screen to say why. The two columns are one fact
     * stored twice, so they move together or not at all.
     *
     * @param  array $values
     * @return boolean
     */
    public function update(array $values)
    {
        $taskListId = (int) $values['id'];
        $existing   = $this->getById($taskListId);
        $values     = $this->prepare($values);
        unset($values['project_id']);

        $milestoneChanged = ! empty($existing)
            && array_key_exists('milestone_id', $values)
            && (int) $existing['milestone_id'] !== (int) $values['milestone_id'];

        if (! $milestoneChanged) {
            return $this->db->table(self::TABLE)->eq('id', $taskListId)->update($values);
        }

        $this->db->startTransaction();

        $result = $this->db->table(self::TABLE)->eq('id', $taskListId)->update($values);

        if ($result) {
            $this->db
                ->table(TaskModel::TABLE)
                ->eq('task_list_id', $taskListId)
                ->update(array('milestone_id' => (int) $values['milestone_id']));
        }

        $this->db->closeTransaction();

        return $result;
    }

    /**
     * Detaches tasks rather than deleting them.
     *
     * @param  integer $taskListId
     * @return boolean
     */
    public function remove($taskListId)
    {
        $this->db->startTransaction();

        $this->db->table(TaskModel::TABLE)->eq('task_list_id', $taskListId)->update(array('task_list_id' => 0));
        $result = $this->db->table(self::TABLE)->eq('id', $taskListId)->remove();

        $this->db->closeTransaction();

        return $result;
    }

    /**
     * @param  array $values
     * @return array
     */
    protected function prepare(array $values)
    {
        $allowed = array('project_id', 'milestone_id', 'title');
        $values = array_intersect_key($values, array_flip($allowed));
        $values['milestone_id'] = isset($values['milestone_id']) ? (int) $values['milestone_id'] : 0;

        return $values;
    }
}
