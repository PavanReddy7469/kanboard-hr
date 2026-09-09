<?php

namespace Kanboard\Plugin\TaskManager\Helper;

/**
 * Enhanced TaskHelper for SUPERBEE:
 * 1. Labels the priority dropdown P1..P10 instead of 1..10.
 * 2. Suppresses unneeded time tracking fields (Original estimate, Time spent, Complexity).
 *
 * @package Kanboard\Plugin\TaskManager\Helper
 */
class TaskHelper extends \Kanboard\Helper\TaskHelper
{
    public function renderPriorityField(array $project, array $values)
    {
        /* A rank of nothing has to be selectable. Closing a task clears its
           priority to 0, and without this option the dropdown would fall back
           to showing P1 - so simply opening that task and saving would put it
           back in a queue it has already left. */
        $options = array(0 => '—');

        foreach (range($project['priority_start'], $project['priority_end']) as $priority) {
            $options[$priority] = 'P'.$priority;
        }

        $values += array('priority' => $project['priority_default']);

        $html = $this->helper->form->label(t('Priority'), 'priority');
        $html .= $this->helper->form->select('priority', $options, $values, array(), array('tabindex="9"'));

        return $html;
    }

    public function renderTimeEstimatedField(array $values, array $errors = array(), array $attributes = array())
    {
        return '';
    }

    public function renderTimeSpentField(array $values, array $errors = array(), array $attributes = array())
    {
        return '';
    }

    public function renderScoreField(array $values, array $errors = array(), array $attributes = array())
    {
        return '';
    }
}
