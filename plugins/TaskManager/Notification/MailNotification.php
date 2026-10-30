<?php

namespace Kanboard\Plugin\TaskManager\Notification;

/**
 * Every notification email, in the same frame.
 *
 * Kanboard has 23 notification templates and each is a standalone HTML
 * document written in about 2014: an <h2>, a bare <ul> of changes, an <hr>
 * and the word "Kanboard". They are the emails the team actually receives
 * day to day, and they looked nothing like the portal they come from.
 *
 * Rewriting 23 files would mean 23 files to re-merge on every Kanboard
 * upgrade, so this intercepts one method instead. Two paths:
 *
 *   a task event  - rendered entirely by this plugin, with the context the
 *                   core templates never had: what changed FROM and to,
 *                   where the task stands now, who did it, and the last
 *                   couple of comments.
 *
 *   anything else - core's own body, unwrapped from its <html> document and
 *                   dropped into the same frame. Not beautiful, but
 *                   consistent, and it cannot break when core adds an event.
 *
 * The subject line is left alone. getMailSubject() already reads
 * "[Project] Title", which is right, and threading in a mail client depends
 * on it staying stable.
 *
 * @package Kanboard\Plugin\TaskManager\Notification
 */
class MailNotification extends \Kanboard\Notification\MailNotification
{
    /**
     * How many of the most recent comments to include.
     *
     * Two. One is often "ok" and tells the reader nothing; the whole thread
     * turns a notification into a wall of text that gets skimmed and then
     * ignored. The link is there for the rest.
     */
    const RECENT_COMMENTS = 2;

    /**
     * @param  string $event_name
     * @param  array  $event_data
     * @return string
     */
    public function getMailContent($event_name, array $event_data)
    {
        if (empty($event_data['task'])) {
            /* Project-level or future events. Framed, not restyled: this
               must keep working for an event this plugin has never heard
               of, which is exactly what a core upgrade will add. */
            return $this->template->render('TaskManager:notification/_shell', array(
                'content' => self::unwrapDocument(parent::getMailContent($event_name, $event_data)),
                'reason'  => t('You are receiving this because you follow this project.'),
            ));
        }

        $task = $event_data['task'];

        return $this->template->render('TaskManager:notification/task_event', array(
            'event_name' => $event_name,
            /* Core's own localised per-event title, so a new event type
               gets a correct headline here without this class being told
               about it. */
            'headline'   => $this->notificationModel->getTitleWithoutAuthor($event_name, $event_data),
            'task'       => $task,
            'changes'    => isset($event_data['changes']) ? $event_data['changes'] : array(),
            'previous'   => isset($event_data['previous']) ? $event_data['previous'] : array(),
            'comment'    => isset($event_data['comment']) ? $event_data['comment'] : array(),
            'subtask'    => isset($event_data['subtask']) ? $event_data['subtask'] : array(),
            'file'       => isset($event_data['file']) ? $event_data['file'] : array(),
            'comments'   => $this->recentComments($task),
            'actor'      => $this->actorName(),
        ));
    }

    /**
     * Who made the change.
     *
     * Kanboard puts this in the From line as "P Sudheep via Kanboard" and
     * nowhere in the body, so the moment an email is forwarded, printed or
     * filed, the one fact a reader wants is gone.
     *
     * Empty from cron and the CLI, where there is no session and nobody did
     * it - the template then says nothing rather than inventing an actor.
     *
     * @return string
     */
    protected function actorName()
    {
        if (! $this->userSession->isLogged()) {
            return '';
        }

        $user = $this->userSession->getAll();

        if (! empty($user['name'])) {
            return $user['name'];
        }

        return isset($user['username']) ? $user['username'] : '';
    }

    /**
     * The last few comments on the task.
     *
     * @param  array $task
     * @return array
     */
    protected function recentComments(array $task)
    {
        if (empty($task['id'])) {
            return array();
        }

        $comments = $this->commentModel->getAll($task['id']);

        if (count($comments) <= self::RECENT_COMMENTS) {
            return $comments;
        }

        return array_slice($comments, -self::RECENT_COMMENTS);
    }

    /**
     * Pull the body out of a full HTML document.
     *
     * Core's templates each emit <html><body>...</body></html>. Nesting one
     * inside the frame would put a second <html> in the middle of the
     * message, which Outlook in particular renders by discarding everything
     * after it.
     *
     * Falls through unchanged when there is no body tag, so a template that
     * returns a fragment is not silently emptied.
     *
     * @param  string $html
     * @return string
     */
    public static function unwrapDocument($html)
    {
        $html = (string) $html;

        if (preg_match('#<body[^>]*>(.*)</body>#si', $html, $matches) === 1) {
            return trim($matches[1]);
        }

        return trim($html);
    }
}
