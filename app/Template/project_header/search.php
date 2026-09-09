<?php
/* The project filter control.

   The visible free-text box is gone: the Filter dropdown covers the same
   ground with named options, and a raw query box invited a filter that stuck
   silently across Tasks, Gantt and Calendar with nothing on screen explaining
   why half the work had vanished.

   The input itself stays, hidden. Kanboard's dropdown JS reads and writes
   $("#form-search") and then submits this form, so the field has to exist and
   has to carry that exact id - without it every option in the menu submits an
   empty filter, which is precisely what was happening before. */
?>
<div class="filter-box" style="display: inline-flex; align-items: center;">
    <form method="get" action="<?= $this->url->dir() ?>" class="search" style="margin: 0;">
        <input type="hidden" name="controller" value="TaskGridController" />
        <input type="hidden" name="action" value="show" />
        <input type="hidden" name="plugin" value="TaskManager" />
        <input type="hidden" name="project_id" value="<?= $project['id'] ?>" />

        <input type="hidden" name="search" id="form-search" value="<?= isset($filters['search']) ? $this->text->e($filters['search']) : '' ?>" />

        <?php /* Opens the filter panel rendered by the Tasks grid. The button
                 is inert on views that do not render one, which is deliberate -
                 better than a menu of presets that half-apply. */ ?>
        <a href="#" class="zf-open" data-zf-open title="<?= t('Filter') ?>">
            <i class="fa fa-filter" aria-hidden="true"></i>
            <span><?= t('Filter') ?></span>
            <?php if (! empty($filters['search'])): ?>
                <span class="zf-open-dot" title="<?= t('A filter is applied') ?>"></span>
            <?php endif ?>
        </a>
    </form>
</div>
