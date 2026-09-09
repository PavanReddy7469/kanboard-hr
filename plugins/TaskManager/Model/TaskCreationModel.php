<?php

namespace Kanboard\Plugin\TaskManager\Model;

/**
 * Task creation, with backdating refused.
 *
 * Overriding the model rather than the validator is deliberate. The validator
 * only covers the web forms; task creation also happens through the API, the
 * automatic actions, recurrence and the bulk tools, and each of those calls
 * this method directly. Gating here is the only place that catches all of them.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class TaskCreationModel extends \Kanboard\Model\TaskCreationModel
{
    use NoBackdatingTrait;

    /**
     * @param  array $values
     * @return integer  0 when refused, as core returns 0 on failure
     */
    public function create(array $values)
    {
        $this->backdatingRefusal = '';

        $refused = $this->findBackdatedFields($values, array(
            'date_started' => t('Start date'),
            'date_due'     => t('Due date'),
        ));

        if (! empty($refused)) {
            $this->refuseBackdating($refused);
            return 0;
        }

        // The creation timestamp is a record of when this happened, not a
        // field anyone gets to choose. Core stamps it in prepare(); drop any
        // value supplied by a caller so the API cannot pre-date a task.
        unset($values['date_creation'], $values['date_modification']);

        return parent::create($values);
    }
}
