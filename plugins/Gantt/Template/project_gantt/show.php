<section id="main">
    <div class="page-header">
        <h2><i class="fa fa-map-o" aria-hidden="true"></i> Organizational Roadmap (All Projects View)</h2>
        <ul>
            <?php if ($this->user->hasAccess('ProjectCreationController', 'create')): ?>
                <li>
                    <?= $this->modal->medium('plus', t('New project'), 'ProjectCreationController', 'create') ?>
                </li>
            <?php endif ?>
            <?php if ($this->app->config('disable_private_project', 0) == 0): ?>
                <li>
                    <?= $this->modal->medium('lock', t('New private project'), 'ProjectCreationController', 'createPrivate') ?>
                </li>
            <?php endif ?>
            <li>
                <?= $this->url->icon('folder', t('Projects list'), 'ProjectListController', 'show') ?>
            </li>
            <?php if ($this->user->hasAccess('ProjectUserOverviewController', 'managers')): ?>
                <li>
                    <?= $this->url->icon('user', t('Users overview'), 'ProjectUserOverviewController', 'managers') ?>
                </li>
            <?php endif ?>
        </ul>
    </div>

    <?php /* Same toolbar and legend as the per-project Gantt, so the two views
             are the same control. This page used to carry its own hand-rolled
             one with hardcoded greys, a blue "Fit to Width" button and a red
             and a blue badge standing in for a legend; none of it matched the
             rest of the application, and the two badges named the markers
             without showing what they look like on the chart. */ ?>
    <div class="sb-gantt-toolbar">
        <div class="sb-scale-group" role="group" aria-label="<?= t('Time scale') ?>">
            <span class="sb-scale-label"><i class="fa fa-sliders"></i> <?= t('Scale:') ?></span>
            <div class="zg-scale-picker-wrapper">
                <select id="zg-scale-select" class="zg-scale-select">
                    <option value="weeks"><?= t('Weeks') ?></option>
                    <option value="months"><?= t('Months') ?></option>
                    <option value="quarters" selected="selected"><?= t('Quarters') ?></option>
                    <option value="years"><?= t('Years') ?></option>
                </select>
                <i class="fa fa-caret-down zg-scale-caret"></i>
            </div>
            <div class="zg-zoom-stepper">
                <button type="button" class="zg-zoom-btn" data-step="-1" title="<?= t('Zoom Out') ?>"><i class="fa fa-minus"></i></button>
                <button type="button" class="zg-zoom-btn" data-step="1" title="<?= t('Zoom In') ?>"><i class="fa fa-plus"></i></button>
            </div>
            <button type="button" class="sb-fit-btn" id="zg-fit-btn">
                <i class="fa fa-arrows-h" aria-hidden="true"></i> <?= t('Fit to width') ?>
            </button>
        </div>

        <div class="sb-gantt-legend">
            <span class="sb-legend-item"><span class="sb-legend-rule is-today"></span><?= t('Today') ?></span>
            <span class="sb-legend-item"><span class="sb-legend-rule is-est"></span><?= t('Estimated completion') ?></span>
        </div>
    </div>

    <section>
        <?php if (empty($projects)): ?>
            <p class="alert"><?= t('No project') ?></p>
        <?php else: ?>
            <div class="sb-gantt-surface">
                <div
                    id="gantt-chart"
                    data-records='<?= json_encode($projects, JSON_HEX_APOS) ?>'
                    data-save-url="<?= $this->url->href('ProjectGanttController', 'save', array('plugin' => 'Gantt')) ?>"
                    data-label-project-manager="<?= t('Project managers') ?>"
                    data-label-project-member="<?= t('Project members') ?>"
                    data-label-gantt-link="<?= t('Gantt chart for this project') ?>"
                    data-label-board-link="<?= t('Project board') ?>"
                    data-label-start-date="<?= t('Start date:') ?>"
                    data-label-end-date="<?= t('End date:') ?>"
                    data-label-not-defined="<?= t('There is no start date or end date for this project.') ?>"
                ></div>
            </div>
        <?php endif ?>
    </section>
</section>
