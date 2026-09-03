<?php

namespace Kanboard\Plugin\TaskManager\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Core\Controller\AccessForbiddenException;
use Kanboard\Plugin\TaskManager\Subscriber\RescheduleSubscriber;

/**
 * Per-project TaskManager settings.
 *
 * @package Kanboard\Plugin\TaskManager\Controller
 */
class TaskManagerConfigController extends BaseController
{
    public function show()
    {
        $project = $this->getProjectForLead();

        $this->response->html($this->helper->layout->project('TaskManager:config/show', array(
            'project'         => $project,
            'auto_reschedule' => (int) $this->projectMetadataModel->get($project['id'], RescheduleSubscriber::SETTING, 0),
            'title'           => $project['name'].' - '.t('Scheduling'),
        )));
    }

    public function save()
    {
        $project = $this->getProjectForLead();
        $values  = $this->request->getValues();

        $this->projectMetadataModel->save($project['id'], array(
            RescheduleSubscriber::SETTING => empty($values['auto_reschedule']) ? '0' : '1',
        ));

        $this->flash->success(t('Scheduling settings saved.'));
        $this->response->redirect($this->helper->url->to('TaskManagerConfigController', 'show', array('project_id' => $project['id'], 'plugin' => 'TaskManager')));
    }

    /**
     * @return array
     * @throws AccessForbiddenException
     */
    protected function getProjectForLead()
    {
        $project = $this->getProject();

        if (! $this->helper->taskTree->canManageTasks($project['id'])) {
            throw new AccessForbiddenException(t('Only a Team Lead or above can change scheduling settings.'));
        }

        return $project;
    }
}
