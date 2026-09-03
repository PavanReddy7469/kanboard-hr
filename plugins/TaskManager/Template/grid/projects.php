<div class="zg">

    <div class="zg-title">
        <h2><?= t('Projects') ?></h2>
    </div>

    <?php /* These lived on the dashboard, where "New project" had nothing to do
             with the page. "New Project" itself is the primary button on the
             right, so it is not repeated here. */ ?>
    <div class="zg-actions">
        <?php if ($this->app->config('disable_private_project', 0) == 0): ?>
            <span class="zg-action"><?= $this->modal->medium('lock', t('New personal project'), 'ProjectCreationController', 'createPrivate') ?></span>
        <?php endif ?>
        <span class="zg-action"><?= $this->modal->medium('dashboard', t('My activity stream'), 'ActivityController', 'user') ?></span>
    </div>

    <div class="zg-toolbar">
        <form method="get" action="<?= $this->url->dir() ?>" class="zg-viewpick">
            <input type="hidden" name="controller" value="ProjectGridController">
            <input type="hidden" name="action" value="show">
            <input type="hidden" name="plugin" value="TaskManager">
            <select name="view" data-zg-submit aria-label="<?= t('View') ?>">
                <?php foreach ($views as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $key === $view ? 'selected' : '' ?>><?= $this->text->e($label) ?></option>
                <?php endforeach ?>
            </select>
        </form>

        <div class="zg-toolbar-right">
            <form method="get" action="<?= $this->url->dir() ?>" class="zg-search">
                <input type="hidden" name="controller" value="ProjectGridController">
                <input type="hidden" name="action" value="show">
                <input type="hidden" name="plugin" value="TaskManager">
                <input type="hidden" name="view" value="<?= $this->text->e($view) ?>">
                <input type="search" name="search" value="<?= $this->text->e($search) ?>" placeholder="<?= t('Search') ?>" aria-label="<?= t('Search') ?>">
            </form>

            <?php if ($can_create): ?>
                <span class="zg-btn-primary"><?= $this->modal->medium('plus', t('New Project'), 'ProjectCreationController', 'create') ?></span>
            <?php endif ?>

            <div class="dropdown zg-overflow">
                <a href="#" class="dropdown-menu dropdown-menu-link-icon" aria-label="<?= t('More') ?>"><strong><i class="fa fa-ellipsis-h" aria-hidden="true"></i></strong></a>
                <ul>
                    <li><?= $this->url->link(t('Board view'), 'ProjectListController', 'show') ?></li>
                    <?php if ($this->user->hasAccess('ProjectUserOverviewController', 'managers')): ?>
                        <li><?= $this->url->link(t('Users overview'), 'ProjectUserOverviewController', 'managers') ?></li>
                    <?php endif ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="zg-scroll">
        <table class="zg-table">
            <thead>
                <tr>
                    <th class="zg-col-code"><?= t('ID') ?></th>
                    <th class="zg-col-name"><?= t('Project Name') ?></th>
                    <th class="zg-col-pct">%</th><!-- literal: t() runs sprintf, and a lone % is not a valid format spec -->
                    <th class="zg-col-owner"><?= t('Owner') ?></th>
                    <th class="zg-col-status"><?= t('Status') ?></th>
                    <th class="zg-col-tasks"><?= t('Tasks') ?></th>
                    <th class="zg-col-date"><?= t('Start Date') ?></th>
                    <th class="zg-col-date"><?= t('End Date') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="zg-empty"><?= t('There is no project.') ?></td></tr>
                <?php endif ?>

                <?php foreach ($rows as $row): ?>
                    <tr class="<?= $row['status_key'] === 'closed' ? 'is-archived' : '' ?>">
                        <td class="zg-col-code"><span class="zg-code"><?= $this->text->e($row['code']) ?></span></td>
                        <td class="zg-col-name">
                            <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $row['id'])) ?>">
                                <?= $this->text->e($row['name']) ?>
                            </a>
                        </td>
                        <td class="zg-col-pct"><?= $row['progress'] ?>%</td>
                        <td class="zg-col-owner">
                            <?php if ($row['owner'] !== ''): ?>
                                <span class="zg-owner"><?= $this->text->e($row['owner']) ?></span>
                            <?php else: ?>
                                <span class="zg-muted">&mdash;</span>
                            <?php endif ?>
                        </td>
                        <td class="zg-col-status">
                            <?= $this->render('TaskManager:grid/status_select', array(
                                'options'        => array('active' => t('Active'), 'on_hold' => t('On Hold'), 'closed' => t('Closed')),
                                'option_classes' => array('active' => 'status-wip', 'on_hold' => 'status-rev', 'closed' => 'status-closed'),
                                'current'        => $row['status_key'],
                                'current_label'  => $row['status_label'],
                                'current_class'  => $row['status_class'],
                                'editable'       => $can_set_status,
                                'url_template'   => $this->url->href('StatusChangeController', 'project', array('plugin' => 'TaskManager', 'project_id' => $row['id'])).'&state=%s&csrf_token=%s',
                            )) ?>
                        </td>
                        <td class="zg-col-tasks">
                            <span class="zg-tasks">
                                <em><?= $row['closed'] ?></em>
                                <span class="zg-meter"><span class="zg-meter-fill" style="width: <?= $row['progress'] ?>%"></span></span>
                                <em><?= $row['total'] ?></em>
                            </span>
                        </td>
                        <td class="zg-col-date"><?= $row['start_date'] !== '' ? $this->text->e($row['start_date']) : '<span class="zg-muted">&mdash;</span>' ?></td>
                        <td class="zg-col-date">
                            <?php if ($row['end_date'] !== ''): ?>
                                <?= $this->text->e($row['end_date']) ?>
                                <?php if ($row['days_left'] !== null): ?>
                                    <span class="zg-daysleft <?= $row['days_left'] < 0 ? 'is-late' : '' ?>">
                                        <?= $row['days_left'] < 0 ? t('(%d days over)', abs($row['days_left'])) : t('(%d days to go)', $row['days_left']) ?>
                                    </span>
                                <?php endif ?>
                            <?php else: ?>
                                <span class="zg-muted">&mdash;</span>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?= $this->render('TaskManager:grid/pager', array(
        'page'       => $page,
        'controller' => 'ProjectGridController',
        'base'       => array(
            'plugin' => 'TaskManager',
            'view'   => $view,
            'search' => $search,
        ),
    )) ?>

</div>
