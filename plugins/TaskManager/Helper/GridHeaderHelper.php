<?php

namespace Kanboard\Plugin\TaskManager\Helper;

use Kanboard\Core\Base;
use Kanboard\Plugin\TaskManager\Model\GridModel;

/**
 * Feeds the display-type picker and the Filter button now that they live in
 * the project tab strip.
 *
 * The tab strip is core's project header, shared by every project page, and
 * it gets none of the task grid's view variables. Rather than pass them down
 * through a template that Gantt, Calendar, Task Lists and Reports also
 * render, this reads the same request parameters the grid controller reads,
 * and answers whether we are even on the page where these two controls mean
 * anything.
 *
 * @package Kanboard\Plugin\TaskManager\Helper
 */
class GridHeaderHelper extends Base
{
    /**
     * Whether the page being rendered is the task grid.
     *
     * The router, not the request: with URL rewriting on, the controller
     * name is not in the query string.
     *
     * @return boolean
     */
    public function isTaskGrid()
    {
        return strtolower($this->router->getController()) === 'taskgridcontroller'
            && strtolower($this->router->getAction()) === 'show';
    }

    /**
     * @return array  mode => label
     */
    public function getModes()
    {
        return $this->gridModel->getViewModes();
    }

    /**
     * The mode currently on screen, validated the same way the controller
     * validates it so the picker can never disagree with the table.
     *
     * @return string
     */
    public function getMode()
    {
        $modes = $this->getModes();
        $mode  = $this->request->getStringParam('mode', GridModel::MODE_LIST);

        return array_key_exists($mode, $modes) ? $mode : GridModel::MODE_LIST;
    }

    /**
     * @return string
     */
    public function getView()
    {
        return $this->request->getStringParam('view', GridModel::FILTER_OPEN);
    }

    /**
     * @return array  key => label
     */
    public function getGroupings()
    {
        return $this->gridModel->getGroupings();
    }

    /**
     * The grouping currently on screen, validated exactly as the controller
     * validates it - including the default - so the picker can never claim a
     * grouping the table is not using.
     *
     * @return string
     */
    public function getGroupBy()
    {
        $groupings = $this->getGroupings();
        $groupBy   = $this->request->getStringParam('group_by', 'task_list');

        return array_key_exists($groupBy, $groupings) ? $groupBy : 'task_list';
    }

    /**
     * The icon that stands for a mode, used on the button and in its menu.
     *
     * @param  string $mode
     * @return string
     */
    public function getModeIcon($mode)
    {
        switch ($mode) {
            case GridModel::MODE_GANTT:
                return 'tasks';
            case GridModel::MODE_KANBAN:
                return 'columns';
            default:
                return 'list';
        }
    }
}
