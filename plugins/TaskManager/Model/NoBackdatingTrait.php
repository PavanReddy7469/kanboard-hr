<?php

namespace Kanboard\Plugin\TaskManager\Model;

/**
 * Refuses dates in the past.
 *
 * Work is planned forward. A start or due date earlier than today is either a
 * mistake or an attempt to make late work look on time, and neither should be
 * possible for anyone - administrators included. There is deliberately no role
 * that can override this.
 *
 * Two rules make it usable rather than merely strict:
 *
 *   - Clearing a date is always allowed. Only a new past value is refused.
 *   - An unchanged date is never refused. A task created last month keeps its
 *     original start date, and editing its title must not fail because of a
 *     date nobody touched. Only a date being MOVED into the past is blocked.
 *
 * "Today" is midnight in the application's configured timezone: TimezoneModel
 * calls date_default_timezone_set() during boot, so PHP's own notion of today
 * already matches what users see.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
trait NoBackdatingTrait
{
    /**
     * Why the last write was refused, for the caller to show the user.
     *
     * @var string
     */
    protected $backdatingRefusal = '';

    /**
     * @return string
     */
    public function getBackdatingRefusal()
    {
        return $this->backdatingRefusal;
    }

    /**
     * Midnight today. A date landing anywhere inside today is acceptable;
     * only something earlier than this instant is backdating.
     *
     * @return integer
     */
    protected function startOfToday()
    {
        return (int) strtotime('today');
    }

    /**
     * Values arrive either as a user-entered string or as an epoch integer,
     * depending on whether they came from a form, the Gantt drag handler or
     * the API. Normalise both.
     *
     * @param  mixed $value
     * @return integer|null  null when there is no date at all
     */
    protected function toTimestamp($value)
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $timestamp = $this->dateParser->getTimestamp($value);

        return $timestamp > 0 ? (int) $timestamp : null;
    }

    /**
     * @param  integer $a
     * @param  integer $b
     * @return boolean
     */
    protected function isSameDay($a, $b)
    {
        return date('Y-m-d', $a) === date('Y-m-d', $b);
    }

    /**
     * Which of the given date fields are being set to a past date.
     *
     * @param  array $values    incoming values
     * @param  array $fields    field name => human label
     * @param  array $existing  the current row, when this is an update
     * @return array            human labels of the offending fields
     */
    protected function findBackdatedFields(array $values, array $fields, array $existing = array())
    {
        $today   = $this->startOfToday();
        $refused = array();

        foreach ($fields as $field => $label) {
            if (! array_key_exists($field, $values)) {
                continue;
            }

            $new = $this->toTimestamp($values[$field]);

            if ($new === null) {
                continue;
            }

            // Leaving an old date exactly as it was is not backdating.
            if (! empty($existing[$field])) {
                $old = $this->toTimestamp($existing[$field]);

                if ($old !== null && $this->isSameDay($old, $new)) {
                    continue;
                }
            }

            if ($new < $today) {
                $refused[] = $label;
            }
        }

        return $refused;
    }

    /**
     * Build the message shown to the user, and record it.
     *
     * @param  array $refused
     * @return boolean  always false, so callers can `return $this->refuse(...)`
     */
    protected function refuseBackdating(array $refused)
    {
        $this->backdatingRefusal = t(
            'Dates cannot be set in the past: %s. Today is the earliest date allowed.',
            implode(', ', $refused)
        );

        /* Not the flash: core's controllers call flash->failure() with a
           generic "unable to save" immediately after we return false, which
           would overwrite anything we put there. This uses a channel of its
           own, rendered by a template:layout:top hook and cleared on read, so
           the real reason survives and no core controller has to be patched. */
        session_set('sb_backdating_message', $this->backdatingRefusal);

        return false;
    }
}
