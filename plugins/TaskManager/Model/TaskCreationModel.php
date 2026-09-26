<?php

namespace Kanboard\Plugin\TaskManager\Model;

use Kanboard\Model\TaskModel;

/**
 * Task creation, with backdating refused.
 *
 * Overriding the model rather than the validator is deliberate. The validator
 * only covers the web forms; task creation also happens through the API, the
 * automatic actions, recurrence and the bulk tools, and each of those calls
 * this method directly. Gating here is the only place that catches all of them.
 *
 * @package Kanboard\Plugin\TaskManager\Model
 */
class TaskCreationModel extends \Kanboard\Model\TaskCreationModel
{
    use NoBackdatingTrait;

    /**
     * @param  array $values
     * @return integer  0 when refused, as core returns 0 on failure
     */
    public function create(array $values)
    {
        $this->backdatingRefusal = '';

        $refused = $this->findBackdatedFields($values, array(
            'date_started' => t('Start date'),
            'date_due'     => t('Due date'),
        ));

        if (! empty($refused)) {
            $this->refuseBackdating($refused);
            return 0;
        }

        // The creation timestamp is a record of when this happened, not a
        // field anyone gets to choose. Core stamps it in prepare(); drop any
        // value supplied by a caller so the API cannot pre-date a task.
        unset($values['date_creation'], $values['date_modification']);

        return parent::create($values);
    }

    /**
     * The project a task is being created in, remembered between prepare()
     * and generateUniqueTaskCode(). Core calls the generator itself and
     * passes it nothing, so the project has to reach it this way.
     *
     * @var integer
     */
    protected $pendingProjectId = 0;

    /**
     * Core's prepare() truncates any reference to four characters, which would
     * turn MC01-007 into MC01 and make every task in a project identical. The
     * generated case is handled by overriding the generator below; this covers
     * the other branch, where a caller supplied a reference of its own.
     *
     * @param array $values
     */
    protected function prepare(array &$values)
    {
        $this->pendingProjectId = isset($values['project_id']) ? (int) $values['project_id'] : 0;

        $supplied = isset($values['reference']) ? strtoupper(trim((string) $values['reference'])) : '';

        if ($supplied !== '') {
            // Hide it from core so it cannot be cut down, then put it back.
            $values['reference'] = '';
            parent::prepare($values);
            $values['reference'] = $supplied;
            return;
        }

        parent::prepare($values);
    }

    /**
     * Task codes read as PROJECTCODE-NNN: MC01-001, MC01-002, FW02-001. The
     * number counts within the project, so a task's code says which project it
     * belongs to without anyone having to look it up.
     *
     * Falls back to core's random four-character code when there is no project
     * to derive from - the API and the tests both create tasks that way.
     *
     * @return string
     */
    public function generateUniqueTaskCode()
    {
        if ($this->pendingProjectId > 0) {
            $code = $this->generateProjectTaskCode($this->pendingProjectId);

            if ($code !== '') {
                return $code;
            }
        }

        return parent::generateUniqueTaskCode();
    }

    /**
     * Next free number within one project.
     *
     * Counts from the highest already issued rather than from how many tasks
     * exist, so a number is never handed out twice - including the numbers of
     * tasks that have since been deleted. A deleted MC01-004 leaves a gap on
     * purpose: reissuing it would point an old document at different work.
     *
     * @param  integer $projectId
     * @return string  '' when the project cannot be read
     */
    protected function generateProjectTaskCode($projectId)
    {
        $project = $this->projectModel->getById($projectId);

        if (empty($project)) {
            return '';
        }

        $prefix = ! empty($project['identifier'])
            ? strtoupper(substr($project['identifier'], 0, 4))
            : sprintf('P%03d', $project['id']);

        $taken   = array();
        $highest = 0;
        $pattern = '/^'.preg_quote($prefix, '/').'-([0-9]+)$/';

        $rows = $this->db->table(TaskModel::TABLE)
                         ->eq('project_id', (int) $projectId)
                         ->columns('reference')
                         ->findAll();

        foreach ($rows as $row) {
            $reference = strtoupper((string) $row['reference']);
            $taken[$reference] = true;

            if (preg_match($pattern, $reference, $match)) {
                $highest = max($highest, (int) $match[1]);
            }
        }

        /* Three digits keeps them sorting correctly and reads well. Past 999
           sprintf simply widens the number rather than wrapping, so a very
           large project keeps working - it just stops aligning. */
        for ($number = $highest + 1; $number <= 99999; $number++) {
            $code = $prefix.'-'.sprintf('%03d', $number);

            if (! isset($taken[$code])) {
                return $code;
            }
        }

        return '';
    }
}
