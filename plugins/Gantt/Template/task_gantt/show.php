<section id="main">
    <?= $this->projectHeader->render($project, 'TaskGanttController', 'show', false, 'Gantt') ?>
    
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
            <a class="sb-roadmap-link" href="<?= $this->url->href('ProjectGanttController', 'show', array('plugin' => 'Gantt')) ?>" title="<?= t('View organisational roadmap') ?>">
                <i class="fa fa-map-o" aria-hidden="true"></i> <?= t('All projects') ?>
            </a>
        </div>
    </div>

    <?php if (! empty($tasks)): ?>
        <div class="sb-gantt-surface">
            <div
                id="gantt-chart"
                data-records='<?= json_encode($tasks, JSON_HEX_APOS) ?>'
                data-save-url="<?= $this->url->href('TaskGanttController', 'save', array('project_id' => $project['id'], 'plugin' => 'Gantt')) ?>"
                data-label-start-date="<?= t('Start date:') ?>"
                data-label-end-date="<?= t('Due date:') ?>"
                data-label-assignee="<?= t('Assignee:') ?>"
                data-label-not-defined="<?= t('There is no start date or due date for this task.') ?>"
            ></div>
        </div>
        <p class="alert alert-info sb-gantt-note"><i class="fa fa-info-circle"></i> <?= t('Moving or resizing a task will change the start and due date of the task.') ?></p>
    <?php else: ?>
        <p class="alert"><?= t('There is no task in your project.') ?></p>
    <?php endif ?>
</section>
