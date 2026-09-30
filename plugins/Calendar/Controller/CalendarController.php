<?php

namespace Kanboard\Plugin\Calendar\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Filter\TaskAssigneeFilter;
use Kanboard\Filter\TaskDueDateRangeFilter;
use Kanboard\Filter\TaskProjectFilter;
use Kanboard\Filter\TaskProjectsFilter;
use Kanboard\Filter\TaskStatusFilter;
use Kanboard\Model\ProjectModel;
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
            'user'      => $user,
            'projects'  => $projects,
            'assignees' => $this->getAssignableUsers(),
            'title'     => t('Calendar'),
        )));
    }

    /**
     * Everyone who can be picked in the Assignee filter: the people who can
     * hold a task on any project this viewer can see.
     *
     * @return array  user id => name
     */
    protected function getAssignableUsers()
    {
        $users = array();

        foreach ($this->getVisibleProjectIds() as $projectId) {
            foreach ($this->projectUserRoleModel->getAssignableUsersList($projectId, false) as $userId => $userName) {
                $users[$userId] = $userName;
            }
        }

        /* Plain string order, not natural order: strnatcasecmp ignores the
           space in a name, so "P Sudheep" compares as "PSudheep" and sorts
           after "Pavan Reddy". Natural order earns its keep on embedded
           numbers, which people's names do not have. */
        asort($users, SORT_STRING | SORT_FLAG_CASE);

        return $users;
    }

    /**
     * The projects whose tasks this viewer is allowed to see.
     *
     * userEvents() filters by assignee and by nothing else, so asking it for
     * somebody else's tasks - or for everybody's - would have returned tasks
     * from projects the viewer is not a member of. That did not show up while
     * the page only ever asked for the signed-in user's own work; it does the
     * moment the Assignee filter lets you ask for anyone.
     *
     * @return array
     */
    protected function getVisibleProjectIds()
    {
        if ($this->userSession->isAdmin()) {
            return $this->db
                ->table(ProjectModel::TABLE)
                ->eq('is_active', ProjectModel::ACTIVE)
                ->findAllByColumn('id');
        }

        return array_keys($this->projectUserRoleModel->getActiveProjectsByUser($this->userSession->getId()));
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

        $visibleProjectIds = $this->getVisibleProjectIds();

        if (empty($visibleProjectIds)) {
            $this->response->json(array());
            return;
        }

        $builder = clone $this->taskQuery;

        /* Scope first, and always. The assignee filter alone decides whose
           work is shown, not which projects it may come from, so without
           this a request for another person's tasks - or for everyone's -
           would return work from projects the viewer cannot open. */
        $builder->withFilter(new TaskProjectsFilter($visibleProjectIds));

        if ($user_id > 0) {
            $builder->withFilter(new TaskAssigneeFilter($user_id));
        }

        if ($projectId > 0 && in_array($projectId, array_map('intval', $visibleProjectIds), true)) {
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
