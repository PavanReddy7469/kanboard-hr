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
                /* May not touch a task that is not theirs - which is what
                   makes a status somebody's own to report. */
                ProjectRoleRestrictionModel::RULE_TASK_UPDATE_ASSIGNED,
                /* ...nor hand work to somebody else, or take it off them.
                   Enforced again in AuthorityHelper, because this rule only
                   covers Kanboard's own screens and the pills are ours. */
                ProjectRoleRestrictionModel::RULE_TASK_CHANGE_ASSIGNEE,
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
        $this->backfillMissingRestrictions($projectId);

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

    /**
     * Add rules the blueprint has gained since a project's roles were made.
     *
     * seed() skips a role that already exists, which is right - somebody may
     * have adjusted it on purpose - but it means a rule added to the
     * blueprint later never reaches the projects that already had the role.
     * That is how every existing project ended up with an Engineer who could
     * still reassign work.
     *
     * Only ever adds. A rule removed from a project by hand stays removed.
     *
     * @param  integer $projectId
     * @return integer  how many rows were added
     */
    public function backfillMissingRestrictions($projectId)
    {
        $projectId = (int) $projectId;
        $blueprint = self::getBlueprint();
        $added     = 0;

        foreach ($this->db->table(ProjectRoleModel::TABLE)->eq('project_id', $projectId)->findAll() as $row) {
            if (! isset($blueprint[$row['role']]) || empty($blueprint[$row['role']])) {
                continue;
            }

            $have = array();

            foreach ($this->db->table(ProjectRoleRestrictionModel::TABLE)->eq('role_id', $row['role_id'])->findAll() as $restriction) {
                $have[$restriction['rule']] = true;
            }

            foreach ($blueprint[$row['role']] as $rule) {
                if (isset($have[$rule])) {
                    continue;
                }

                $this->db->table(ProjectRoleRestrictionModel::TABLE)->insert(array(
                    'project_id' => $projectId,
                    'role_id'    => (int) $row['role_id'],
                    'rule'       => $rule,
                ));

                $added++;
            }
        }

        return $added;
    }
}
