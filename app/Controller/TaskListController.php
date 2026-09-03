<?php

namespace Kanboard\Controller;

/**
 * Task List Controller
 *
 * Redirects directly to the modern SUPERBEE Task Grid (TaskGridController) to
 * eliminate the legacy open-source task list UI across the application.
 *
 * @package Kanboard\Controller
 */
class TaskListController extends BaseController
{
    /**
     * Show list view for projects - redirected to modern TaskGrid
     */
    public function show()
    {
        $project = $this->getProject();
        $search = $this->request->getStringParam('search');
        $params = array('plugin' => 'TaskManager', 'project_id' => $project['id']);
        if (! empty($search)) {
            $params['search'] = $search;
        }
        $this->response->redirect($this->helper->url->to('TaskGridController', 'show', $params));
    }
}
