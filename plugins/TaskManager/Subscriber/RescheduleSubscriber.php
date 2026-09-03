<?php

namespace Kanboard\Plugin\TaskManager\Subscriber;

use Kanboard\Event\TaskEvent;
use Kanboard\Model\TaskModel;
use Kanboard\Plugin\TaskManager\Model\DependencyModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Shifts successor tasks when a predecessor's dates move.
 *
 * OFF by default, per project. Silently rewriting other people's due dates is
 * the fastest way to lose trust in a scheduler, so this only runs where a
 * Lead has deliberately switched it on.
 *
 * @package Kanboard\Plugin\TaskManager\Subscriber
 */
class RescheduleSubscriber implements EventSubscriberInterface
{
    const SETTING = 'taskmanager_auto_reschedule';
    const MAX_DEPTH = 25;

    /** Guards against a cascade re-entering through its own writes. */
    protected static $running = false;

    protected $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public static function getSubscribedEvents()
    {
        return array(
            TaskModel::EVENT_UPDATE => 'onTaskUpdate',
        );
    }

    public function onTaskUpdate(TaskEvent $event)
    {
        if (self::$running) {
            return;
        }

        $taskId = isset($event['task_id']) ? $event['task_id'] : (isset($event['id']) ? $event['id'] : 0);

        if (! $taskId) {
            return;
        }

        $task = $this->container['taskFinderModel']->getById($taskId);

        if (empty($task) || ! $this->isEnabled($task['project_id'])) {
            return;
        }

        self::$running = true;

        try {
            $this->cascade($task, 0);
        } finally {
            self::$running = false;
        }
    }

    /**
     * @param  integer $projectId
     * @return boolean
     */
    public function isEnabled($projectId)
    {
        return (int) $this->container['projectMetadataModel']->get($projectId, self::SETTING, 0) === 1;
    }

    /**
     * @param  array   $task
     * @param  integer $depth
     * @return void
     */
    protected function cascade(array $task, $depth)
    {
        if ($depth >= self::MAX_DEPTH) {
            return;
        }

        $successors = $this->container['dependencyModel']->getSuccessors($task['id']);

        foreach ($successors as $dependency) {
            $successor = $this->container['taskFinderModel']->getById($dependency['task_id']);

            if (empty($successor) || empty($successor['is_active'])) {
                continue;
            }

            $shifted = $this->applyConstraint($task, $successor, $dependency);

            if ($shifted !== null) {
                $this->container['db']->table(TaskModel::TABLE)
                    ->eq('id', $successor['id'])
                    ->update($shifted);

                $successor = array_merge($successor, $shifted);
                $this->cascade($successor, $depth + 1);
            }
        }
    }

    /**
     * Work out the successor's new dates, or null when it already satisfies
     * the constraint. Duration is preserved: a shift moves a bar, it does not
     * stretch it.
     *
     * @param  array $predecessor
     * @param  array $successor
     * @param  array $dependency
     * @return array|null
     */
    protected function applyConstraint(array $predecessor, array $successor, array $dependency)
    {
        $day  = 86400;
        $lag  = (int) $dependency['lag_days'] * $day;
        $type = $dependency['dependency_type'];

        $predStart = (int) $predecessor['date_started'];
        $predEnd   = (int) $predecessor['date_due'];
        $sucStart  = (int) $successor['date_started'];
        $sucEnd    = (int) $successor['date_due'];

        if ($sucStart <= 0 || $sucEnd <= 0) {
            return null;
        }

        $duration = max(0, $sucEnd - $sucStart);

        switch ($type) {
            case 'SS':
                if ($predStart <= 0) {
                    return null;
                }
                $earliestStart = $predStart + $lag;
                break;

            case 'FF':
                if ($predEnd <= 0) {
                    return null;
                }
                $earliestEnd = $predEnd + $lag;
                return $sucEnd >= $earliestEnd ? null : array(
                    'date_due'     => $earliestEnd,
                    'date_started' => $earliestEnd - $duration,
                );

            case 'SF':
                if ($predStart <= 0) {
                    return null;
                }
                $earliestEnd = $predStart + $lag;
                return $sucEnd >= $earliestEnd ? null : array(
                    'date_due'     => $earliestEnd,
                    'date_started' => $earliestEnd - $duration,
                );

            default: // FS
                if ($predEnd <= 0) {
                    return null;
                }
                $earliestStart = $predEnd + $lag;
                break;
        }

        if ($sucStart >= $earliestStart) {
            return null;
        }

        return array(
            'date_started' => $earliestStart,
            'date_due'     => $earliestStart + $duration,
        );
    }
}
