<div class="page-header">
    <h2><i class="fa fa-upload text-indigo"></i> <?= t('Submit Task Completion Report / Link') ?></h2>
</div>

<p class="alert alert-info" style="border-radius: 8px; font-size: 0.88rem; line-height: 1.5;">
    <i class="fa fa-info-circle"></i> <?= t('Provide a Google Drive document, Figma design, GitHub repository, or live project demo link as completion evidence. Once submitted, an Administrator will verify and approve it before the task is marked completed.') ?>
</p>

<form method="post" action="<?= $this->url->href('DeliverableController', 'save', array('plugin' => 'TaskManager', 'project_id' => $project['id'])) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>

    <div style="margin-bottom: 14px;">
        <label for="form-task_id" style="font-weight: 600; font-size: 13px; margin-bottom: 4px; display: block;">
            <i class="fa fa-tasks"></i> <?= t('Target Task') ?>:
        </label>
        <select name="task_id" id="form-task_id" class="form-input" required style="width: 100%; border-radius: 8px; padding: 8px 12px; border: 1px solid #cbd5e1;">
            <option value=""><?= t('-- Select completed task --') ?></option>
            <?php foreach ($open_tasks as $task): ?>
                <option value="<?= $task['id'] ?>" <?= (int) $values['task_id'] === (int) $task['id'] ? 'selected' : '' ?>>
                    #<?= $task['id'] ?> &mdash; <?= $this->text->e($task['title']) ?>
                </option>
            <?php endforeach ?>
        </select>
    </div>

    <?php if (empty($open_tasks)): ?>
        <p class="alert alert-success" style="border-radius: 8px; font-size: 0.85rem;"><i class="fa fa-check"></i> <?= t('All open tasks in this project already have verified deliverables.') ?></p>
    <?php endif ?>

    <div style="margin-bottom: 14px;">
        <label for="form-url" style="font-weight: 600; font-size: 13px; margin-bottom: 4px; display: block;">
            <i class="fa fa-link"></i> <?= t('Google Drive / Live Document Link') ?>:
        </label>
        <?= $this->form->text('url', $values, $errors, array('placeholder="https://drive.google.com/file/d/... or https://..."', 'required', 'style="width: 100%; border-radius: 8px; padding: 8px 12px; border: 1px solid #cbd5e1;"'), 'form-input') ?>
    </div>

    <div style="margin-bottom: 14px;">
        <label for="form-title" style="font-weight: 600; font-size: 13px; margin-bottom: 4px; display: block;">
            <i class="fa fa-tag"></i> <?= t('Document / Report Title') ?>:
        </label>
        <?= $this->form->text('title', $values, $errors, array('placeholder="e.g. Final Deployment Test Report / Design Specs"', 'style="width: 100%; border-radius: 8px; padding: 8px 12px; border: 1px solid #cbd5e1;"'), 'form-input') ?>
    </div>

    <div style="margin-bottom: 14px;">
        <label for="form-note" style="font-weight: 600; font-size: 13px; margin-bottom: 4px; display: block;">
            <i class="fa fa-comment-o"></i> <?= t('Summary Notes for Admin Reviewer') ?>:
        </label>
        <?= $this->form->textarea('note', $values, $errors, array('rows="3"', 'placeholder="Briefly describe what was implemented, tested, or delivered..."', 'style="width: 100%; border-radius: 8px; padding: 8px 12px; border: 1px solid #cbd5e1; font-family: inherit;"'), 'form-input') ?>
    </div>

    <div class="form-actions" style="margin-top: 18px; display: flex; gap: 10px; align-items: center;">
        <button type="submit" class="btn btn-blue" style="border-radius: 8px; padding: 8px 18px; font-weight: 600;">
            <i class="fa fa-paper-plane"></i> <?= t('Submit Report for Verification') ?>
        </button>
        <?= t('or') ?> <a href="#" class="close-popover" style="color: #64748b;"><?= t('cancel') ?></a>
    </div>
</form>
