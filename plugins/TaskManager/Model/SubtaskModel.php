<?php

namespace Kanboard\Plugin\TaskManager\Model;

/**
 * Subtasks with a start date, and the same refusal of past dates that tasks
 * and projects get.
 *
 * Core already stores date_due on a subtask and converts it in prepare();
 * date_started arrives with plugin schema 8 and needs the same treatment, so
 * the two behave identically rather than one silently failing to save.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class SubtaskModel extends \Kanboard\Model\SubtaskModel
{
    use NoBackdatingTrait;

    /**
     * The date fields a subtask carries, and how to label them if refused.
     *
     * @return array
     */
    protected function dateFields()
    {
        return array(
            'date_started' => t('Start date'),
            'date_due'     => t('Due date'),
        );
    }

    /**
     * @param  array $values
     * @return boolean|integer
     */
    public function create(array $values)
    {
        $this->backdatingRefusal = '';

        $refused = $this->findBackdatedFields($values, $this->dateFields());

        if (! empty($refused)) {
            $this->refuseBackdating($refused);
            return false;
        }

        return parent::create($values);
    }

    /**
     * @param  array   $values
     * @param  boolean $fire_events
     * @return boolean
     */
    public function update(array $values, $fire_events = true)
    {
        $this->backdatingRefusal = '';

        if (! empty($values['id'])) {
            $existing = $this->getById($values['id']);

            if (! empty($existing)) {
                $refused = $this->findBackdatedFields($values, $this->dateFields(), $existing);

                if (! empty($refused)) {
                    return $this->refuseBackdating($refused);
                }
            }
        }

        return parent::update($values, $fire_events);
    }

    /**
     * Core converts date_due here; date_started needs the same, or the form
     * would post a date string into an integer column and lose it.
     *
     * @param  array $values
     */
    protected function prepare(array &$values)
    {
        $this->normaliseStartDate($values);
        parent::prepare($values);
    }

    /**
     * @param  array $values
     */
    protected function prepareCreation(array &$values)
    {
        $this->normaliseStartDate($values);
        parent::prepareCreation($values);
    }

    /**
     * @param  array $values
     */
    protected function normaliseStartDate(array &$values)
    {
        if (! isset($values['date_started'])) {
            return;
        }

        if (! empty($values['date_started']) && ! is_numeric($values['date_started'])) {
            $values['date_started'] = $this->dateParser->getTimestamp($values['date_started']);
        } elseif (empty($values['date_started'])) {
            $values['date_started'] = 0;
        } else {
            $values['date_started'] = (int) $values['date_started'];
        }
    }
}
