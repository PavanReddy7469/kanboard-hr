<?php
/* Renders in two places: the project dashboard, where every task belongs to
   the same project, and Home, where they do not. Each item carries its own
   project_id so the task link is right either way; $show_project adds the
   project name to the meta line when the list spans more than one. */
?>
<div class="sb-card sb-dash-attention">
    <div class="sb-card-head">
        <h3><?= t('Needs attention') ?></h3>
        <span class="sb-card-note"><?= t('Worst first') ?></span>
    </div>
    <div class="sb-card-body">
        <?php if (empty($attention)): ?>
            <p class="sb-empty"><?= t('Nothing is late, blocked or unassigned. Good week.') ?></p>
        <?php else: ?>
            <ul class="sb-attention">
                <?php foreach ($attention as $item): ?>
                    <li>
                        <div class="sb-attention-main">
                            <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $item['project_id'], 'task_id' => $item['id'])) ?>"
                               data-zp-open="<?= $this->url->href('TaskPanelController', 'show', array('plugin' => 'TaskManager', 'task_id' => $item['id'], 'project_id' => $item['project_id'])) ?>">
                                <?= $this->text->e($item['title']) ?>
                            </a>
                            <span class="sb-attention-meta">
                                <?php if ($show_project && $item['project'] !== ''): ?><?= $this->text->e($item['project']) ?> &middot; <?php endif ?>
                                <?php if ($item['column'] !== ''): ?><?= $this->text->e($item['column']) ?><?php endif ?>
                                <?php if ($item['assignee'] !== ''): ?> &middot; <?= $this->text->e($item['assignee']) ?><?php endif ?>
                                <?php if ($item['date_due'] > 0): ?> &middot; <?= $this->dt->date($item['date_due']) ?><?php endif ?>
                            </span>
                        </div>
                        <div class="sb-attention-flags">
                            <?php foreach ($item['reasons'] as $reason): ?>
                                <span class="sb-flag is-<?= $reason['tone'] ?>"><?= $this->text->e($reason['label']) ?></span>
                            <?php endforeach ?>
                        </div>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </div>
</div>
