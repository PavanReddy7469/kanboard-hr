<?php

namespace Kanboard\Plugin\Calendar\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Filter\TaskAssigneeFilter;
use Kanboard\Filter\TaskDueDateRangeFilter;
use Kanboard\Filter\TaskProjectFilter;
use Kanboard\Filter\TaskStatusFilter;
use Kanboard\Model\TaskModel;

/**
 * Calendar Controller
 *
 * @package  Kanboard\Plugin\Calendar\Controller
 * @author   Frederic Guillot
 * @author   Timo Litzbarski
 */
class CalendarController extends BaseController
{
    public function user()
    {
        $user = $this->getUser();
        $projects = $this->projectUserRoleModel->getActiveProjectsByUser($user['id']);

        $this->response->html($this->helper->layout->app('Calendar:calendar/user', array(
            'user'     => $user,
            'projects' => $projects,
            'title'    => t('Calendar'),
        )));
    }

    public function project()
    {
        $project = $this->getProject();

        $this->response->html($this->helper->layout->app('Calendar:calendar/project', array(
            'project'     => $project,
            'title'       => $project['name'],
            'description' => $this->helper->projectHeader->getDescription($project),
        )));
    }

    public function projectEvents()
    {
        $projectId = $this->request->getIntegerParam('project_id');
        $startRange = $this->request->getStringParam('start');
        $endRange = $this->request->getStringParam('end');
        $priority = $this->request->getIntegerParam('priority');
        $status = $this->request->getStringParam('status', 'open');
        $search = trim($this->request->getStringParam('search'));
        $startColumn = $this->configModel->get('calendar_project_tasks', 'date_started');

        $builder = $this->taskLexer->build($this->userSession->getFilters($projectId))
            ->withFilter(new TaskProjectFilter($projectId));

        if ($status === 'closed') {
            $builder->withFilter(new TaskStatusFilter(TaskModel::STATUS_CLOSED));
        } elseif ($status !== 'all') {
            $builder->withFilter(new TaskStatusFilter(TaskModel::STATUS_OPEN));
        }

        if ($priority > 0) {
            $builder->getQuery()->eq(TaskModel::TABLE.'.priority', $priority);
        }

        if (! empty($search)) {
            $builder->getQuery()->ilike(TaskModel::TABLE.'.title', '%'.$search.'%');
        }

        $dueDateOnlyEvents = clone $builder;
        $dueDateOnlyEvents = $dueDateOnlyEvents
            ->withFilter(new TaskDueDateRangeFilter(array($startRange, $endRange)))
            ->format($this->taskCalendarFormatter->setColumns('date_due'));

        $startAndDueDateQueryBuilder = clone $builder;
        $startAndDueDateQueryBuilder
            ->getQuery()
            ->addCondition($this->getConditionForTasksWithStartAndDueDate($startRange, $endRange, $startColumn, 'date_due', 'date_completed'));

        $startAndDueDateEvents = $startAndDueDateQueryBuilder
            ->format($this->taskCalendarFormatter->setColumns($startColumn, 'date_due', 'date_completed'));

        $events = array_merge($dueDateOnlyEvents, $startAndDueDateEvents);

        $events = $this->hook->merge('controller:calendar:project:events', $events, array(
            'project_id' => $projectId,
            'start' => $startRange,
            'end' => $endRange,
        ));

        $this->response->json($events);
    }

    public function userEvents()
    {
        $user_id = $this->request->getIntegerParam('user_id');
        $projectId = $this->request->getIntegerParam('project_id');
        $priority = $this->request->getIntegerParam('priority');
        $status = $this->request->getStringParam('status', 'open');
        $search = trim($this->request->getStringParam('search'));
        $startRange = $this->request->getStringParam('start');
        $endRange = $this->request->getStringParam('end');
        $startColumn = $this->configModel->get('calendar_project_tasks', 'date_started');

        $builder = clone $this->taskQuery;
        if ($user_id > 0) {
            $builder->withFilter(new TaskAssigneeFilter($user_id));
        }

        if ($projectId > 0) {
            $builder->withFilter(new TaskProjectFilter($projectId));
        }

        if ($status === 'closed') {
            $builder->withFilter(new TaskStatusFilter(TaskModel::STATUS_CLOSED));
        } elseif ($status !== 'all') {
            $builder->withFilter(new TaskStatusFilter(TaskModel::STATUS_OPEN));
        }

        if ($priority > 0) {
            $builder->getQuery()->eq(TaskModel::TABLE.'.priority', $priority);
        }

        if (! empty($search)) {
            $builder->getQuery()->ilike(TaskModel::TABLE.'.title', '%'.$search.'%');
        }

        $dueDateOnlyEvents = clone $builder;
        $dueDateOnlyEvents = $dueDateOnlyEvents
            ->withFilter(new TaskDueDateRangeFilter(array($startRange, $endRange)))
            ->format($this->taskCalendarFormatter->setColumns('date_due'));

        $startAndDueDateQueryBuilder = clone $builder;
        $startAndDueDateQueryBuilder
            ->getQuery()
            ->addCondition($this->getConditionForTasksWithStartAndDueDate($startRange, $endRange, $startColumn, 'date_due', 'date_completed'));

        $startAndDueDateEvents = $startAndDueDateQueryBuilder
            ->format($this->taskCalendarFormatter->setColumns($startColumn, 'date_due', 'date_completed'));

        $events = array_merge($dueDateOnlyEvents, $startAndDueDateEvents);

        $events = $this->hook->merge('controller:calendar:user:events', $events, array(
            'user_id' => $user_id,
            'start' => $startRange,
            'end' => $endRange,
        ));

        $this->response->json($events);
    }

    public function save()
    {
        if ($this->request->isAjax() && $this->request->isPost()) {
            $values = $this->request->getJson();

            $this->taskModificationModel->update(array(
                'id' => $values['task_id'],
                'date_due' => substr($values['date_due'], 0, 10),
            ));
        }
    }

    protected function getConditionForTasksWithStartAndDueDate($startTime, $endTime, $startColumn, $expectedEndColumn, $effectiveEndColumn)
    {
        $startTime = strtotime($startTime);
        $endTime = strtotime($endTime);
        $startColumn = $this->db->escapeIdentifier($startColumn);
        $expectedEndColumn = $this->db->escapeIdentifier($expectedEndColumn);
        $effectiveEndColumn = $this->db->escapeIdentifier($effectiveEndColumn);

        $conditions = array(
            "($startColumn >= '$startTime' AND $startColumn <= '$endTime')",
            "($startColumn <= '$startTime' AND ($expectedEndColumn >= '$startTime' OR $effectiveEndColumn >= '$startTime'))",
            "($startColumn <= '$startTime' AND ($expectedEndColumn = '0' OR $expectedEndColumn IS NULL) AND ($effectiveEndColumn = '0' OR $effectiveEndColumn IS NULL))",
        );

        return $startColumn.' IS NOT NULL AND '.$startColumn.' > 0 AND ('.implode(' OR ', $conditions).')';
    }
}
