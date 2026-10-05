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
        $grouping  = $this->request->getStringParam('group_by', 'task_list');
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
            $grouping = 'task_list';
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

            /* Whether the Owner cell is a picker or just a name. Reassigning
               is management's, and so is setting a priority; the status of a
               task is its assignee's and is therefore decided per row, below.
               All three are checked again on the save - this only decides
               what the grid offers. */
            'can_assign' => $this->helper->authority->isManager($project['id']),
        );

        /* The Owner picker's list. "Unassigned" is first because taking a
           task off somebody is as ordinary as giving it to them, and there
           was no way to do it from this screen at all before. */
        $params['assignable_users'] = array(0 => t('Unassigned'))
            + $this->projectUserRoleModel->getAssignableUsersList($project['id'], false);

        list($params['columns'], $params['column_classes']) = $this->gridModel->getStatusOptions($project['id']);

        /* The task lists, each with its milestone resolved, so a group header
           can carry the chip the Task Lists page used to show. Every list is
           passed whether or not it has tasks: an empty list still needs a
           header, otherwise creating one appears to do nothing. */
        /* false: without it the list is prepended with 0 => "No milestone",
           which then reads as a milestone named "No milestone" on every
           header that has none. Leaving 0 out of the map makes the isset()
           below fall through to '' and no chip is drawn. */
        $milestones = $this->milestoneModel->getList($project['id'], false);
        $params['task_lists'] = array();

        foreach ($this->taskListModel->getAll($project['id']) as $taskList) {
            $taskList['milestone_title'] = isset($milestones[$taskList['milestone_id']])
                ? $milestones[$taskList['milestone_id']]
                : '';
            $params['task_lists'][] = $taskList;
        }

        /* Creating, editing and removing a list happens here now that the
           Task Lists tab is gone. */
        $params['can_manage_lists'] = $this->helper->user->hasProjectAccess('TaskGroupController', 'create', $project['id']);

        /* The Priority picker's list. A priority is a queue position now, so
           the choices are the positions that exist - 1..N over the tasks
           holding one - plus one more for a task joining the queue, and
           None for leaving it. Not the project's priority_end: that is a
           ceiling the queue grows past, and offering a position the queue
           does not have would just clamp. */
        list($params['priority_options'], $params['priority_classes']) =
            $this->buildPriorityChoices($project);

        /* A priority is a position in the project's queue, so setting one
           is management's even on your own task. */
        $params['can_prioritise'] = $this->helper->authority->isManager($project['id']);

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
                'groups' => $this->gridModel->groupRows($page['rows'], $grouping, $params['task_lists'], $rows),
            );
        }

        $this->response->html($this->helper->layout->project('TaskManager:grid/tasks', $params, 'TaskManager:project/no_sidebar'));
    }

    /**
     * The positions a priority picker may offer on this project.
     *
     * @param  array $project
     * @return array  array(id => label, id => css class)
     */
    protected function buildPriorityChoices(array $project)
    {
        $start  = max(1, (int) $project['priority_start']);
        $length = $this->priorityModel->getQueueLength($project['id']);

        $options = array(0 => t('None'));
        $classes = array(0 => $this->helper->taskTree->getPriorityClass(0));

        /* $length + 1 positions: every place in the queue, and the place
           after it for a task that is not in the queue yet. */
        foreach (range($start, $start + $length) as $p) {
            $options[$p] = 'P'.$p;
            $classes[$p] = $this->helper->taskTree->getPriorityClass($p);
        }

        return array($options, $classes);
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
