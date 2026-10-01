<div class="page-header">
    <h2><i class="fa fa-upload text-indigo"></i> <?= t('Submit Task Completion Report / Link') ?></h2>
</div>

<p class="alert alert-info" style="border-radius: 8px; font-size: 0.88rem; line-height: 1.5;">
    <i class="fa fa-info-circle"></i> <?= t('Attach the document itself, or paste a link to where it lives - a Drive file, a Figma design, a repository, a live demo. An administrator verifies it before the task is marked completed.') ?>
</p>

<?php /* multipart, or the browser posts the filename as ordinary text and
         the file never leaves the machine. */ ?>
<form method="post" enctype="multipart/form-data" action="<?= $this->url->href('DeliverableController', 'save', array('plugin' => 'TaskManager', 'project_id' => $project['id'])) ?>" autocomplete="off">
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

    <?php /* The file first: it is the one people were being told to do
             somewhere else and link back to. Neither field is required on
             its own - the controller insists on one of the two, which is a
             rule the browser cannot express. */ ?>
    <div style="margin-bottom: 14px;">
        <label for="form-evidence" style="font-weight: 600; font-size: 13px; margin-bottom: 4px; display: block;">
            <i class="fa fa-paperclip"></i> <?= t('Attach the report') ?>:
        </label>
        <input type="file" name="evidence" id="form-evidence"
               style="width: 100%; border-radius: 8px; padding: 8px 12px; border: 1px solid #cbd5e1; background: #f8fafc; font-size: 0.86rem;">
        <p style="font-size: 0.78rem; color: #64748b; margin: 6px 0 0;">
            <?= t('PDF, Word, Excel, text, images, drawings, archives - any file type.') ?>
            <?php if ($max_size > 0): ?>
                <strong><?= t('Up to %s.', $this->text->bytes($max_size)) ?></strong>
            <?php endif ?>
        </p>
    </div>

    <div style="display: flex; align-items: center; gap: 10px; margin: 0 0 14px; color: #94a3b8; font-size: 0.78rem; font-weight: 700; text-transform: uppercase;">
        <span style="flex: 1; height: 1px; background: #e2e8f0;"></span>
        <?= t('or') ?>
        <span style="flex: 1; height: 1px; background: #e2e8f0;"></span>
    </div>

    <div style="margin-bottom: 14px;">
        <label for="form-url" style="font-weight: 600; font-size: 13px; margin-bottom: 4px; display: block;">
            <i class="fa fa-link"></i> <?= t('Link to where it lives') ?>:
        </label>
        <?= $this->form->text('url', $values, $errors, array('placeholder="https://drive.google.com/file/d/... or https://..."', 'style="width: 100%; border-radius: 8px; padding: 8px 12px; border: 1px solid #cbd5e1;"'), 'form-input') ?>
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
