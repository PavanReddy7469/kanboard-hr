<?php

namespace Kanboard\Plugin\TaskManager\Model;

use Kanboard\Core\Base;
use Kanboard\Model\UserModel as CoreUserModel;
use Kanboard\Model\UserNotificationTypeModel;

/**
 * The outgoing mail this plugin sends on its own account.
 *
 * Kanboard's own notifications go through UserNotificationModel, which maps
 * an event name to a template by convention (dots become underscores, and it
 * looks only in app/Template/notification). A plugin cannot add to that map
 * without overriding core templates, so the reminders and the welcome mail
 * are sent from here instead, through the same emailClient underneath.
 *
 * Doing it here also fixes who receives a reminder. Core's overdue job mails
 * every notification-enabled member of the project, which for a twelve-person
 * project means eleven people are told about work that is not theirs. A
 * reminder goes to the person the task is assigned to, and to nobody else.
 *
 * Two things are checked before anything is sent, every time:
 *
 *   - the person has an email address at all;
 *   - the person has not switched notifications off in their profile.
 *
 * The second is the opt-out. New accounts are opted in by UserModel, but
 * "on by default" has to keep meaning "and they can turn it off", or it is
 * just spam with extra steps.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class MailerModel extends Base
{
    /**
     * The display name beside the From address.
     *
     * The address itself is never chosen here. Mail::sendEmail() always
     * calls setFrom(helper->mail->getMailSenderAddress(), $authorName), and
     * that helper reads the stored mail_sender_address setting first and
     * only falls back to the constant in config.php. So Settings > Email is
     * the single place the sender is decided, and this is just the name
     * people see beside it.
     */
    const AUTHOR_NAME = 'Superbee PMO';

    /**
     * No Reply-To header.
     *
     * The sixth argument to emailClient->send() is not the sender - it
     * becomes Reply-To. This used to pass the config.php sender constant
     * there, which put a dead address on every message as soon as the real
     * sender was set in the UI: anyone hitting Reply got a bounce. Nothing
     * is the right answer. These come from a no-reply address and there is
     * nobody at the other end.
     */
    const NO_REPLY_TO = '';

    /**
     * The subject line for a task reminder.
     *
     * Pure, and deliberately so: the subject is the entire message for
     * anyone reading on a phone lock screen, and it is the part that would
     * otherwise never be looked at again after the day it was written.
     *
     * @param  string  $kind
     * @param  array   $task
     * @param  integer $days  whole days until the due date
     * @return string
     */
    public static function reminderSubject($kind, array $task, $days)
    {
        $project = isset($task['project_name']) ? $task['project_name'] : '';
        $title   = isset($task['title']) ? $task['title'] : '';
        $id      = isset($task['id']) ? (int) $task['id'] : 0;

        switch ($kind) {
            case ReminderModel::KIND_DUE_TODAY:
                $lead = t('Due today');
                break;
            case ReminderModel::KIND_DUE_SOON:
                /* "in 3 days" rather than the date itself: the reader should
                   not have to work out what day it is to feel the urgency. */
                $lead = $days === 1 ? t('Due tomorrow') : t('Due in %d days', $days);
                break;
            case ReminderModel::KIND_START:
                $lead = t('Starts today');
                break;
            default:
                return '';
        }

        return sprintf('[%s] %s: #%d %s', $project, $lead, $id, $title);
    }

    /**
     * Whether this person can and wants to be written to.
     *
     * @param  array $user
     * @return boolean
     */
    public static function isReachable(array $user)
    {
        $email = isset($user['email']) ? trim((string) $user['email']) : '';

        if ($email === '') {
            return false;
        }

        /* Absent means absent, not enabled. A row fetched without the column
           must never be read as consent. */
        if (! array_key_exists('notifications_enabled', $user)) {
            return false;
        }

        /* Checked only when the caller supplied it. The task-reminder query
           filters disabled accounts out in SQL, but sendProjectEnding() is
           handed whole user rows read by id, and a disabled manager should
           not be told a project is ending either. Absence is not treated as
           inactive here, because the rows that lack the column have already
           been filtered. */
        if (array_key_exists('is_active', $user) && (int) $user['is_active'] !== 1) {
            return false;
        }

        return (int) $user['notifications_enabled'] === 1;
    }

    /**
     * Send one task reminder to the person it is assigned to.
     *
     * @param  array   $task  a row from ReminderModel::getCandidateTasks()
     * @param  string  $kind
     * @param  integer $now
     * @return boolean  whether it was actually sent
     */
    public function sendTaskReminder(array $task, $kind, $now)
    {
        $user = array(
            'email'                 => isset($task['assignee_email']) ? $task['assignee_email'] : '',
            'name'                  => isset($task['assignee_name']) ? $task['assignee_name'] : '',
            'username'              => isset($task['assignee_username']) ? $task['assignee_username'] : '',
            'notifications_enabled' => isset($task['assignee_notifications']) ? $task['assignee_notifications'] : 0,
        );

        if (! self::isReachable($user)) {
            return false;
        }

        $days = ReminderModel::daysUntil(isset($task['date_due']) ? (int) $task['date_due'] : 0, $now);

        $this->emailClient->send(
            $user['email'],
            $user['name'] ?: $user['username'],
            self::reminderSubject($kind, $task, $days),
            $this->template->render('TaskManager:notification/task_reminder', array(
                'task' => $task,
                'kind' => $kind,
                'days' => $days,
                'user' => $user,
            )),
            self::AUTHOR_NAME,
            self::NO_REPLY_TO
        );

        return true;
    }

    /**
     * Tell a project's managers that its end date is in sight.
     *
     * @param  array   $project
     * @param  array   $user
     * @param  integer $days
     * @return boolean
     */
    public function sendProjectEnding(array $project, array $user, $days)
    {
        if (! self::isReachable($user)) {
            return false;
        }

        $lead = $days <= 0 ? t('ends today') : ($days === 1 ? t('ends tomorrow') : t('ends in %d days', $days));

        $this->emailClient->send(
            $user['email'],
            $user['name'] ?: $user['username'],
            sprintf('[%s] %s', $project['name'], t('Project %s', $lead)),
            $this->template->render('TaskManager:notification/project_ending', array(
                'project' => $project,
                'days'    => $days,
                'user'    => $user,
            )),
            self::AUTHOR_NAME,
            self::NO_REPLY_TO
        );

        return true;
    }

    /**
     * The mail a new account gets.
     *
     * It carries no password. Accounts here are created with a shared
     * starting password and UserModel sets must_change_password, so the
     * person is forced to choose their own on first sign-in - putting the
     * shared one in an email would hand it to anyone who later reads that
     * mailbox, and it is the same string for everybody.
     *
     * @param  array $user  as stored, including id, username, email, name
     * @return boolean
     */
    public function sendWelcome(array $user)
    {
        $email = isset($user['email']) ? trim((string) $user['email']) : '';

        if ($email === '') {
            return false;
        }

        /* Not gated on notifications_enabled: this one is transactional.
           Somebody who has not signed in yet has had no chance to express a
           preference, and without it they do not know the account exists. */
        $this->emailClient->send(
            $email,
            isset($user['name']) && $user['name'] !== '' ? $user['name'] : $user['username'],
            t('Your Superbee PMO account is ready'),
            $this->template->render('TaskManager:notification/welcome', array(
                'user' => $user,
            )),
            self::AUTHOR_NAME,
            self::NO_REPLY_TO
        );

        return true;
    }

    /**
     * Switch a person's notifications on.
     *
     * Both halves are required and the second is the one that is easy to
     * miss: users.notifications_enabled is the master switch, but
     * UserNotificationModel then asks getSelectedTypes(), which reads
     * user_has_notification_types and filters the result against the
     * registered classes. A user with no row there has no selected type, so
     * the loop that sends the mail runs zero times - the switch is on and
     * nothing is delivered, with no error anywhere to explain it.
     *
     * @param  integer $userId
     * @return boolean
     */
    public function enableNotifications($userId)
    {
        $userId = (int) $userId;

        if ($userId <= 0) {
            return false;
        }

        $this->db->table(CoreUserModel::TABLE)
            ->eq('id', $userId)
            ->update(array('notifications_enabled' => 1));

        $hasType = $this->db->table(UserNotificationTypeModel::TABLE)
            ->eq('user_id', $userId)
            ->eq('notification_type', 'email')
            ->exists();

        if (! $hasType) {
            $this->db->table(UserNotificationTypeModel::TABLE)->insert(array(
                'user_id'           => $userId,
                'notification_type' => 'email',
            ));
        }

        return true;
    }
}
