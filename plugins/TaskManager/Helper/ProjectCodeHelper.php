<?php

namespace Kanboard\Plugin\TaskManager\Helper;

use Kanboard\Core\Base;

/**
 * What the project creation form needs to know about codes.
 *
 * The form reads this rather than the controller, so core's
 * ProjectCreationController stays as close to stock as it can - the one thing
 * it had to learn is to pass project_type through.
 *
 * @package Kanboard\Plugin\TaskManager\Helper
 */
class ProjectCodeHelper extends Base
{
    /**
     * Prefix => label, for the dropdown.
     *
     * @return array
     */
    public function getTypes()
    {
        return $this->projectModel->getProjectTypes();
    }

    /**
     * The same list with a prompt in front, so the field starts unanswered
     * instead of silently defaulting to MultiCopter.
     *
     * @return array
     */
    public function getTypeOptions()
    {
        return array('' => t('Select aircraft type')) + $this->getTypes();
    }

    /**
     * Prefix => next free code, for the live preview in the form.
     *
     * This is a preview, not a reservation: two people creating a project at
     * the same moment both see MC03, and the second one is saved as MC04
     * because the number is settled again when the row is written.
     *
     * @return array
     */
    public function getNextCodes()
    {
        $codes = array();

        foreach (array_keys($this->getTypes()) as $prefix) {
            $codes[$prefix] = $this->projectModel->getNextIdentifier($prefix);
        }

        return $codes;
    }
}
