<?php

namespace Kanboard\Plugin\TaskManager\Helper;

use Kanboard\Core\Base;

/**
 * Feeds the milestone and task-list selects on the task form.
 *
 * Templates have no way to reach a model, and the task form is core's - it
 * builds its own view variables and knows nothing about either of ours. A
 * helper is the one seam that reaches both.
 *
 * @package Kanboard\Plugin\TaskManager\Helper
 */
class TaskGroupingHelper extends Base
{
    /**
     * Which project a form is for.
     *
     * The creation form carries project_id in its values. The edit form does
     * too, until a validation error sends the posted values back through -
     * those hold only what the form submitted, and project_id is not one of
     * the fields. Falling back to the task itself keeps the selects on screen
     * through a failed save instead of quietly dropping them.
     *
     * @param  array $values
     * @return integer  0 when it cannot be determined.
     */
    public function getProjectId(array $values)
    {
        if (! empty($values['project_id'])) {
            return (int) $values['project_id'];
        }

        if (! empty($values['id'])) {
            $task = $this->taskFinderModel->getById($values['id']);

            if (! empty($task)) {
                return (int) $task['project_id'];
            }
        }

        return 0;
    }

    /**
     * @param  integer $projectId
     * @return array  id => title
     */
    public function getMilestoneOptions($projectId)
    {
        return $this->milestoneModel->getList($projectId);
    }

    /**
     * Task lists, each labelled with the milestone it sits under.
     *
     * The label is not decoration. Choosing a list also sets the task's
     * milestone, so the milestone has to be visible at the moment of the
     * choice rather than discovered afterwards on the overview.
     *
     * @param  integer $projectId
     * @return array  id => label
     */
    public function getTaskListOptions($projectId)
    {
        $milestones = $this->milestoneModel->getList($projectId, false);
        $options    = array(0 => t('No task list'));

        foreach ($this->taskListModel->getAll($projectId) as $list) {
            $milestoneId = (int) $list['milestone_id'];

            $options[$list['id']] = isset($milestones[$milestoneId])
                ? $list['title'].' — '.$milestones[$milestoneId]
                : $list['title'];
        }

        return $options;
    }

    /**
     * Whether this project has anything to group tasks into.
     *
     * A project with no milestones and no task lists gets no selects at all,
     * rather than two dropdowns whose only entry is "None".
     *
     * @param  integer $projectId
     * @return boolean
     */
    public function hasGroups($projectId)
    {
        if (count($this->milestoneModel->getList($projectId, false)) > 0) {
            return true;
        }

        return count($this->taskListModel->getAll($projectId)) > 0;
    }
}
