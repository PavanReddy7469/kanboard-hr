<?php

namespace Kanboard\Plugin\TaskManager\Action;

use Kanboard\Action\Base;
use Kanboard\Model\TaskModel;

/**
 * Nightly sweep: anything past its due date by more than N days gets a colour
 * and moves one step up the priority scale, so slipping work surfaces on the
 * board and in the tree without anyone chasing it.
 *
 * Runs on Kanboard's daily cronjob event.
 *
 * @package Kanboard\Plugin\TaskManager\Action
 */
class FlagOverdueTask extends Base
{
    public function getDescription()
    {
        return t('SUPERBEE: flag and escalate tasks overdue by more than N days');
    }

    public function getCompatibleEvents()
    {
        return array(TaskModel::EVENT_DAILY_CRONJOB);
    }

    public function getActionRequiredParameters()
    {
        return array(
            'duration' => t('Days overdue before flagging'),
            'color_id' => t('Colour to apply'),
        );
    }

    public function getEventRequiredParameters()
    {
        return array('tasks');
    }

    public function hasRequiredCondition(array $data)
    {
        return count($data['tasks']) > 0;
    }

    /**
     * @param  array $data
     * @return boolean
     */
    public function doAction(array $data)
    {
        $grace   = (int) $this->getParam('duration') * 86400;
        $colorId = $this->getParam('color_id');
        $floors  = array();
        $touched = false;

        foreach ($data['tasks'] as $task) {
            if (empty($task['date_due']) || $task['is_active'] != 1) {
                continue;
            }

            if (time() - $task['date_due'] <= $grace) {
                continue;
            }

            $projectId = $task['project_id'];

            if (! isset($floors[$projectId])) {
                $floors[$projectId] = $this->getPriorityFloor($projectId);
            }

            $values   = array('id' => $task['id']);
            $priority = (int) $task['priority'];

            if ($task['color_id'] !== $colorId) {
                $values['color_id'] = $colorId;
            }

            if ($priority > $floors[$projectId]) {
                $values['priority'] = $priority - 1;
            }

            if (count($values) > 1) {
                $touched = $this->taskModificationModel->update($values, false) || $touched;
            }
        }

        return $touched;
    }

    /**
     * A project's most urgent priority, so escalation stops at P1 rather than
     * walking off the bottom of the dropdown.
     *
     * @param  integer $projectId
     * @return integer
     */
    protected function getPriorityFloor($projectId)
    {
        $project = $this->projectModel->getById($projectId);

        return empty($project['priority_start']) ? 1 : (int) $project['priority_start'];
    }
}
