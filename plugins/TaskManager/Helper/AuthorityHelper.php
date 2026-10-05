<?php

namespace Kanboard\Plugin\TaskManager\Helper;

use Kanboard\Core\Base;
use Kanboard\Core\Security\Role;
use Kanboard\Plugin\TaskManager\Model\RoleSeedModel;

/**
 * Who may change what, in one place.
 *
 * Three separate places used to ask "is this person a manager" and each
 * answered differently - and all three asked only about Kanboard's built-in
 * project-manager role, which nobody here holds: this installation puts
 * people in the custom roles Manager, Team Lead and Engineer, and a custom
 * role is stored as its own name rather than as project-manager. A Manager
 * was therefore not a manager to any of that code.
 *
 * The rules this encodes:
 *
 *   owner     management only. An engineer cannot hand work to somebody
 *             else, or take it off them.
 *   priority  management only, including on their own tasks. Priority is a
 *             queue position across the whole project, so one person moving
 *             themselves up moves everyone else down.
 *   status    management, or the person the task is assigned to. Nobody
 *             reports progress on work that is not theirs.
 *
 * Every one of these is also enforced server-side at the point of writing,
 * because the methods here decide what a screen OFFERS, and a screen is not
 * a security boundary: the pills are GET endpoints, the Edit modal writes
 * the same columns, and so does the API.
 *
 * @package Kanboard\Plugin\TaskManager\Helper
 */
class AuthorityHelper extends Base
{
    /**
     * The project roles that count as management.
     *
     * @return array
     */
    public static function managementRoles()
    {
        return array(
            Role::PROJECT_MANAGER,
            RoleSeedModel::ROLE_MANAGER,
            RoleSeedModel::ROLE_LEAD,
        );
    }

    /**
     * @param  integer $projectId
     * @return boolean
     */
    public function isManager($projectId)
    {
        if ($this->userSession->isAdmin()) {
            return true;
        }

        return in_array($this->helper->projectRole->getProjectUserRole((int) $projectId), self::managementRoles(), true);
    }

    /**
     * Whether the task is assigned to the person asking.
     *
     * An unassigned task belongs to nobody, so this is false for everyone -
     * which is deliberate. Being able to set the status of work that has not
     * been given to anyone is how a queue stops meaning anything.
     *
     * @param  array $task
     * @return boolean
     */
    public function ownsTask(array $task)
    {
        $ownerId = isset($task['owner_id']) ? (int) $task['owner_id'] : 0;

        return $ownerId > 0 && $ownerId === (int) $this->userSession->getId();
    }

    /**
     * Reassigning work.
     *
     * @param  array $task
     * @return boolean
     */
    public function canSetOwner(array $task)
    {
        if (! $this->isManager($task['project_id'])) {
            return false;
        }

        /* Core's own restriction still applies on top: a project may take
           reassignment away from a role this calls management. */
        return $this->helper->projectRole->canChangeAssignee($task);
    }

    /**
     * Setting a priority, which is a queue position over the whole project.
     *
     * @param  array $task
     * @return boolean
     */
    public function canSetPriority(array $task)
    {
        return $this->isManager($task['project_id']);
    }

    /**
     * Reporting progress on a task.
     *
     * @param  array $task
     * @return boolean
     */
    public function canSetStatus(array $task)
    {
        if (! $this->isManager($task['project_id']) && ! $this->ownsTask($task)) {
            return false;
        }

        /* And core's restrictions on top - a role can be barred from moving
           tasks at all, or from the column being moved into, and that is
           checked where the columns are known. */
        return $this->helper->projectRole->canUpdateTask($task);
    }

    /**
     * Ticking a subtask off.
     *
     * A subtask carries its own assignee. The person it is assigned to may
     * set it; so may the owner of the parent task when the subtask is
     * assigned to nobody, because an unassigned subtask of your own task is
     * still your work.
     *
     * @param  array $task
     * @param  array $subtask
     * @return boolean
     */
    public function canSetSubtaskStatus(array $task, array $subtask)
    {
        if ($this->isManager($task['project_id'])) {
            return true;
        }

        /* A subtask's assignee is user_id on Kanboard's own records and
           owner_id on the rows the grid builds. Read whichever is present:
           guessing one and finding nothing would read as "unassigned" and
           quietly let the wrong person tick it off. */
        if (isset($subtask['user_id'])) {
            $subtaskOwner = (int) $subtask['user_id'];
        } elseif (isset($subtask['owner_id'])) {
            $subtaskOwner = (int) $subtask['owner_id'];
        } else {
            $subtaskOwner = 0;
        }

        $me           = (int) $this->userSession->getId();

        if ($subtaskOwner > 0) {
            return $subtaskOwner === $me;
        }

        return $this->ownsTask($task);
    }

    /**
     * Approving a completion report. The report is submitted "for
     * verification by the manager", so this is the same group.
     *
     * @param  integer $projectId
     * @return boolean
     */
    public function canApproveReport($projectId)
    {
        return $this->isManager($projectId);
    }
}
