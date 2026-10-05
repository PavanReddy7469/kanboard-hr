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

                /* The pills are not the security boundary - the Edit modal,
                   the bulk tools and the API all write these same columns.
                   Whatever the path, a field the person may not set is
                   dropped here rather than refused, so editing a title still
                   works on a task whose owner they cannot change. */
                $this->stripFieldsBeyondAuthority($values, $existing);
            }
        }

        // date_creation is history, not an editable field.
        unset($values['date_creation']);

        return parent::update($values, $fire_events);
    }

    /**
     * Remove the fields this person is not allowed to set.
     *
     * Only a value that would actually CHANGE is dropped: a form posts every
     * field it renders, so a task's own unchanged owner_id arrives on every
     * save and refusing that would block editing anything at all.
     *
     * Only while somebody is logged in, too. Cron and the CLI run with no
     * session - that is how the overdue-flag action sets a priority nightly -
     * and they are not the threat this guards against.
     *
     * @param  array $values
     * @param  array $existing
     * @return void
     */
    protected function stripFieldsBeyondAuthority(array &$values, array $existing)
    {
        if (! $this->userSession->isLogged()) {
            return;
        }

        $authority = $this->helper->authority;

        $guarded = array(
            'owner_id'  => $authority->canSetOwner($existing),
            'priority'  => $authority->canSetPriority($existing),
            'column_id' => $authority->canSetStatus($existing),
        );

        foreach ($guarded as $field => $allowed) {
            if ($allowed || ! array_key_exists($field, $values)) {
                continue;
            }

            if ((int) $values[$field] !== (int) $existing[$field]) {
                unset($values[$field]);
            }
        }
    }
}
