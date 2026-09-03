<?php

namespace Kanboard\Filter;

use Kanboard\Core\Filter\FilterInterface;
use Kanboard\Model\FuzzySearchModel;
use Kanboard\Model\TaskModel;

/**
 * Filter tasks by title with enhanced fuzzy and typo-tolerant search
 *
 * @package filter
 * @author  Frederic Guillot
 */
class TaskTitleFilter extends BaseFilter implements FilterInterface
{
    /**
     * @var FuzzySearchModel
     */
    protected $fuzzySearchModel;

    /**
     * Set FuzzySearchModel
     *
     * @param  FuzzySearchModel $fuzzySearchModel
     * @return $this
     */
    public function setFuzzySearchModel(FuzzySearchModel $fuzzySearchModel)
    {
        $this->fuzzySearchModel = $fuzzySearchModel;
        return $this;
    }

    /**
     * Get search attribute
     *
     * @access public
     * @return string[]
     */
    public function getAttributes()
    {
        return array('title');
    }

    /**
     * Apply filter
     *
     * @access public
     * @return FilterInterface
     */
    public function apply()
    {
        $val = trim((string) $this->value);

        if (ctype_digit($val) || (strlen($val) > 1 && $val[0] === '#' && ctype_digit(substr($val, 1)))) {
            $this->query->beginOr();
            $this->query->eq(TaskModel::TABLE.'.id', str_replace('#', '', $val));
            $this->query->ilike(TaskModel::TABLE.'.title', '%'.$val.'%');
            $this->query->closeOr();
        } else {
            $fuzzyIds = array();

            if (isset($this->fuzzySearchModel)) {
                $fuzzyIds = $this->fuzzySearchModel->findTaskIdsByFuzzyTitle($val);
            }

            $this->query->beginOr();
            $this->query->ilike(TaskModel::TABLE.'.title', '%'.$val.'%');
            $this->query->ilike(TaskModel::TABLE.'.description', '%'.$val.'%');

            if (! empty($fuzzyIds)) {
                $this->query->in(TaskModel::TABLE.'.id', $fuzzyIds);
            }

            $this->query->closeOr();
        }

        return $this;
    }
}
