<?php

namespace Kanboard\Plugin\TaskManager\Model;

use Kanboard\Core\Base;
use Kanboard\Model\ProjectRoleModel;
use Kanboard\Model\ProjectRoleRestrictionModel;

/**
 * Seeds the three SUPERBEE project roles and their guardrails.
 *
 * These are Kanboard's own custom project roles, stored in project_has_roles
 * and project_role_has_restrictions, so they appear under Project settings >
 * Custom roles and are enforced by core's ProjectRoleHelper. Nothing about
 * permissions is hand-rolled any more.
 *
 * Kanboard's restrictions are NEGATIVE: a row means "this role may NOT do
 * this". So Manager and Team Lead get no rows at all, and Engineer gets the
 * rows that take away task creation, deletion and reassignment.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class RoleSeedModel extends Base
{
    const ROLE_MANAGER  = 'Manager';
    const ROLE_LEAD     = 'Team Lead';
    const ROLE_ENGINEER = 'Engineer';

    /**
     * role name => the rules that role is denied
     *
     * @return array
     */
    public static function getBlueprint()
    {
        return array(
            self::ROLE_MANAGER  => array(),
            self::ROLE_LEAD     => array(),
            self::ROLE_ENGINEER => array(
                ProjectRoleRestrictionModel::RULE_TASK_CREATION,
                ProjectRoleRestrictionModel::RULE_TASK_SUPPRESSION,
                ProjectRoleRestrictionModel::RULE_TASK_UPDATE_ASSIGNED,
            ),
        );
    }

    /**
     * Create any of the three roles a project does not already have.
     *
     * Safe to call repeatedly: an existing role of the same name is left
     * exactly as configured, restrictions included.
     *
     * @param  integer $projectId
     * @return void
     */
    public function seed($projectId)
    {
        $existing = array();

        foreach ($this->db->table(ProjectRoleModel::TABLE)->eq('project_id', $projectId)->findAll() as $row) {
            $existing[$row['role']] = true;
        }

        foreach (self::getBlueprint() as $role => $rules) {
            if (isset($existing[$role])) {
                continue;
            }

            $roleId = $this->db->table(ProjectRoleModel::TABLE)->persist(array(
                'project_id' => (int) $projectId,
                'role'       => $role,
            ));

            if (! $roleId) {
                continue;
            }

            foreach ($rules as $rule) {
                $this->db->table(ProjectRoleRestrictionModel::TABLE)->insert(array(
                    'project_id' => (int) $projectId,
                    'role_id'    => (int) $roleId,
                    'rule'       => $rule,
                ));
            }
        }
    }
}
