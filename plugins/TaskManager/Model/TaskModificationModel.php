<?php

namespace Kanboard\Plugin\TaskManager\Model;

use Kanboard\Model\TaskModel;

/**
 * Task modification, with backdating refused.
 *
 * Every path that changes a task's dates funnels through update(): the task
 * edit form, the inline panel, dragging a bar on the Gantt chart, the bulk
 * change tools, automatic actions and the API. The Gantt drag in particular
 * never touches the validator - it builds an array and calls this method - so
 * validator-level checks would leave it open.
 *
 * An unchanged date is never refused. Editing the title of a task that started
 * last month has to keep working; only a date being moved backwards is blocked.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class TaskModificationModel extends \Kanboard\Model\TaskModificationModel
{
    use NoBackdatingTrait;
    use TaskGroupingTrait;

    /**
     * @param  array   $values
     * @param  boolean $fire_events
     * @return boolean
     */
    public function update(array $values, $fire_events = true)
    {
        $this->backdatingRefusal = '';

        if (! empty($values['id'])) {
            $existing = $this->taskFinderModel->getById($values['id']);

            if (! empty($existing)) {
                $refused = $this->findBackdatedFields($values, array(
                    'date_started' => t('Start date'),
                    'date_due'     => t('Due date'),
                ), $existing);

                if (! empty($refused)) {
                    return $this->refuseBackdating($refused);
                }

                /* The project comes from the stored task, never from the
                   request: a task cannot be moved between projects by
                   editing it, so the posted ids are checked against the
                   project the task is actually in. $existing also supplies
                   whichever of the two ids this particular form left out. */
                $this->resolveGrouping($values, $existing['project_id'], $existing);
            }
        }

        // date_creation is history, not an editable field.
        unset($values['date_creation']);

        return parent::update($values, $fire_events);
    }
}
