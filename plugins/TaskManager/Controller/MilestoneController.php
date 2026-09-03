<?php

namespace Kanboard\Plugin\TaskManager\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;

/**
 * Milestone CRUD.
 *
 * Creating, editing and removing a milestone is a Lead-and-above action, the
 * same guardrail that governs adding and deleting tasks.
 *
 * @package Kanboard\Plugin\TaskManager\Controller
 */
class MilestoneController extends BaseController
{
    public function index()
    {
        $project = $this->getProject();

        $this->response->html($this->helper->layout->project('TaskManager:milestone/index', array(
            'project'    => $project,
            'milestones' => $this->milestoneModel->getAllWithProgress($project['id']),
            'is_lead'    => $this->helper->taskTree->canManageTasks($project['id']),
            'title'      => $project['name'].' - '.t('Milestones'),
        ), 'TaskManager:project/no_sidebar'));
    }

    public function create(array $values = array(), array $errors = array())
    {
        $project = $this->getProjectForLead();

        $this->response->html($this->template->render('TaskManager:milestone/create', array(
            'project' => $project,
            'values'  => $values + array('project_id' => $project['id']),
            'errors'  => $errors,
            'users'   => $this->projectUserRoleModel->getAssignableUsersList($project['id']),
        )));
    }

    public function save()
    {
        $project = $this->getProjectForLead();
        $values  = $this->request->getValues();
        $values['project_id'] = $project['id'];

        list($valid, $errors) = $this->validate($values);

        if ($valid && $this->milestoneModel->create($values) !== false) {
            $this->flash->success(t('Milestone created successfully.'));
            $this->response->redirect($this->helper->url->to('MilestoneController', 'index', array('project_id' => $project['id'], 'plugin' => 'TaskManager')), true);
            return;
        }

        $this->create($values, $errors);
    }

    public function edit(array $values = array(), array $errors = array())
    {
        $project   = $this->getProjectForLead();
        $milestone = $this->getMilestone($project);

        $this->response->html($this->template->render('TaskManager:milestone/edit', array(
            'project' => $project,
            'values'  => empty($values) ? $milestone : $values,
            'errors'  => $errors,
            'users'   => $this->projectUserRoleModel->getAssignableUsersList($project['id']),
        )));
    }

    public function update()
    {
        $project   = $this->getProjectForLead();
        $milestone = $this->getMilestone($project);
        $values    = $this->request->getValues();
        $values['id'] = $milestone['id'];
        $values['project_id'] = $project['id'];

        list($valid, $errors) = $this->validate($values);

        if ($valid && $this->milestoneModel->update($values)) {
            $this->flash->success(t('Milestone updated successfully.'));
            $this->response->redirect($this->helper->url->to('MilestoneController', 'index', array('project_id' => $project['id'], 'plugin' => 'TaskManager')), true);
            return;
        }

        $this->edit($values, $errors);
    }

    public function confirm()
    {
        $project   = $this->getProjectForLead();
        $milestone = $this->getMilestone($project);

        $this->response->html($this->template->render('TaskManager:milestone/remove', array(
            'project'   => $project,
            'milestone' => $milestone,
        )));
    }

    public function remove()
    {
        $project   = $this->getProjectForLead();
        $milestone = $this->getMilestone($project);
        $this->checkCSRFParam();

        if ($this->milestoneModel->remove($milestone['id'])) {
            $this->flash->success(t('Milestone removed. Its tasks were kept and are now unassigned.'));
        } else {
            $this->flash->failure(t('Unable to remove this milestone.'));
        }

        $this->response->redirect($this->helper->url->to('MilestoneController', 'index', array('project_id' => $project['id'], 'plugin' => 'TaskManager')));
    }

    /**
     * @return array
     * @throws AccessForbiddenException
     */
    protected function getProjectForLead()
    {
        $project = $this->getProject();

        if (! $this->helper->taskTree->canManageTasks($project['id'])) {
            throw new AccessForbiddenException(t('Only a Team Lead or above can manage milestones.'));
        }

        return $project;
    }

    /**
     * @param  array $project
     * @return array
     * @throws AccessForbiddenException
     */
    protected function getMilestone(array $project)
    {
        $milestone = $this->milestoneModel->getById($this->request->getIntegerParam('milestone_id'));

        if (empty($milestone) || $milestone['project_id'] != $project['id']) {
            throw new AccessForbiddenException(t('Milestone not found in this project.'));
        }

        return $milestone;
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

        if (! empty($values['date_start']) && ! empty($values['date_due'])) {
            $start = $this->dateParser->getTimestamp($values['date_start']);
            $due   = $this->dateParser->getTimestamp($values['date_due']);

            if ($start > 0 && $due > 0 && $due < $start) {
                $errors['date_due'] = array(t('The due date cannot be before the start date.'));
            }
        }

        return array(empty($errors), $errors);
    }

}
