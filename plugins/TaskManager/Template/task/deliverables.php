<?php
/* Attached to template:task:show:before-attachments.
   Completion evidence & verification gate for this task. */
$rows     = $this->deliverable->getAllByTask($task['id']);
$approved = $this->deliverable->hasApproved($task['id']);
$statuses = $this->deliverable->getStatusList();
$class    = array('pending' => 'is-pending', 'approved' => 'is-approved', 'rejected' => 'is-rejected');
?>
<div class="sb-deliv-task-block" style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; margin: 18px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.02);">
    <h3 style="margin: 0 0 12px; font-size: 1rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
        <i class="fa fa-file-text-o" style="color: #6366f1;"></i>
        <?= t('Task Completion Report & Verification') ?>
    </h3>

    <div class="sb-deliv-gate <?= $approved ? 'is-open' : 'is-shut' ?>" style="padding: 10px 14px; border-radius: 8px; font-weight: 600; font-size: 0.88rem; display: flex; align-items: center; gap: 8px; margin-bottom: 14px; <?= $approved ? 'background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;' : 'background: #fef3c7; color: #92400e; border: 1px solid #fde68a;' ?>">
        <?php if ($approved): ?>
            <i class="fa fa-check-circle" style="color: #16a34a; font-size: 1.1rem;"></i>
            <?= t('Approved & Verified by Admin. This task is eligible to be marked Completed.') ?>
        <?php else: ?>
            <i class="fa fa-lock" style="color: #d97706; font-size: 1.1rem;"></i>
            <?= t('Completion Gate Locked: Submit a Google Drive or live demo link below for Admin verification before this task can be completed.') ?>
        <?php endif ?>
    </div>

    <?php if (! empty($rows)): ?>
        <ul class="sb-deliv-list is-compact" style="margin-bottom: 14px;">
            <?php foreach ($rows as $row): ?>
                <?php
                    $isGoogleDrive = strstr($row['url'], 'drive.google.com') || strstr($row['url'], 'docs.google.com');
                    $isGithub = strstr($row['url'], 'github.com');
                    $isFigma = strstr($row['url'], 'figma.com');
                ?>
                <li class="<?= $class[$row['status']] ?>">
                    <div class="sb-deliv-main">
                        <a class="sb-deliv-link" href="<?= $this->text->e($row['url']) ?>" target="_blank" rel="noopener noreferrer nofollow" title="<?= t('Open submitted document') ?>">
                            <?php if ($isGoogleDrive): ?>
                                <i class="fa fa-google" style="color: #4285f4;"></i>
                            <?php elseif ($isGithub): ?>
                                <i class="fa fa-github" style="color: #333;"></i>
                            <?php elseif ($isFigma): ?>
                                <i class="fa fa-paint-brush" style="color: #a259ff;"></i>
                            <?php else: ?>
                                <i class="fa fa-external-link" style="color: #6366f1;"></i>
                            <?php endif ?>
                            <strong><?= $this->text->e($row['title'] !== '' ? $row['title'] : $row['url']) ?></strong>
                        </a>
                        <span class="sb-deliv-meta">
                            <?= t('Submitted by') ?> <strong><?= $this->text->e($row['submitter']) ?></strong><?= '' ?>
                            <?php if ($row['date_submitted'] > 0): ?> &middot; <?= $this->dt->datetime($row['date_submitted']) ?><?php endif ?>
                            <?php if ($row['status'] !== 'pending' && $row['reviewer'] !== ''): ?>
                                &middot; <?= t('reviewed by') ?> <strong><?= $this->text->e($row['reviewer']) ?></strong><?= '' ?>
                            <?php endif ?>
                        </span>
                        <?php if ($row['note'] !== '' && $row['note'] !== null): ?>
                            <p class="sb-deliv-note" style="background: #f8fafc; padding: 4px 8px; border-radius: 4px; margin-top: 4px; font-size: 0.82rem;">
                                <strong><?= t('Note') ?>:</strong> <?= $this->text->e($row['note']) ?>
                            </p>
                        <?php endif ?>
                        <?php if ($row['review_note'] !== '' && $row['review_note'] !== null): ?>
                            <p class="sb-deliv-note is-review" style="background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239; padding: 4px 8px; border-radius: 4px; margin-top: 4px; font-size: 0.82rem;">
                                <strong><i class="fa fa-commenting-o"></i> <?= t('Admin Feedback') ?>:</strong> <?= $this->text->e($row['review_note']) ?>
                            </p>
                        <?php endif ?>
                    </div>
                    <span class="sb-deliv-status <?= $class[$row['status']] ?>">
                        <?= $this->text->e($statuses[$row['status']]) ?>
                    </span>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>

    <div class="sb-deliv-task-actions" style="display: flex; gap: 12px; align-items: center;">
        <?= $this->modal->medium('upload', t('Submit Report / Link'), 'DeliverableController', 'create', array('plugin' => 'TaskManager', 'project_id' => $task['project_id'], 'task_id' => $task['id'])) ?>
        <a href="<?= $this->url->href('DeliverableController', 'index', array('plugin' => 'TaskManager', 'project_id' => $task['project_id'])) ?>" style="font-size: 0.85rem; color: #6366f1; text-decoration: none; font-weight: 600;">
            <i class="fa fa-arrow-right"></i> <?= t('View All Project Reports in Reports Tab') ?>
        </a>
    </div>
</div>
