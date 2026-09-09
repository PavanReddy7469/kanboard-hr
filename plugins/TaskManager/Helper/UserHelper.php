<?php

namespace Kanboard\Plugin\TaskManager\Helper;

/**
 * Adds the employee ID to how a person is displayed: "NAME (EMP ID)".
 *
 * This one method is the single funnel worth changing. UserModel::prepareList()
 * builds every user dropdown in the application through it - task owner,
 * assignee, project owner, group members - and templates call it directly for
 * labels. Changing it here reaches all of them without touching a single query.
 *
 * The employee IDs are read once per request and kept, rather than joined into
 * each of those queries. The alternative was widening half a dozen SELECTs that
 * currently fetch only id, username and name; for a table of staff, one small
 * lookup is both cheaper and far less invasive.
 *
 * A user with no employee ID displays exactly as before.
 *
 * @package Kanboard\Plugin\TaskManager\Helper
 */
class UserHelper extends \Kanboard\Helper\UserHelper
{
    /**
     * user id => employee id, for everyone who has one. Null until loaded.
     *
     * @var array|null
     */
    protected $employeeIds = null;

    /**
     * @return array
     */
    protected function getEmployeeIds()
    {
        if ($this->employeeIds === null) {
            $this->employeeIds = array();

            try {
                $rows = $this->db
                    ->table(\Kanboard\Model\UserModel::TABLE)
                    ->neq('employee_id', '')
                    ->notNull('employee_id')
                    ->columns('id', 'employee_id')
                    ->findAll();

                foreach ($rows as $row) {
                    $this->employeeIds[(int) $row['id']] = $row['employee_id'];
                }
            } catch (\Exception $e) {
                /* The column arrives with plugin schema 7. If this runs against
                   a database that has not migrated yet, fall back to plain
                   names rather than taking every page down. */
                $this->employeeIds = array();
            }
        }

        return $this->employeeIds;
    }

    /**
     * @param  array $user
     * @return string
     */
    public function getFullname(array $user = array())
    {
        $name = parent::getFullname($user);

        // Some callers already carry the column; prefer it over a lookup.
        if (! empty($user['employee_id'])) {
            return $name.' ('.$user['employee_id'].')';
        }

        $id = isset($user['id']) ? (int) $user['id'] : 0;

        if ($id === 0 && empty($user)) {
            $id = (int) $this->userSession->getId();
        }

        if ($id > 0) {
            $map = $this->getEmployeeIds();

            if (! empty($map[$id])) {
                return $name.' ('.$map[$id].')';
            }
        }

        return $name;
    }

    /**
     * The name on its own, for the places where the employee ID would be
     * noise - the signed-in user's own label in the sidebar, for instance.
     *
     * @param  array $user
     * @return string
     */
    public function getPlainFullname(array $user = array())
    {
        return parent::getFullname($user);
    }

    /**
     * The employee ID for a given user id, or an empty string.
     *
     * @param  integer $userId
     * @return string
     */
    public function getEmployeeId($userId)
    {
        $map = $this->getEmployeeIds();

        return isset($map[(int) $userId]) ? $map[(int) $userId] : '';
    }

    /**
     * Format a name that came out of a query as a plain string - task
     * assignee_name and friends - where no user array is available.
     *
     * @param  integer $userId
     * @param  string  $name
     * @return string
     */
    public function formatName($userId, $name)
    {
        $employeeId = $this->getEmployeeId($userId);

        return $employeeId === '' ? $name : $name.' ('.$employeeId.')';
    }
}
