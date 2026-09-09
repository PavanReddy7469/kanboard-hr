<?php

namespace Kanboard\Plugin\TaskManager\Model;

/**
 * Applies the SUPERBEE P1-P10 priority scale and seeds the Manager /
 * Team Lead / Engineer project roles onto new projects.
 *
 * Kanboard ships column defaults of priority_start=0, priority_end=3,
 * priority_default=0, which renders a P0-P3 dropdown and creates every task
 * at P0. Registered over the core projectModel by TaskManager\Plugin.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class ProjectModel extends \Kanboard\Model\ProjectModel
{
    use NoBackdatingTrait;

    const PRIORITY_START   = 1;
    const PRIORITY_END     = 10;
    const PRIORITY_DEFAULT = 5;

    public function create(array $values, $userId = 0, $addUser = false)
    {
        $this->backdatingRefusal = '';

        $refused = $this->findBackdatedFields($values, array(
            'start_date' => t('Start date'),
            'end_date'   => t('End date'),
        ));

        if (! empty($refused)) {
            $this->refuseBackdating($refused);
            return false;
        }

        $values = $this->applyPriorityScale($values);

        /* Core overwrites owner_id with whoever is creating the project, so a
           chosen owner has to be applied afterwards. The creator is still added
           to the project by parent::create(), so picking someone else as owner
           never locks the creator out of their own project. */
        $owner = isset($values['owner_id']) ? (int) $values['owner_id'] : 0;

        // parent::create() returns the new project id, or false.
        $projectId = parent::create($values, $userId, $addUser);

        if ($projectId) {
            $this->roleSeedModel->seed($projectId);

            if ($owner > 0 && $owner !== (int) $userId && $this->userModel->exists($owner)) {
                $this->db->table(self::TABLE)->eq('id', $projectId)->update(array('owner_id' => $owner));
                $this->projectUserRoleModel->addUser($projectId, $owner, \Kanboard\Core\Security\Role::PROJECT_MANAGER);
            }
        }

        return $projectId;
    }

    /**
     * Same rule when a project is edited. The existing row is passed in so an
     * old project keeps its original start date - only a date being moved
     * backwards is refused.
     *
     * @param  array $values
     * @return boolean
     */
    public function update(array $values)
    {
        $this->backdatingRefusal = '';

        if (! empty($values['id'])) {
            $existing = $this->getById($values['id']);

            if (! empty($existing)) {
                $refused = $this->findBackdatedFields($values, array(
                    'start_date' => t('Start date'),
                    'end_date'   => t('End date'),
                ), $existing);

                if (! empty($refused)) {
                    return $this->refuseBackdating($refused);
                }
            }
        }

        return parent::update($values);
    }

    /**
     * Fill in the SUPERBEE scale wherever the caller left the stock values.
     *
     * @param  array $values
     * @return array
     */
    protected function applyPriorityScale(array $values)
    {
        if (! isset($values['priority_start']) || (int) $values['priority_start'] < 1) {
            $values['priority_start'] = self::PRIORITY_START;
        }

        if (! isset($values['priority_end']) || (int) $values['priority_end'] < (int) $values['priority_start']) {
            $values['priority_end'] = self::PRIORITY_END;
        }

        $default = isset($values['priority_default']) ? (int) $values['priority_default'] : 0;

        if ($default < (int) $values['priority_start'] || $default > (int) $values['priority_end']) {
            $values['priority_default'] = self::PRIORITY_DEFAULT;
        }

        return $values;
    }
}
