<?php

namespace Kanboard\Plugin\TaskManager\Subscriber;

use Kanboard\Event\TaskEvent;
use Kanboard\Model\TaskModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Closes gaps in the priority queue on every change, where the project asked
 * for it.
 *
 * Why a subscriber as well as the automatic action: Kanboard stores one row
 * per (event, action) pair, so an action alone covers exactly the one event
 * it was added for. "Renumber whenever anything changes" would mean adding
 * the same action five times in Project settings and still missing deletion,
 * which fires no event at all. Adding it once now switches on the whole
 * behaviour.
 *
 * OFF by default, and the switch is not hidden: it is the presence of the
 * PriorityEscalation automatic action on the project, which a Lead adds and
 * removes in Project settings. The objection to the subscriber this plugin
 * used to have was that it was invisible and always on; this one is neither.
 *
 * No re-entrancy guard, deliberately: the renumbering writes priorities
 * straight to the table rather than through taskModificationModel, so it
 * fires no events and cannot come back round to here.
 *
 * @package Kanboard\Plugin\TaskManager\Subscriber
 */
class PrioritySubscriber implements EventSubscriberInterface
{
    protected $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * Every event that can free a position, or follow one being freed.
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            TaskModel::EVENT_CLOSE        => 'onTaskChange',
            TaskModel::EVENT_OPEN         => 'onTaskChange',
            TaskModel::EVENT_UPDATE       => 'onTaskChange',
            TaskModel::EVENT_MOVE_COLUMN  => 'onTaskChange',
            TaskModel::EVENT_MOVE_PROJECT => 'onTaskChange',
        );
    }

    public function onTaskChange(TaskEvent $event)
    {
        $projectId = $this->getProjectId($event);

        if ($projectId === 0) {
            return;
        }

        $priorityModel = $this->container['priorityModel'];

        if ($priorityModel->isEnabledForProject($projectId)) {
            $priorityModel->closeGaps($projectId);
        }
    }

    /**
     * Event payloads are not uniform: some carry the whole task, some only
     * an id, and a move between projects carries the project directly.
     *
     * @param  TaskEvent $event
     * @return integer  0 when it cannot be determined
     */
    protected function getProjectId(TaskEvent $event)
    {
        if (! empty($event['task']['project_id'])) {
            return (int) $event['task']['project_id'];
        }

        if (! empty($event['project_id'])) {
            return (int) $event['project_id'];
        }

        $taskId = 0;

        if (! empty($event['task_id'])) {
            $taskId = (int) $event['task_id'];
        } elseif (! empty($event['id'])) {
            $taskId = (int) $event['id'];
        }

        if ($taskId === 0) {
            return 0;
        }

        $task = $this->container['taskFinderModel']->getById($taskId);

        return empty($task) ? 0 : (int) $task['project_id'];
    }
}
