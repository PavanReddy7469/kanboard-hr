<?php
/* Replaces task_list/task_details + task_icons + task_subtasks on the
   dashboard rows.

   Kanboard's version packed sixteen unlabelled icons onto one line -
   reference, milestone, complexity, time, both dates, recurrence, links,
   external links, subtask %, files, comments, description, position, task age,
   days in column, priority - and then nested a subtask table with a timer
   button underneath. On a glance-list none of it is readable without hovering
   each glyph, and it duplicated what the Tasks grid shows under real column
   headers.

   What survives is what you can read at a glance: where the task is, when it
   is due, and how urgent it is. The task drawer has the rest, labelled. */
$sb_overdue = ! empty($task['date_due']) && $task['date_due'] < time() && $task['is_active'] == 1;
?>
<div class="sb-rowmeta">
    <span class="sb-rowmeta-status"><?= $this->text->e($task['column_name']) ?></span>

    <?php if (! empty($task['date_due'])): ?>
        <span class="sb-rowmeta-due <?= $sb_overdue ? 'is-overdue' : '' ?>">
            <?= $sb_overdue ? t('Overdue') : t('Due') ?> <?= $this->dt->date($task['date_due']) ?>
        </span>
    <?php endif ?>

    <?php if (! empty($task['nb_subtasks'])): ?>
        <?php $sb_total = (int) $task['nb_subtasks']; $sb_done = (int) $task['nb_completed_subtasks']; ?>
        <span class="sb-rowmeta-sub">
            <?= $sb_total === 1
                ? t('%d of 1 subtask done', $sb_done)
                : t('%d of %d subtasks done', $sb_done, $sb_total) ?>
        </span>
    <?php endif ?>

    <?php if (! empty($task['nb_comments'])): ?>
        <?php $sb_comments = (int) $task['nb_comments']; ?>
        <span class="sb-rowmeta-sub">
            <?= $sb_comments === 1 ? t('1 comment') : t('%d comments', $sb_comments) ?>
        </span>
    <?php endif ?>

    <?php if ($task['priority'] > 0): ?>
        <span class="sb-rowmeta-priority"><?= $this->text->e($this->taskTree->getPriorityLabel($task['priority'])) ?></span>
    <?php endif ?>
</div>
