<?php

namespace Kanboard\Plugin\TaskManager\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Plugin\TaskManager\Model\GridModel;

/**
 * All Projects, as a data grid.
 *
 * @package Kanboard\Plugin\TaskManager\Controller
 */
class ProjectGridController extends BaseController
{
    public function show()
    {
        $view   = $this->request->getStringParam('view', GridModel::FILTER_ALL);
        $search = $this->request->getStringParam('search');
        $views  = $this->gridModel->getProjectViews();

        if (! array_key_exists($view, $views)) {
            $view = GridModel::FILTER_ALL;
        }

        $rows = $this->gridModel->getProjectRows($this->userSession->getId(), $view, $search);
        $page = $this->gridModel->paginate($rows, $this->request->getIntegerParam('page', 1));

        $this->response->html($this->helper->layout->app('TaskManager:grid/projects', array(
            'title'      => t('Projects').' ('.$page['total'].')',
            'rows'       => $page['rows'],
            'page'       => $page,
            'views'      => $views,
            'view'       => $view,
            'search'     => $search,
            'can_create' => $this->helper->user->hasAccess('ProjectCreationController', 'create'),
            'can_set_status' => $this->userSession->isAdmin(),
        )));
    }
}
