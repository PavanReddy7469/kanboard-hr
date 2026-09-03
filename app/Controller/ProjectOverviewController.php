<?php

namespace Kanboard\Controller;

/**
 * Project Overview Controller
 *
 * Redirects directly to the Project Tasks view (TaskGridController) to remove
 * the redundant/glitchy legacy dashboard as requested.
 *
 * @package Kanboard\Controller
 */
class ProjectOverviewController extends BaseController
{
    /**
     * Show project overview - redirected directly to project Tasks grid
     */
    public function show()
    {
        $project = $this->getProject();
        $this->response->redirect($this->helper->url->to('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $project['id'])));
    }
}
