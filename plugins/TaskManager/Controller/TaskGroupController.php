<?php

namespace Kanboard\Plugin\TaskManager\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;

/**
 * Task list CRUD.
 *
 * Called TaskGroup, not TaskList: Kanboard core already has a
 * TaskListController (the "List" view). The namespaces do not actually
 * collide, but two controllers with one name in the logs and the URL bar
 * would be a standing trap.
 *
 * @package Kanboard\Plugin\TaskManager\Controller
 */
class TaskGroupController extends BaseController
{
    /**
     * Each list is a group header with its tasks underneath, and the tasks
     * in no list get a group of their own at the end.
     *
     * The counts used to come from two COUNT(*) queries per list and the
     * tasks themselves were never shown, which made the page a directory of
     * names rather than somewhere work could be seen. One pass over the
     * project's tasks now does both, so a list's header can never disagree
     * with the rows under it.
     */
    public function index()
    {
        $project    = $this->getProject();
        $taskLists  = $this->taskListModel->getAll($project['id']);
        $milestones = $this->milestoneModel->getList($project['id']);

        $tasksByList = $this->getTasksGroupedByList($project['id']);

        foreach ($taskLists as &$tl) {
            $listId = (int) $tl['id'];
            $tasks  = isset($tasksByList[$listId]) ? $tasksByList[$listId] : array();

            $tl['tasks']           = $tasks;
            $tl['total_tasks']     = count($tasks);
            $tl['closed_tasks']    = $this->countClosed($tasks);
            $tl['progress']        = $tl['total_tasks'] > 0
                ? (int) round(($tl['closed_tasks'] / $tl['total_tasks']) * 100)
                : 0;
            $tl['milestone_title'] = isset($milestones[$tl['milestone_id']]) ? $milestones[$tl['milestone_id']] : t('None');
        }

        unset($tl);

        $unfiled = isset($tasksByList[0]) ? $tasksByList[0] : array();

        $this->response->html($this->helper->layout->app('TaskManager:task_list/index', array(
            'project'    => $project,
            'task_lists' => $taskLists,
            'unfiled'    => $unfiled,
            'title'      => t('Task Lists') . ' — ' . $project['name'],
        )));
    }

    /**
     * Every task in the project, open and closed, keyed by its task list.
     *
     * Closed tasks are included deliberately: they are what the progress bar
     * counts, and a list that reads 80% done while showing only the four
     * tasks still open would be unreadable.
     *
     * @param  integer $projectId
     * @return array  task_list_id => tasks
     */
    protected function getTasksGroupedByList($projectId)
    {
        /* The same lookup the overview tree uses, so a person's name reads
           identically on both pages. */
        $users   = $this->userModel->getActiveUsersList();
        $columns = $this->columnModel->getList($projectId);

        $tasks = $this->db
            ->table(\Kanboard\Model\TaskModel::TABLE)
            ->eq('project_id', $projectId)
            ->asc('task_list_id')
            ->asc('position')
            ->asc('id')
            ->findAll();

        $grouped = array();

        foreach ($tasks as $task) {
            $task['assignee_name'] = isset($users[$task['owner_id']]) ? $users[$task['owner_id']] : t('Unassigned');
            $task['column_title']  = isset($columns[$task['column_id']]) ? $columns[$task['column_id']] : '';
            $task['is_closed']     = empty($task['is_active']);

            $grouped[(int) $task['task_list_id']][] = $task;
        }

        return $grouped;
    }

    /**
     * @param  array $tasks
     * @return integer
     */
    protected function countClosed(array $tasks)
    {
        $closed = 0;

        foreach ($tasks as $task) {
            if (! empty($task['is_closed'])) {
                $closed++;
            }
        }

        return $closed;
    }

    public function create(array $values = array(), array $errors = array())
    {
        $project = $this->getProjectForLead();

        $this->response->html($this->template->render('TaskManager:task_list/create', array(
            'project'    => $project,
            'values'     => $values + array('project_id' => $project['id'], 'milestone_id' => $this->request->getIntegerParam('milestone_id')),
            'errors'     => $errors,
            'milestones' => $this->milestoneModel->getList($project['id']),
        )));
    }

    public function save()
    {
        $project = $this->getProjectForLead();
        $values  = $this->request->getValues();
        $values['project_id'] = $project['id'];

        list($valid, $errors) = $this->validate($values);

        if ($valid && $this->taskListModel->create($values) !== false) {
            $this->flash->success(t('Task list created successfully.'));
            $this->response->redirect($this->helper->url->to('ProjectOverviewController', 'show', array('project_id' => $project['id'])), true);
            return;
        }

        $this->create($values, $errors);
    }

    public function edit(array $values = array(), array $errors = array())
    {
        $project  = $this->getProjectForLead();
        $taskList = $this->getTaskList($project);

        $this->response->html($this->template->render('TaskManager:task_list/edit', array(
            'project'    => $project,
            'values'     => empty($values) ? $taskList : $values,
            'errors'     => $errors,
            'milestones' => $this->milestoneModel->getList($project['id']),
        )));
    }

    public function update()
    {
        $project  = $this->getProjectForLead();
        $taskList = $this->getTaskList($project);
        $values   = $this->request->getValues();
        $values['id'] = $taskList['id'];
        $values['project_id'] = $project['id'];

        list($valid, $errors) = $this->validate($values);

        if ($valid && $this->taskListModel->update($values)) {
            $this->flash->success(t('Task list updated successfully.'));
            $this->response->redirect($this->helper->url->to('ProjectOverviewController', 'show', array('project_id' => $project['id'])), true);
            return;
        }

        $this->edit($values, $errors);
    }

    public function confirm()
    {
        $project  = $this->getProjectForLead();
        $taskList = $this->getTaskList($project);

        $this->response->html($this->template->render('TaskManager:task_list/remove', array(
            'project'   => $project,
            'task_list' => $taskList,
        )));
    }

    public function remove()
    {
        $project  = $this->getProjectForLead();
        $taskList = $this->getTaskList($project);
        $this->checkCSRFParam();

        if ($this->taskListModel->remove($taskList['id'])) {
            $this->flash->success(t('Task list removed. Its tasks were kept and are now unassigned.'));
        } else {
            $this->flash->failure(t('Unable to remove this task list.'));
        }

        $this->response->redirect($this->helper->url->to('ProjectOverviewController', 'show', array('project_id' => $project['id'])));
    }

    /**
     * @return array
     * @throws AccessForbiddenException
     */
    protected function getProjectForLead()
    {
        $project = $this->getProject();

        if (! $this->helper->taskTree->canManageTasks($project['id'])) {
            throw new AccessForbiddenException(t('Only a Team Lead or above can manage task lists.'));
        }

        return $project;
    }

    /**
     * @param  array $project
     * @return array
     * @throws AccessForbiddenException
     */
    protected function getTaskList(array $project)
    {
        $taskList = $this->taskListModel->getById($this->request->getIntegerParam('task_list_id'));

        if (empty($taskList) || $taskList['project_id'] != $project['id']) {
            throw new AccessForbiddenException(t('Task list not found in this project.'));
        }

        return $taskList;
    }

    /**
     * @param  array $values
     * @return array
     */
    protected function validate(array $values)
    {
        $errors = array();

        if (empty($values['title'])) {
            $errors['title'] = array(t('This field is required'));
        }

        return array(empty($errors), $errors);
    }
}
