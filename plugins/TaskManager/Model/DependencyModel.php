<?php

namespace Kanboard\Plugin\TaskManager\Model;

use Kanboard\Core\Base;
use Kanboard\Model\TaskModel;

/**
 * Typed scheduling dependencies between tasks.
 *
 *   FS  finish -> start   successor starts after predecessor finishes
 *   SS  start  -> start   both start together
 *   FF  finish -> finish  both finish together
 *   SF  start  -> finish  successor finishes after predecessor starts
 *
 * A lag in days shifts the constraint; it may be negative (lead).
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class DependencyModel extends Base
{
    const TABLE = 'taskmanager_dependencies';

    /**
     * @return array
     */
    public function getTypes()
    {
        return array(
            'FS' => t('Finish to Start'),
            'SS' => t('Start to Start'),
            'FF' => t('Finish to Finish'),
            'SF' => t('Start to Finish'),
        );
    }

    /**
     * Dependencies of one task, with the predecessor's title.
     *
     * @param  integer $taskId
     * @return array
     */
    public function getAllByTask($taskId)
    {
        return $this->db
            ->table(self::TABLE)
            ->columns(
                self::TABLE.'.id',
                self::TABLE.'.task_id',
                self::TABLE.'.depends_on_id',
                self::TABLE.'.dependency_type',
                self::TABLE.'.lag_days',
                TaskModel::TABLE.'.title AS depends_on_title',
                TaskModel::TABLE.'.is_active AS depends_on_is_active'
            )
            ->join(TaskModel::TABLE, 'id', 'depends_on_id')
            ->eq(self::TABLE.'.task_id', $taskId)
            ->findAll();
    }

    /**
     * Every dependency in a project, keyed by the dependent task id.
     *
     * @param  integer $projectId
     * @return array
     */
    public function getAllByProjectGroupedByTask($projectId)
    {
        $grouped = array();

        foreach ($this->getAllByProject($projectId) as $row) {
            $grouped[(int) $row['task_id']][] = $row;
        }

        return $grouped;
    }

    /**
     * @param  integer $projectId
     * @return array
     */
    public function getAllByProject($projectId)
    {
        return $this->db
            ->table(self::TABLE)
            ->eq('project_id', $projectId)
            ->findAll();
    }

    /**
     * Tasks that depend on the given task.
     *
     * @param  integer $taskId
     * @return array
     */
    public function getSuccessors($taskId)
    {
        return $this->db->table(self::TABLE)->eq('depends_on_id', $taskId)->findAll();
    }

    /**
     * The tasks waiting on this one, with their titles.
     *
     * getSuccessors() returns the raw edges for the rescheduler; this is the
     * same relationship read the other way round for display, so a person
     * looking at a task can see what is blocked behind it and not only what it
     * is blocked by.
     *
     * @param  integer $taskId
     * @return array
     */
    public function getSuccessorDetails($taskId)
    {
        return $this->db
            ->table(self::TABLE)
            ->columns(
                self::TABLE.'.id',
                self::TABLE.'.task_id',
                self::TABLE.'.depends_on_id',
                self::TABLE.'.dependency_type',
                self::TABLE.'.lag_days',
                TaskModel::TABLE.'.title AS task_title',
                TaskModel::TABLE.'.is_active AS task_is_active'
            )
            ->join(TaskModel::TABLE, 'id', 'task_id')
            ->eq(self::TABLE.'.depends_on_id', $taskId)
            ->findAll();
    }

    /**
     * @param  integer $dependencyId
     * @return array|null
     */
    public function getById($dependencyId)
    {
        return $this->db->table(self::TABLE)->eq('id', $dependencyId)->findOne();
    }

    /**
     * @param  array $values
     * @return boolean|integer
     */
    public function create(array $values)
    {
        $types = $this->getTypes();
        $type  = isset($values['dependency_type']) ? strtoupper($values['dependency_type']) : 'FS';

        return $this->db->table(self::TABLE)->persist(array(
            'project_id'      => (int) $values['project_id'],
            'task_id'         => (int) $values['task_id'],
            'depends_on_id'   => (int) $values['depends_on_id'],
            'dependency_type' => isset($types[$type]) ? $type : 'FS',
            'lag_days'        => isset($values['lag_days']) ? (int) $values['lag_days'] : 0,
            'date_creation'   => time(),
        ));
    }

    /**
     * @param  integer $dependencyId
     * @return boolean
     */
    public function remove($dependencyId)
    {
        return $this->db->table(self::TABLE)->eq('id', $dependencyId)->remove();
    }

    /**
     * Validate a proposed dependency.
     *
     * Rejects self-reference, duplicates, cross-project links and anything
     * that would close a cycle. A warning would be useless here: a cycle
     * makes the schedule unsolvable, so it must not be storable.
     *
     * @param  integer $taskId
     * @param  integer $dependsOnId
     * @return array  [bool $valid, string $error]
     */
    public function validate($taskId, $dependsOnId)
    {
        $taskId      = (int) $taskId;
        $dependsOnId = (int) $dependsOnId;

        if ($dependsOnId <= 0) {
            return array(false, t('Choose the task this one depends on.'));
        }

        if ($taskId === $dependsOnId) {
            return array(false, t('A task cannot depend on itself.'));
        }

        $task      = $this->taskFinderModel->getById($taskId);
        $dependsOn = $this->taskFinderModel->getById($dependsOnId);

        if (empty($task) || empty($dependsOn)) {
            return array(false, t('Task not found.'));
        }

        if ($task['project_id'] != $dependsOn['project_id']) {
            return array(false, t('Both tasks must belong to the same project.'));
        }

        $exists = $this->db->table(self::TABLE)
            ->eq('task_id', $taskId)
            ->eq('depends_on_id', $dependsOnId)
            ->exists();

        if ($exists) {
            return array(false, t('That dependency already exists.'));
        }

        if ($this->wouldCreateCycle($taskId, $dependsOnId)) {
            return array(false, t('That would create a circular dependency.'));
        }

        return array(true, '');
    }

    /**
     * Would adding "task depends on dependsOn" close a loop?
     *
     * True when task is already reachable from dependsOn by following
     * predecessor edges.
     *
     * @param  integer $taskId
     * @param  integer $dependsOnId
     * @return boolean
     */
    public function wouldCreateCycle($taskId, $dependsOnId)
    {
        $edges   = array();
        $task    = $this->taskFinderModel->getById($taskId);
        $project = empty($task) ? 0 : $task['project_id'];

        foreach ($this->getAllByProject($project) as $row) {
            $edges[(int) $row['task_id']][] = (int) $row['depends_on_id'];
        }

        $stack   = array((int) $dependsOnId);
        $visited = array();

        while (! empty($stack)) {
            $current = array_pop($stack);

            if ($current === (int) $taskId) {
                return true;
            }

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            if (isset($edges[$current])) {
                foreach ($edges[$current] as $next) {
                    $stack[] = $next;
                }
            }
        }

        return false;
    }

    /**
     * Short label for the overview table, e.g. "#118 FS +2d".
     *
     * @param  array $dependency
     * @return string
     */
    public function getShortLabel(array $dependency)
    {
        $label = '#'.$dependency['depends_on_id'].' '.$dependency['dependency_type'];

        if (! empty($dependency['lag_days'])) {
            $label .= sprintf(' %+dd', (int) $dependency['lag_days']);
        }

        return $label;
    }
}
