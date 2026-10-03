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
        /* Task Lists is no longer a page. Its content is the Tasks tab
           grouped by list, so this route sends people there rather than
           404ing a link somebody bookmarked or a redirect left behind in the
           create/edit/remove flows. */
        $project = $this->getProject();

        $this->response->redirect($this->helper->url->to('TaskGridController', 'show', array(
            'plugin'     => 'TaskManager',
            'project_id' => $project['id'],
            'group_by'   => 'task_list',
        )), true);
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
            $this->response->redirect($this->helper->url->to('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'group_by' => 'task_list')), true);
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
            $this->response->redirect($this->helper->url->to('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'group_by' => 'task_list')), true);
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

        $this->response->redirect($this->helper->url->to('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'group_by' => 'task_list')));
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
