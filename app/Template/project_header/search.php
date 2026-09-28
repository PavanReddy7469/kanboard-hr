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

        <?php /* The visible Filter button now lives in the Tasks grid toolbar,
                 next to the display-type picker - the two controls that decide
                 what the table shows belong side by side. It is gone from here
                 because this header is shared with Gantt and Calendar, where
                 there is no filter panel for it to open.

                 The hidden field above stays: it is cheap, and Kanboard's own
                 scripts look for #form-search by id. */ ?>
    </form>
</div>
