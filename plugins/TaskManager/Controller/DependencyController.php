<?php

namespace Kanboard\Plugin\TaskManager\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;

/**
 * Typed dependencies between tasks, plus the JSON feed the Gantt overlay reads.
 *
 * @package Kanboard\Plugin\TaskManager\Controller
 */
class DependencyController extends BaseController
{
    public function create(array $values = array(), array $errors = array())
    {
        $task    = $this->getTask();
        $project = $this->getProjectForLead($task['project_id']);

        $this->response->html($this->template->render('TaskManager:task/dependency_create', array(
            'task'    => $task,
            'project' => $project,
            'values'  => $values + array('task_id' => $task['id'], 'dependency_type' => 'FS', 'lag_days' => 0),
            'errors'  => $errors,
            'types'   => $this->dependencyModel->getTypes(),
            'tasks'   => $this->getCandidateTasks($task),
        )));
    }

    public function save()
    {
        $task = $this->getTask();
        $this->getProjectForLead($task['project_id']);

        $values = $this->request->getValues();
        $values['task_id']    = $task['id'];
        $values['project_id'] = $task['project_id'];

        list($valid, $error) = $this->dependencyModel->validate($task['id'], isset($values['depends_on_id']) ? $values['depends_on_id'] : 0);

        if ($valid && $this->dependencyModel->create($values) !== false) {
            $this->flash->success(t('Dependency added.'));
            $this->response->redirect($this->helper->url->to('TaskViewController', 'show', array('task_id' => $task['id'], 'project_id' => $task['project_id'])), true);
            return;
        }

        $this->create($values, array('depends_on_id' => array($error ?: t('Unable to add this dependency.'))));
    }

    public function confirm()
    {
        $task = $this->getTask();
        $this->getProjectForLead($task['project_id']);
        $dependency = $this->getDependency($task);

        $this->response->html($this->template->render('TaskManager:task/dependency_remove', array(
            'task'       => $task,
            'dependency' => $dependency,
        )));
    }

    public function remove()
    {
        $task = $this->getTask();
        $this->getProjectForLead($task['project_id']);
        $dependency = $this->getDependency($task);
        $this->checkCSRFParam();

        if ($this->dependencyModel->remove($dependency['id'])) {
            $this->flash->success(t('Dependency removed.'));
        } else {
            $this->flash->failure(t('Unable to remove this dependency.'));
        }

        $this->response->redirect($this->helper->url->to('TaskViewController', 'show', array('task_id' => $task['id'], 'project_id' => $task['project_id'])));
    }

    /**
     * Dependency edges for one project, consumed by the Gantt arrow overlay.
     */
    public function json()
    {
        $project = $this->getProject();
        $edges   = array();

        foreach ($this->dependencyModel->getAllByProject($project['id']) as $row) {
            $edges[] = array(
                'task_id'       => (int) $row['task_id'],
                'depends_on_id' => (int) $row['depends_on_id'],
                'type'          => $row['dependency_type'],
                'lag'           => (int) $row['lag_days'],
            );
        }

        $this->response->json(array('edges' => $edges));
    }

    /**
     * @return array
     */
    protected function getTask()
    {
        $task = $this->taskFinderModel->getById($this->request->getIntegerParam('task_id'));

        if (empty($task)) {
            throw new AccessForbiddenException(t('Task not found.'));
        }

        return $task;
    }

    /**
     * @param  integer $projectId
     * @return array
     * @throws AccessForbiddenException
     */
    protected function getProjectForLead($projectId)
    {
        if (! $this->helper->taskTree->canManageTasks($projectId)) {
            throw new AccessForbiddenException(t('Only a Team Lead or above can manage dependencies.'));
        }

        return $this->projectModel->getById($projectId);
    }

    /**
     * @param  array $task
     * @return array
     */
    protected function getDependency(array $task)
    {
        $dependency = $this->dependencyModel->getById($this->request->getIntegerParam('dependency_id'));

        if (empty($dependency) || $dependency['task_id'] != $task['id']) {
            throw new AccessForbiddenException(t('Dependency not found for this task.'));
        }

        return $dependency;
    }

    /**
     * Tasks in the same project that this one could depend on, minus itself
     * and anything that would close a loop.
     *
     * @param  array $task
     * @return array
     */
    protected function getCandidateTasks(array $task)
    {
        $candidates = array(0 => t('Select a task'));

        foreach ($this->taskFinderModel->getAll($task['project_id']) as $candidate) {
            if ($candidate['id'] == $task['id']) {
                continue;
            }

            if ($this->dependencyModel->wouldCreateCycle($task['id'], $candidate['id'])) {
                continue;
            }

            $candidates[$candidate['id']] = '#'.$candidate['id'].' '.$candidate['title'];
        }

        return $candidates;
    }
}
