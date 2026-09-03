<?php

namespace Kanboard\Plugin\TaskManager\Helper;

use Kanboard\Core\Base;
use Kanboard\Plugin\TaskManager\Model\DeliverableModel;

/**
 * Read-only access to deliverables from templates, so the task-view hook can
 * render without a controller of its own.
 *
 * @package Kanboard\Plugin\TaskManager\Helper
 */
class DeliverableHelper extends Base
{
    /**
     * @param  integer $taskId
     * @return array
     */
    public function getAllByTask($taskId)
    {
        return $this->deliverableModel->getAllByTask($taskId);
    }

    /**
     * @param  integer $taskId
     * @return boolean
     */
    public function hasApproved($taskId)
    {
        return $this->deliverableModel->hasApproved($taskId);
    }

    /**
     * @return array
     */
    public function getStatusList()
    {
        return DeliverableModel::getStatusList();
    }
}
