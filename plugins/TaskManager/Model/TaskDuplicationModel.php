<?php

namespace Kanboard\Plugin\TaskManager\Model;

use Pimple\Container;

/**
 * A duplicated task gets its own code.
 *
 * Core copies `reference` along with the rest of the fields, which was
 * harmless while the code was a random string nobody read. Now that the code
 * identifies the task - MC01-003 - copying it would put two different tasks
 * under one identifier, and the copy would be indistinguishable from the
 * original in the grid, in search and in any document quoting it.
 *
 * Removing the field from the list rather than restating the list keeps this
 * in step with core: whatever else Kanboard decides to duplicate in a later
 * version still gets duplicated.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class TaskDuplicationModel extends \Kanboard\Model\TaskDuplicationModel
{
    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->fieldsToDuplicate = array_values(
            array_diff($this->fieldsToDuplicate, array('reference'))
        );

        /* A copy belongs beside the task it was copied from, so it starts in
           the same milestone and task list rather than falling into
           "Unassigned" for someone to re-file by hand.
           Duplicating into a different project is safe too: creation checks
           both ids against the destination project and clears them when they
           belong elsewhere. */
        $this->fieldsToDuplicate = array_values(array_unique(array_merge(
            $this->fieldsToDuplicate,
            array('milestone_id', 'task_list_id')
        )));
    }
}
