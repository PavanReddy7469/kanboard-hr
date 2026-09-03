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

        <div class="input-addon-item" style="display: flex; align-items: center; background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <?= $this->render('app/filters_helper', array(
                'reset' => 'status:open',
                'project' => $project,
                'users_list' => isset($users_list) ? $users_list : array(),
                'categories_list' => isset($categories_list) ? $categories_list : array(),
            )) ?>
        </div>
    </form>
</div>
