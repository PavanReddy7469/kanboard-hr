<?php

namespace Kanboard\Plugin\TaskManager\Model;

use Kanboard\Core\Base;
use Kanboard\Model\TaskModel;

/**
 * Milestones — the top level of the SUPERBEE hierarchy.
 *
 * Project > Milestone > Task list > Task > Subtask
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class MilestoneModel extends Base
{
    use NoBackdatingTrait;

    const TABLE = 'taskmanager_milestones';

    /**
     * @param  integer $projectId
     * @return array
     */
    public function getAll($projectId)
    {
        return $this->db
            ->table(self::TABLE)
            ->eq('project_id', $projectId)
            ->eq('is_active', 1)
            ->asc('position')
            ->asc('id')
            ->findAll();
    }

    /**
     * @param  integer $milestoneId
     * @return array|null
     */
    public function getById($milestoneId)
    {
        return $this->db->table(self::TABLE)->eq('id', $milestoneId)->findOne();
    }

    /**
     * id => title, for select fields.
     *
     * @param  integer $projectId
     * @param  boolean $prepend
     * @return array
     */
    public function getList($projectId, $prepend = true)
    {
        // hashtable(), not table(): getAll($key, $value) is a HashTable method.
        $listing = $this->db
            ->hashtable(self::TABLE)
            ->eq('project_id', $projectId)
            ->eq('is_active', 1)
            ->asc('position')
            ->asc('id')
            ->getAll('id', 'title');

        return $prepend ? array(0 => t('No milestone')) + $listing : $listing;
    }

    /**
     * Milestones with task counts and a completion percentage.
     *
     * @param  integer $projectId
     * @return array
     */
    public function getAllWithProgress($projectId)
    {
        $milestones = $this->getAll($projectId);

        if (empty($milestones)) {
            return array();
        }

        $totals = $this->countTasksByMilestone($projectId, null);
        $closed = $this->countTasksByMilestone($projectId, 0);

        foreach ($milestones as &$milestone) {
            $id    = (int) $milestone['id'];
            $total = isset($totals[$id]) ? $totals[$id] : 0;
            $done  = isset($closed[$id]) ? $closed[$id] : 0;

            $milestone['nb_tasks']    = $total;
            $milestone['nb_closed']   = $done;
            $milestone['progress']    = $total > 0 ? (int) round($done / $total * 100) : 0;
            $milestone['is_reached']  = $milestone['date_reached'] > 0;
            $milestone['is_overdue']  = ! $milestone['is_reached']
                && $milestone['date_due'] > 0
                && $milestone['date_due'] < time();
        }

        return $milestones;
    }

    /**
     * @param  integer      $projectId
     * @param  integer|null $isActive  null for every task, 0 for closed only
     * @return array  milestone_id => count
     */
    protected function countTasksByMilestone($projectId, $isActive)
    {
        $query = $this->db
            ->table(TaskModel::TABLE)
            ->columns('milestone_id', 'COUNT(*) AS nb')
            ->eq('project_id', $projectId)
            ->groupBy('milestone_id');

        if ($isActive !== null) {
            $query->eq('is_active', $isActive);
        }

        $counts = array();

        foreach ($query->findAll() as $row) {
            $counts[(int) $row['milestone_id']] = (int) $row['nb'];
        }

        return $counts;
    }

    /**
     * @param  array $values
     * @return boolean|integer
     */
    public function create(array $values)
    {
        $this->backdatingRefusal = '';

        $refused = $this->findBackdatedFields($values, array(
            'date_start' => t('Start date'),
            'date_due'   => t('Due date'),
        ));

        if (! empty($refused)) {
            $this->refuseBackdating($refused);
            return false;
        }

        $values = $this->prepare($values);
        $values['position'] = $this->getLastPosition($values['project_id']);

        return $this->db->table(self::TABLE)->persist($values);
    }

    /**
     * @param  array $values
     * @return boolean
     */
    public function update(array $values)
    {
        $this->backdatingRefusal = '';
        $milestoneId = (int) $values['id'];

        // Compare against the stored milestone, so one that already runs from
        // an earlier date can still be renamed without tripping the rule.
        $existing = $this->db->table(self::TABLE)->eq('id', $milestoneId)->findOne();

        $refused = $this->findBackdatedFields($values, array(
            'date_start' => t('Start date'),
            'date_due'   => t('Due date'),
        ), is_array($existing) ? $existing : array());

        if (! empty($refused)) {
            return $this->refuseBackdating($refused);
        }

        $values = $this->prepare($values);
        unset($values['project_id']);

        return $this->db->table(self::TABLE)->eq('id', $milestoneId)->update($values);
    }

    /**
     * Removing a milestone detaches its task lists and tasks rather than
     * deleting them — losing work to a rename would be unforgivable.
     *
     * @param  integer $milestoneId
     * @return boolean
     */
    public function remove($milestoneId)
    {
        $this->db->startTransaction();

        $this->db->table(TaskModel::TABLE)->eq('milestone_id', $milestoneId)->update(array('milestone_id' => 0));
        $this->db->table(TaskListModel::TABLE)->eq('milestone_id', $milestoneId)->update(array('milestone_id' => 0));
        $result = $this->db->table(self::TABLE)->eq('id', $milestoneId)->remove();

        $this->db->closeTransaction();

        return $result;
    }

    /**
     * @param  integer $milestoneId
     * @return boolean
     */
    public function markAsReached($milestoneId)
    {
        return $this->db->table(self::TABLE)->eq('id', $milestoneId)->update(array('date_reached' => time()));
    }

    /**
     * @param  array $values
     * @return array
     */
    protected function prepare(array $values)
    {
        $allowed = array('project_id', 'title', 'description', 'date_start', 'date_due', 'owner_id', 'is_active');
        $values = array_intersect_key($values, array_flip($allowed));

        foreach (array('date_start', 'date_due') as $field) {
            $values[$field] = empty($values[$field]) ? 0 : $this->dateParser->getTimestamp($values[$field]);
        }

        $values['owner_id']  = isset($values['owner_id']) ? (int) $values['owner_id'] : 0;
        $values['is_active'] = isset($values['is_active']) ? (int) $values['is_active'] : 1;

        return $values;
    }

    /**
     * @param  integer $projectId
     * @return integer
     */
    protected function getLastPosition($projectId)
    {
        return (int) $this->db->table(self::TABLE)->eq('project_id', $projectId)->count() + 1;
    }
}
