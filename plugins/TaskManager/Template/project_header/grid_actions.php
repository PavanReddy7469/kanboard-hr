<?php
/**
 * The display-type picker and the Filter button, at the right-hand end of
 * the project tab strip.
 *
 * They used to sit in a toolbar of their own under the tabs, which spent a
 * whole row on two controls and left the tab strip half empty. They are
 * rendered only on the task grid: the tab strip is shared with Gantt,
 * Calendar and Reports, and on those pages the picker points at
 * a table that is not there and the Filter button has no panel to open.
 *
 * @var array $project
 * @var array $filters
 */

$mode    = $this->gridHeader->getMode();
$modes   = $this->gridHeader->getModes();
$view    = $this->gridHeader->getView();
$groupBy = $this->gridHeader->getGroupBy();
$search  = isset($filters['search']) ? $filters['search'] : '';

/* Group By lives inside the display dropdown rather than beside it. The bar
   was deliberately kept to two controls; a third would undo that. */
$groupings = $this->gridHeader->getGroupings();
?>
<li class="zg-tabs-actions">
    <div class="dropdown zg-modepick">
        <a href="#" class="dropdown-menu dropdown-menu-link-icon" aria-label="<?= t('Change view') ?>">
            <strong>
                <i class="fa fa-<?= $this->gridHeader->getModeIcon($mode) ?>" aria-hidden="true"></i>
                <span><?= $this->text->e($modes[$mode]) ?></span>
                <i class="fa fa-caret-down zg-modepick-caret" aria-hidden="true"></i>
            </strong>
        </a>
        <ul>
            <?php foreach ($modes as $key => $label): ?>
                <li>
                    <?= $this->url->link(
                            '<i class="fa fa-'.$this->gridHeader->getModeIcon($key).'"></i> '.$label,
                            'TaskGridController',
                            'show',
                            array(
                                'plugin'     => 'TaskManager',
                                'project_id' => $project['id'],
                                'mode'       => $key,
                                'view'       => $view,
                                'group_by'   => $groupBy,
                            ),
                            false,
                            $key === $mode ? 'is-current' : ''
                        ) ?>
                </li>
            <?php endforeach ?>

            <?php /* Only the list has rows to group; the board groups by
                     column and the Gantt by date, both by definition. */ ?>
            <?php if ($mode === 'list'): ?>
                <li class="zg-modepick-sep"><span><?= t('Group by') ?></span></li>
                <?php foreach ($groupings as $key => $label): ?>
                    <li>
                        <?= $this->url->link(
                                '<i class="fa fa-fw fa-'.($key === $groupBy ? 'check' : 'none').'"></i> '.$label,
                                'TaskGridController',
                                'show',
                                array(
                                    'plugin'     => 'TaskManager',
                                    'project_id' => $project['id'],
                                    'mode'       => $mode,
                                    'view'       => $view,
                                    'group_by'   => $key,
                                ),
                                false,
                                $key === $groupBy ? 'is-current' : ''
                            ) ?>
                    </li>
                <?php endforeach ?>
            <?php endif ?>
        </ul>
    </div>

    <?php /* Add Task. The table has a "+ Add Task" row at the top of the
             list, but that row only exists in list mode and only once you
             have scrolled back up to it. Up here it is reachable from the
             board and the Gantt too, and from anywhere in a long list. */ ?>
    <?php if ($this->user->hasProjectAccess('TaskCreationController', 'show', $project['id'])): ?>
        <span class="zg-toolbtn">
            <?= $this->modal->large('plus', t('Add Task'), 'TaskCreationController', 'show', array('project_id' => $project['id'])) ?>
        </span>
    <?php endif ?>

    <?php /* Creating a list used to be a button on the Task Lists page. That
             page is gone, so the action moved here, next to the grouping it
             affects. Shown only while the list is actually grouped by list -
             elsewhere it would create something the screen does not show. */ ?>
    <?php if ($mode === 'list' && $groupBy === 'task_list' && $this->user->hasProjectAccess('TaskGroupController', 'create', $project['id'])): ?>
        <span class="zg-toolbtn">
            <?= $this->modal->medium('plus', t('New Task List'), 'TaskGroupController', 'create', array('plugin' => 'TaskManager', 'project_id' => $project['id'])) ?>
        </span>
    <?php endif ?>

    <a href="#" class="zf-open zg-filter-trigger" data-zf-open title="<?= t('Filter') ?>">
        <i class="fa fa-filter" aria-hidden="true"></i>
        <span><?= t('Filter') ?></span>
        <?php if (! empty($search)): ?>
            <span class="zf-open-dot" title="<?= t('A filter is applied') ?>"></span>
        <?php endif ?>
    </a>
</li>
