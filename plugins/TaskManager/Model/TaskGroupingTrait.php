<?php

namespace Kanboard\Plugin\TaskManager\Model;

/**
 * Decides which milestone and which task list a task belongs to.
 *
 * Both ids arrive from a POST body, or from the API, and both used to go
 * straight into the INSERT. Two things had to be settled here rather than in
 * a validator, because task creation and modification also happen through the
 * API, the automatic actions and the bulk tools, none of which run a
 * validator:
 *
 * 1. A task list belongs to exactly one milestone, and the tree looks a task
 *    up at [milestone_id][task_list_id]. A task filed under list 5 but
 *    milestone 0, when list 5 sits under milestone 2, is looked for in a
 *    place nothing renders - it vanishes from the overview without a trace.
 *    So the list wins: pick a list and you have picked its milestone.
 *
 * 2. Neither id is checked against the project. Without that, a crafted
 *    request can file a task under another project's milestone, and the task
 *    then shows up in a tree its author cannot see.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
trait TaskGroupingTrait
{
    /**
     * Normalise $values['milestone_id'] and $values['task_list_id'] in place.
     *
     * Does nothing at all when neither key is present: a Gantt drag or an
     * automatic action that only moves a date must not have its grouping
     * rewritten as a side effect.
     *
     * @param  array   $values     Modified in place.
     * @param  integer $projectId  The project the task lives in.
     * @param  array   $existing   The task as stored, for a partial update.
     */
    protected function resolveGrouping(array &$values, $projectId, array $existing = array())
    {
        $hasList      = array_key_exists('task_list_id', $values);
        $hasMilestone = array_key_exists('milestone_id', $values);

        if (! $hasList && ! $hasMilestone) {
            return;
        }

        $projectId = (int) $projectId;

        /* A form that offers only one of the two still has to end up
           consistent, so the missing half comes from the stored task. */
        $listId = $hasList
            ? (int) $values['task_list_id']
            : (isset($existing['task_list_id']) ? (int) $existing['task_list_id'] : 0);

        $milestoneId = $hasMilestone
            ? (int) $values['milestone_id']
            : (isset($existing['milestone_id']) ? (int) $existing['milestone_id'] : 0);

        if ($listId > 0) {
            $list = $this->taskListModel->getById($listId);

            if (empty($list) || (int) $list['project_id'] !== $projectId) {
                $listId = 0;
            } else {
                /* The list decides. Anything posted in milestone_id is
                   overruled, which is what keeps the invariant true no
                   matter what the caller sent. */
                $milestoneId = (int) $list['milestone_id'];
            }
        }

        if ($listId === 0 && $milestoneId > 0) {
            $milestone = $this->milestoneModel->getById($milestoneId);

            if (empty($milestone) || (int) $milestone['project_id'] !== $projectId) {
                $milestoneId = 0;
            }
        }

        $values['task_list_id'] = $listId;
        $values['milestone_id'] = $milestoneId;
    }
}
