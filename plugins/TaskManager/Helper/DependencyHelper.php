<?php

namespace Kanboard\Plugin\TaskManager\Helper;

use Kanboard\Core\Base;

/**
 * Gives templates read access to task dependencies.
 *
 * Hook templates only receive the hook's own parameters, so the task view
 * panel needs a helper rather than controller-supplied data.
 *
 * @package Kanboard\Plugin\TaskManager\Helper
 */
class DependencyHelper extends Base
{
    /**
     * @param  integer $taskId
     * @return array
     */
    public function getAllByTask($taskId)
    {
        return $this->dependencyModel->getAllByTask($taskId);
    }

    /**
     * @param  integer $taskId
     * @return integer
     */
    /**
     * @param  integer $taskId
     * @return array
     */
    public function getSuccessorDetails($taskId)
    {
        return $this->dependencyModel->getSuccessorDetails($taskId);
    }

    public function countByTask($taskId)
    {
        return count($this->getAllByTask($taskId));
    }

    /**
     * @return array
     */
    public function getTypes()
    {
        return $this->dependencyModel->getTypes();
    }

    /**
     * @param  array $dependency
     * @return string
     */
    public function getShortLabel(array $dependency)
    {
        return $this->dependencyModel->getShortLabel($dependency);
    }
}
