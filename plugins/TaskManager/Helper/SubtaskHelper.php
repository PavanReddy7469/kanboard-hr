<?php

namespace Kanboard\Plugin\TaskManager\Helper;

use Kanboard\Model\SubtaskModel;

/**
 * Enhanced SubtaskHelper for SUPERBEE:
 * Provides rich form fields for editing subtasks (Status, Due date, Estimate, Time spent).
 *
 * @package Kanboard\Plugin\TaskManager\Helper
 */
class SubtaskHelper extends \Kanboard\Helper\SubtaskHelper
{
    /**
     * Render status select dropdown for subtasks.
     *
     * @param  array $statusList
     * @param  array $values
     * @param  array $errors
     * @param  array $attributes
     * @return string
     */
    public function renderStatusField(array $statusList, array $values, array $errors = array(), array $attributes = array())
    {
        $attributes = array_merge(array('tabindex="2"'), $attributes);

        $html = $this->helper->form->label(t('Status'), 'status');
        $html .= $this->helper->form->select('status', $statusList, $values, $errors, $attributes);

        return $html;
    }

    /**
     * Render due date field for subtasks.
     *
     * @param  array $values
     * @param  array $errors
     * @param  array $attributes
     * @return string
     */
    public function renderDueDateField(array $values, array $errors = array(), array $attributes = array())
    {
        $attributes = array_merge(array('tabindex="4"'), $attributes);

        return $this->helper->form->date(t('Due date'), 'date_due', $values, $errors, $attributes);
    }

    /**
     * Render estimated hours field.
     *
     * @param  array $values
     * @param  array $errors
     * @param  array $attributes
     * @return string
     */
    public function renderTimeEstimatedField(array $values, array $errors = array(), array $attributes = array())
    {
        $attributes = array_merge(array('tabindex="5"'), $attributes);

        $html = $this->helper->form->label(t('Original estimate (hours)'), 'time_estimated');
        $html .= $this->helper->form->numeric('time_estimated', $values, $errors, $attributes);

        return $html;
    }

    /**
     * Render time spent hours field.
     *
     * @param  array $values
     * @param  array $errors
     * @param  array $attributes
     * @return string
     */
    public function renderTimeSpentField(array $values, array $errors = array(), array $attributes = array())
    {
        $attributes = array_merge(array('tabindex="6"'), $attributes);

        $html = $this->helper->form->label(t('Time spent (hours)'), 'time_spent');
        $html .= $this->helper->form->numeric('time_spent', $values, $errors, $attributes);

        return $html;
    }

    /**
     * Render checkbox-only status toggle (without duplicating the subtask title text).
     *
     * @param  array  $task
     * @param  array  $subtask
     * @param  string $fragment
     * @param  int    $userId
     * @return string
     */
    public function renderToggleStatusCheckbox(array $task, array $subtask, $fragment = '', $userId = 0)
    {
        $isDone = (int)$subtask['status'] === SubtaskModel::STATUS_DONE;
        $isInProgress = (int)$subtask['status'] === SubtaskModel::STATUS_INPROGRESS;

        if (! $this->helper->user->hasProjectAccess('SubtaskController', 'edit', $task['project_id'])) {
            $icon = $isDone ? 'fa-check-square-o' : ($isInProgress ? 'fa-gears' : 'fa-square-o');
            $color = $isDone ? '#10b981' : ($isInProgress ? '#f59e0b' : '#94a3b8');
            return '<i class="fa ' . $icon . '" style="color: ' . $color . '; font-size: 1.15rem;"></i>';
        }

        if ($isDone) {
            $icon = '<i class="fa fa-check-square-o" style="color: #10b981; font-size: 1.15rem;"></i>';
        } elseif ($isInProgress) {
            $icon = '<i class="fa fa-gears" style="color: #f59e0b; font-size: 1.15rem;"></i>';
        } else {
            $icon = '<i class="fa fa-square-o" style="color: #94a3b8; font-size: 1.15rem;"></i>';
        }

        $params = array(
            'project_id' => $task['project_id'],
            'task_id'    => $subtask['task_id'],
            'subtask_id' => $subtask['id'],
            'user_id'    => $userId,
            'fragment'   => $fragment,
            'csrf_token' => $this->token->getReusableCSRFToken(),
        );

        if ($subtask['status'] == 0 && $this->hasSubtaskInProgress()) {
            return $this->helper->url->link($icon, 'SubtaskRestrictionController', 'show', $params, false, 'js-modal-confirm', $this->getSubtaskTooltip($subtask));
        }

        return $this->helper->url->link($icon, 'SubtaskStatusController', 'change', $params, false, 'js-subtask-toggle-status', $this->getSubtaskTooltip($subtask));
    }
}
