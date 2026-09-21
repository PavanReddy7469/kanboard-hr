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

    /**
     * The type chosen on the form, remembered between applyProjectType() and
     * generateUniqueIdentifier() - core calls the generator itself, from
     * inside its own transaction, and passes it no arguments.
     *
     * @var string
     */
    protected $pendingType = '';

    public function create(array $values, $userId = 0, $addUser = false)
    {
        $this->backdatingRefusal = '';
        $this->pendingType = '';

        $refused = $this->findBackdatedFields($values, array(
            'start_date' => t('Start date'),
            'end_date'   => t('End date'),
        ));

        if (! empty($refused)) {
            $this->refuseBackdating($refused);
            return false;
        }

        $values = $this->applyPriorityScale($values);
        $values = $this->applyProjectType($values);

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

    /**
     * The airframe types a project can be. The key is the code prefix, and it
     * is the only place the three are written down - the dropdown, the
     * validator and the code generator all read this list.
     *
     * @return array
     */
    public function getProjectTypes()
    {
        return array(
            'MC' => t('MultiCopter (MC)'),
            'FW' => t('FixedWing (FW)'),
            'HI' => t('HybridAircraft (HI)'),
        );
    }

    /**
     * The next free code for a type: MC01 on the first MultiCopter, MC02 on
     * the next, and so on.
     *
     * It counts from the highest number already issued rather than from how
     * many projects exist, so a code is never handed out twice - including
     * codes typed in by hand, and including the numbers of projects that have
     * since been deleted. A deleted MC02 leaves a gap on purpose: reissuing it
     * would point an old document or link at a different project.
     *
     * @param  string $type  MC, FW or HI
     * @return string        '' when the type is unknown or 99 is exhausted
     */
    public function getNextIdentifier($type)
    {
        $type = strtoupper(trim((string) $type));

        if (! array_key_exists($type, $this->getProjectTypes())) {
            return '';
        }

        $taken   = array();
        $highest = 0;

        foreach ($this->db->table(self::TABLE)->columns('identifier')->findAll() as $row) {
            $code = strtoupper((string) $row['identifier']);
            $taken[$code] = true;

            if (preg_match('/^'.$type.'([0-9]{2})$/', $code, $match)) {
                $highest = max($highest, (int) $match[1]);
            }
        }

        /* The code column is four characters wide, so a two-letter type leaves
           room for 01-99. Past that this returns nothing and core's random
           four-character generator takes over, which keeps the project
           creatable - a manager can then set a code by hand. */
        for ($number = $highest + 1; $number <= 99; $number++) {
            $code = $type.sprintf('%02d', $number);

            if (! isset($taken[$code])) {
                return $code;
            }
        }

        return '';
    }

    /**
     * Whether a code is the right shape for a type: MC01, FW07, HI12.
     *
     * @param  string $identifier
     * @param  string $type
     * @return boolean
     */
    public function isIdentifierForType($identifier, $type)
    {
        $type = strtoupper(trim((string) $type));

        if (! array_key_exists($type, $this->getProjectTypes())) {
            return false;
        }

        return (bool) preg_match('/^'.$type.'[0-9]{2}$/', strtoupper(trim((string) $identifier)));
    }

    /**
     * Settle the type and the code before the row is written.
     *
     * A code typed by hand is left alone - the validator has already checked
     * its shape and that nothing else holds it - so this only fills in the
     * blank case. An empty code and an empty type together mean an old form or
     * an API call, and core's random generator still covers that.
     *
     * @param  array $values
     * @return array
     */
    protected function applyProjectType(array $values)
    {
        $type = isset($values['project_type']) ? strtoupper(trim((string) $values['project_type'])) : '';

        if (! array_key_exists($type, $this->getProjectTypes())) {
            $type = '';
        }

        $values['project_type'] = $type;
        $this->pendingType = $type;

        $values['identifier'] = isset($values['identifier'])
            ? strtoupper(preg_replace('/\s+/', '', (string) $values['identifier']))
            : '';

        /* The code itself is left for generateUniqueIdentifier() below, which
           core calls from inside the transaction that writes the row. Picking
           the number here instead would leave a gap between choosing it and
           using it, and two people creating a MultiCopter at the same moment
           could both be handed MC03. */
        return $values;
    }

    /**
     * Core calls this from inside its own transaction whenever the code field
     * was left blank. With a type chosen it produces that type's next number;
     * without one - project duplication, the API, a form from before the types
     * existed - it falls through to core's random four-character code, so
     * nothing that used to work starts failing.
     *
     * @return string
     */
    public function generateUniqueIdentifier()
    {
        if ($this->pendingType !== '') {
            $code = $this->getNextIdentifier($this->pendingType);

            if ($code !== '') {
                return $code;
            }
        }

        return parent::generateUniqueIdentifier();
    }
}
