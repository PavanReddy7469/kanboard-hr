<?php
/* Search results, in the SUPERBEE grid's language.
 *
 * This used to render Kanboard's stock task_list partials, which is why the
 * results looked nothing like the rest of the application - and why they
 * printed "Project > Default swimlane > Open", exposing a swimlane that means
 * nothing here.
 *
 * The columns match the task grid deliberately: someone arriving from a search
 * should recognise what they are looking at.
 */
?>
<div class="sb-results">
    <div class="sb-results-head">
        <span class="sb-results-count"><?= t('%d task(s)', $paginator->getTotal()) ?></span>
        <span class="sb-results-sort"><?= $paginator ?></span>
    </div>

    <?php foreach ($paginator->getCollection() as $task): ?>
        <?php
            $code = ! empty($task['reference'])
                ? strtoupper(substr($task['reference'], 0, 4))
                : sprintf('T%03d', $task['id']);

            /* The search query aliases the column as column_name; other task
               queries use column_title. Accept either, and never fatal on a
               result set that carries neither. */
            $columnLabel = '';
            if (! empty($task['column_name'])) {
                $columnLabel = $task['column_name'];
            } elseif (! empty($task['column_title'])) {
                $columnLabel = $task['column_title'];
            }

            $isOverdue = ! empty($task['date_due'])
                && ! empty($task['is_active'])
                && $task['date_due'] < strtotime('today');
        ?>
        <a class="sb-result <?= empty($task['is_active']) ? 'is-closed' : '' ?>"
           href="<?= $this->url->href('TaskViewController', 'show', array('task_id' => $task['id'], 'project_id' => $task['project_id'])) ?>">

            <span class="sb-result-code"><?= $this->text->e($code) ?></span>

            <span class="sb-result-main">
                <span class="sb-result-title"><?= $this->text->e($task['title']) ?></span>
                <span class="sb-result-project"><?= $this->text->e($task['project_name']) ?></span>
            </span>

            <span class="sb-result-owner">
                <?php if (! empty($task['assignee_name']) || ! empty($task['assignee_username'])): ?>
                    <?= $this->text->e($this->user->formatName(
                        isset($task['owner_id']) ? $task['owner_id'] : 0,
                        $task['assignee_name'] ?: $task['assignee_username']
                    )) ?>
                <?php else: ?>
                    <span class="sb-result-muted"><?= t('Unassigned') ?></span>
                <?php endif ?>
            </span>

            <span class="sb-result-status">
                <?php if ($columnLabel !== ''): ?>
                    <span class="zs-pill <?= $this->text->e($this->taskTree->getStatusClass($columnLabel)) ?>">
                        <span class="zs-dot"></span>
                        <span class="zs-label"><?= $this->text->e($columnLabel) ?></span>
                    </span>
                <?php endif ?>
            </span>

            <span class="sb-result-dates">
                <?php if (! empty($task['date_due'])): ?>
                    <span class="<?= $isOverdue ? 'is-late' : '' ?>">
                        <i class="fa fa-calendar" aria-hidden="true"></i>
                        <?= $this->dt->date($task['date_due']) ?>
                    </span>
                <?php else: ?>
                    <span class="sb-result-muted">&mdash;</span>
                <?php endif ?>
            </span>

            <span class="sb-result-priority">
                <?php if (! empty($task['priority'])): ?>
                    <span class="zg-priority <?= $this->taskTree->getPriorityClass($task['priority']) ?>"><?= $this->taskTree->getPriorityLabel($task['priority']) ?></span>
                <?php endif ?>
            </span>
        </a>
    <?php endforeach ?>
</div>
