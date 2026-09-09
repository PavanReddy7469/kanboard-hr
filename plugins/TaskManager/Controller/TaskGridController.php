<?php

namespace Kanboard\Plugin\TaskManager\Controller;

use Kanboard\Controller\BaseController;
use Kanboard\Filter\TaskProjectFilter;
use Kanboard\Plugin\TaskManager\Model\GridModel;

/**
 * A project's tasks, in the three modes the view switcher offers.
 *
 * List is our own grid. Gantt and Kanban deliberately reuse what already
 * ships: the Gantt plugin's chart and formatter, and Kanboard's own board
 * template. Nothing is reimplemented, so drag-and-drop, the task limits and
 * the swimlanes all keep working exactly as they do elsewhere.
 *
 * @package Kanboard\Plugin\TaskManager\Controller
 */
class TaskGridController extends BaseController
{
    public function show()
    {
        $project   = $this->getProject();
        $modes     = $this->gridModel->getViewModes();
        $groupings = $this->gridModel->getGroupings();

        $mode = $this->request->getStringParam('mode', GridModel::MODE_LIST);

        if (! array_key_exists($mode, $modes)) {
            $mode = GridModel::MODE_LIST;
        }

        $views     = $this->gridModel->getViewsForMode($mode);
        $view      = $this->request->getStringParam('view', GridModel::FILTER_OPEN);
        $grouping  = $this->request->getStringParam('group_by', 'none');
        $search    = $this->request->getStringParam('search');

        /* The header, the view switcher and the Filter dot all read
           $filters['search'], which ProjectHeaderHelper resolves from the
           session when the URL carries no search - and defaults to
           'status:open' when the session is empty too. This grid resolves its
           own list from the URL alone, so without this line the header would
           advertise a filter the list had not applied: the dot lit on an
           unfiltered list, and the Gantt and Calendar links carrying a stale
           query that hid work the moment you switched view. Writing the
           effective search back keeps the two in agreement, in both
           directions - and the filter still travels across views, which is
           the part of the session behaviour worth keeping. */
        $this->userSession->setFilters($project['id'], $search);
        $order     = $this->request->getStringParam('order', 'id');
        $direction = strtoupper($this->request->getStringParam('direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $scale     = $this->request->getStringParam('scale', 'week');

        if (! array_key_exists($view, $views)) {
            $view = GridModel::FILTER_OPEN;
        }

        if (! array_key_exists($grouping, $groupings)) {
            $grouping = 'none';
        }

        if (! array_key_exists($scale, $this->gridModel->getGanttScales())) {
            $scale = 'week';
        }

        $params = array(
            'project'    => $project,
            'title'      => $project['name'].' - '.t('Tasks'),
            'code'       => $this->gridModel->getProjectCode($project),
            'modes'      => $modes,
            'mode'       => $mode,
            'views'      => $views,
            'view'       => $view,
            'groupings'  => $groupings,
            'group_by'   => $grouping,
            'search'     => $search,
            'order'      => $order,
            'direction'  => $direction,
            'scales'     => $this->gridModel->getGanttScales(),
            'scale'      => $scale,
            'can_create' => $this->helper->user->hasProjectAccess('TaskCreationController', 'show', $project['id']),
            'can_move'   => $this->helper->user->hasProjectAccess('BoardAjaxController', 'save', $project['id']),
        );

        list($params['columns'], $params['column_classes']) = $this->gridModel->getStatusOptions($project['id']);

        /* Subtasks use Kanboard's three states rather than the project's
           columns, so the pill in a subtask row is fed from this list. */
        $params['subtask_statuses'] = $this->subtaskModel->getStatusList();
        $params['subtask_status_classes'] = array();

        foreach (array_keys($params['subtask_statuses']) as $subtaskStatus) {
            $params['subtask_status_classes'][$subtaskStatus] = $this->helper->taskTree->getSubtaskStatusClass($subtaskStatus);
        }

        /* The filter panel composes a Kanboard search string, so it can only
           offer fields the search language actually understands. Duration and
           completion percentage are computed for display and have no filter
           keyword, which is why they are absent from the panel. */
        $params['filter_users'] = $this->projectUserRoleModel->getAssignableUsersList($project['id'], false);

        $params['filter_priorities'] = array();
        foreach (range((int) $project['priority_start'], (int) $project['priority_end']) as $p) {
            $params['filter_priorities'][$p] = 'P'.$p;
        }

        $params['filter_tags'] = $this->tagModel->getAllByProject($project['id']);

        if ($mode === GridModel::MODE_GANTT) {
            $params += $this->getGanttParams($project, $view);
        } elseif ($mode === GridModel::MODE_KANBAN) {
            $params += $this->getKanbanParams($project, $view);
        } else {
            $rows = $this->gridModel->getTaskRows($project['id'], $view, $search, $order, $direction);
            $page = $this->gridModel->paginate($rows, $this->request->getIntegerParam('page', 1));
            $params += array(
                'rows'   => $page['rows'],
                'page'   => $page,
                'groups' => $this->gridModel->groupRows($page['rows'], $grouping),
            );
        }

        $this->response->html($this->helper->layout->project('TaskManager:grid/tasks', $params, 'TaskManager:project/no_sidebar'));
    }

    /**
     * Records in the shape the Gantt plugin's own chart.js expects.
     *
     * @param  array  $project
     * @param  string $view
     * @return array
     */
    protected function getGanttParams(array $project, $view)
    {
        $search = $this->gridModel->getSearchQueryForView($view);

        $filter = $this->taskLexer
            ->build($search)
            ->withFilter(new TaskProjectFilter($project['id']));

        $filter->getQuery()->asc('column_position')->asc(\Kanboard\Model\TaskModel::TABLE.'.position');

        return array(
            'tasks' => $filter->format($this->taskGanttFormatter),
            'rows'  => array(),
        );
    }

    /**
     * Swimlanes in the shape Kanboard's own board templates expect, so the
     * board renders with its real drag-and-drop behaviour.
     *
     * @param  array  $project
     * @param  string $view
     * @return array
     */
    protected function getKanbanParams(array $project, $view)
    {
        $search = $this->gridModel->getSearchQueryForView($view);

        return array(
            'swimlanes' => $this->taskLexer
                ->build($search)
                ->format($this->boardFormatter->withProjectId($project['id'])),
            'board_private_refresh_interval' => $this->configModel->get('board_private_refresh_interval'),
            'board_highlight_period'         => $this->configModel->get('board_highlight_period'),
            'rows'                           => array(),
        );
    }
}
