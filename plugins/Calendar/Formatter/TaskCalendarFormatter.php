<?php

namespace Kanboard\Plugin\Calendar\Formatter;

use DateTime;
use Kanboard\Core\Filter\FormatterInterface;
use Kanboard\Formatter\BaseFormatter;

/**
 * Calendar event formatter for task filter
 *
 * @package  Kanboard\Plugin\Calendar\Formatter
 * @author   Frederic Guillot
 */
class TaskCalendarFormatter extends BaseFormatter implements FormatterInterface
{
    /**
     * Column used for event start date
     *
     * @access protected
     * @var string
     */
    protected $startColumn = '';

    /**
     * Column used for event expected end date
     *
     * @access protected
     * @var string
     */
    protected $expectedEndColumn = '';

    /**
     * Column used for event effective end date
     *
     * @access protected
     * @var string
     */
    protected $effectiveEndColumn = '';

    /**
     * Transform results to calendar events
     *
     * @access public
     * @param  string  $start_column            Column name for the start date
     * @param  string  $expected_end_column     Column name for the expected end date
     * @param  string  $effective_end_column    Column name for the effective end date
     * @return $this
     */
    public function setColumns($start_column, $expected_end_column = '', $effective_end_column = '')
    {
        $this->startColumn = $start_column;
        $this->expectedEndColumn = $expected_end_column ?: $start_column;
        $this->effectiveEndColumn = $effective_end_column ?: $this->expectedEndColumn;
        return $this;
    }

    /**
     * Transform tasks to calendar events
     *
     * @access public
     * @return array
     */
    public function format()
    {
        $events = array();

        foreach ($this->query->findAll() as $task) {
            $startDate = new DateTime();
            $startDate->setTimestamp($task[$this->startColumn]);

            $endDate = new DateTime();
            if (! empty($task[$this->expectedEndColumn])) {
                $endDate->setTimestamp($task[$this->expectedEndColumn]);
            }

            if ($this->expectedEndColumn != $this->effectiveEndColumn &&
                ! empty($task[$this->effectiveEndColumn]) &&
                $task[$this->effectiveEndColumn] != $task[$this->expectedEndColumn]) {
                $endDate->setTimestamp($task[$this->effectiveEndColumn]);
            }

            /* An event is all-day when every date on it sits at midnight -
               which is what a start date and a due date are. The old test
               also insisted the two be the same date, so a task running from
               the 2nd to the 6th was drawn as a timed event instead of a bar
               across those days: FullCalendar then gives each one its own
               line with a clock time in front of it, and a week holding a
               dozen tasks grows to four times the height of an empty one.
               That is what pushes the only rows with anything in them off
               the bottom of the screen.

               Read in the timezone the application is set to, so this stays
               true wherever the team is - if those timestamps do not land on
               midnight, the timezone is wrong and the clock times are the
               symptom worth seeing. */
            $allDay = $startDate->format('Hi') === '0000' && $endDate->format('Hi') === '0000';
            $format = $allDay ? 'Y-m-d' : 'Y-m-d\TH:i:s';

            /* FullCalendar treats the end of an all-day event as exclusive,
               so a bar drawn to the due date would stop the day before it.
               Only for a span: a single all-day event already reads as one
               day with start and end equal. */
            if ($allDay && $endDate > $startDate) {
                $endDate = clone $endDate;
                $endDate->modify('+1 day');
            }

            $tCode = ! empty($task['reference']) ? strtoupper($task['reference']) : sprintf('T%03d', $task['id']);

            $events[] = array(
                'timezoneParam' => $this->timezoneModel->getCurrentTimezone(),
                'id' => $task['id'],
                'project_id' => $task['project_id'],
                'title' => '#'.$tCode.' '.$task['title'],
                'backgroundColor' => $this->colorModel->getBackgroundColor($task['color_id']),
                'borderColor' => $this->colorModel->getBorderColor($task['color_id']),
                'textColor' => '#1e293b',
                'url' => $this->helper->url->to('TaskViewController', 'show', array('task_id' => $task['id'], 'project_id' => $task['project_id'])),
                'start' => $startDate->format($format),
                'end' => $endDate->format($format),
                'editable' => $allDay,
                'allday' => $allDay,
            );
        }

        return $events;
    }
}
