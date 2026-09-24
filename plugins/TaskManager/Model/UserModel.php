<?php

namespace Kanboard\Plugin\TaskManager\Model;

/**
 * Carries the "must change password" flag.
 *
 * Accounts are onboarded with a shared default password, so the flag is set
 * whenever somebody is handed a password they did not choose:
 *
 *   - a new account, always;
 *   - an administrator resetting another person's password.
 *
 * It is cleared only when people change their own password, which is the one
 * case where the password is already a secret nobody else knows.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class UserModel extends \Kanboard\Model\UserModel
{
    public function create(array $values)
    {
        /* An LDAP account has no local password to change, so the gate would
           trap it on a page that cannot help. */
        $isLdap = ! empty($values['is_ldap_user']);

        if (! $isLdap && ! array_key_exists('must_change_password', $values)) {
            $values['must_change_password'] = 1;
        }

        return parent::create($values);
    }

    public function update(array $values)
    {
        if (! empty($values['password']) && ! array_key_exists('must_change_password', $values)) {
            $actor  = (int) $this->userSession->getId();
            $target = isset($values['id']) ? (int) $values['id'] : 0;

            /* Changing your own password clears the gate. Anyone else setting
               it means the new password has been seen by a second person, so
               the gate goes back up. */
            $values['must_change_password'] = ($actor > 0 && $actor === $target) ? 0 : 1;
        }

        return parent::update($values);
    }
}
