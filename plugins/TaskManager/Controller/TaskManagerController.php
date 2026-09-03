<?php

namespace Kanboard\Plugin\TaskManager\Controller;

use Kanboard\Controller\BaseController;

/**
 * Full-width Task Tree page.
 *
 * The tree itself is rendered by TaskManager:project_overview/tree, the same
 * partial the Project Overview page uses, so there is one implementation.
 *
 * @package Kanboard\Plugin\TaskManager\Controller
 */
class TaskManagerController extends BaseController
{
    public function show()
    {
        $project = $this->getProject();

        $this->response->html($this->helper->layout->project('TaskManager:show', array(
            'project' => $project,
            'title'   => $project['name'].' - '.t('Task Tree'),
        ), 'TaskManager:project/no_sidebar'));
    }
}
