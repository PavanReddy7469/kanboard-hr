<div class="page-header">
    <h2><?= t('Request Changes on Completion Report') ?></h2>
</div>

<form method="post" action="<?= $this->url->href('DeliverableController', 'reject', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'deliverable_id' => $deliverable['id'])) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>

    <p style="font-size: 13px; color: #475569; margin-bottom: 12px;">
        <strong><?= t('Task') ?>:</strong> #<?= $deliverable['task_id'] ?> &mdash; <?= $this->text->e($deliverable['title'] ?: $deliverable['url']) ?>
    </p>

    <label for="form-review-note" style="font-weight: 600; font-size: 13px; margin-bottom: 6px; display: block;">
        <i class="fa fa-commenting-o text-indigo"></i> <?= t('Feedback / Changes Required for Assignee') ?>:
    </label>
    <textarea name="review_note" id="form-review-note" class="form-input" rows="4" placeholder="<?= t('Describe what revisions, tests, or updates are needed...') ?>" required style="width: 100%; border-radius: 8px; border: 1px solid #cbd5e1; padding: 10px; font-family: inherit; font-size: 13px;"></textarea>

    <div class="form-actions" style="margin-top: 15px; display: flex; gap: 10px; align-items: center;">
        <button type="submit" class="btn" style="background: #e11d48; color: white; border: none; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer;">
            <i class="fa fa-undo"></i> <?= t('Submit Review & Move Task to WIP') ?>
        </button>
        <?= t('or') ?> <a href="#" class="close-popover" style="color: #64748b;"><?= t('cancel') ?></a>
    </div>
</form>
