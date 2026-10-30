<?php

namespace Kanboard\Plugin\TaskManager\Model;

/**
 * Carries the "must change password" flag, and welcomes a new account.
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

        $userId = parent::create($values);

        if ($userId) {
            $this->onCreated((int) $userId, $values);
        }

        return $userId;
    }

    /**
     * Everything that should happen once an account exists.
     *
     * Split out because it must never be able to stop the account being
     * created. A new user is made by an administrator who is watching the
     * screen; if the relay is down, or the address has a typo in it, or
     * Google rate-limits the moment somebody onboards fifteen people at
     * once, the right outcome is an account that exists and a line in the
     * log - not a 500 and an administrator who has no idea whether the user
     * was created and tries again.
     *
     * There is no event to subscribe to for this: Kanboard's UserModel
     * defines no EVENT_CREATE, which is why the hook lives in an override.
     *
     * @param  integer $userId
     * @param  array   $values
     */
    protected function onCreated($userId, array $values)
    {
        try {
            /* Opted in on creation, which is what makes "on by default"
               true for accounts made after the migration ran. The person
               can still turn it off in their own profile. */
            $this->mailerModel->enableNotifications($userId);

            if (! empty($values['email'])) {
                $this->mailerModel->sendWelcome(array(
                    'id'       => $userId,
                    'username' => isset($values['username']) ? $values['username'] : '',
                    'name'     => isset($values['name']) ? $values['name'] : '',
                    'email'    => $values['email'],
                ));
            }
        } catch (\Exception $e) {
            $this->logger->error('Welcome email failed for user '.$userId.': '.$e->getMessage());
        }
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
