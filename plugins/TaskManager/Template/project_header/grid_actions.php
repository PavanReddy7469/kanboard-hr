<?php
/**
 * The display-type picker and the Filter button, at the right-hand end of
 * the project tab strip.
 *
 * They used to sit in a toolbar of their own under the tabs, which spent a
 * whole row on two controls and left the tab strip half empty. They are
 * rendered only on the task grid: the tab strip is shared with Gantt,
 * Calendar, Task Lists and Reports, and on those pages the picker points at
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
        </ul>
    </div>

    <a href="#" class="zf-open zg-filter-trigger" data-zf-open title="<?= t('Filter') ?>">
        <i class="fa fa-filter" aria-hidden="true"></i>
        <span><?= t('Filter') ?></span>
        <?php if (! empty($search)): ?>
            <span class="zf-open-dot" title="<?= t('A filter is applied') ?>"></span>
        <?php endif ?>
    </a>
</li>
