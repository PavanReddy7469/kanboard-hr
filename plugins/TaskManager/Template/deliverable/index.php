<?php
/* The project's Reports tab.
   Completion Reports & Verification Hub for SUPERBEE.
   Assignees submit Google Drive / Live links to finished tasks; Admins review and approve them. */
$statusClass = array(
    'pending'  => 'is-pending',
    'approved' => 'is-approved',
    'rejected' => 'is-rejected',
);
?>
<section id="main">
    <?= $this->projectHeader->render($project, 'DeliverableController', 'index', false, 'TaskManager') ?>

    <div class="sb-dash sb-deliv" style="margin-top: 15px;">

        <div class="sb-dash-head" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="font-size: 1.4rem; font-weight: 800; color: #0f172a; margin: 0 0 6px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-check-circle-o" style="color: #6366f1;"></i>
                    <?= t('Task Completion Reports & Verification') ?>
                </h2>
                <p class="sb-dash-sub" style="color: #64748b; font-size: 0.9rem; margin: 0; max-width: 750px; line-height: 1.5;">
                    <?= t('Assignees submit live links or Google Drive documents for finished work. An Administrator or Project Manager verifies and approves them. A task is only marked Completed after verification.') ?>
                </p>
            </div>
            <div class="sb-dash-head-actions">
                <?= $this->modal->medium('plus', t('Submit Task Report / Link'), 'DeliverableController', 'create', array('plugin' => 'TaskManager', 'project_id' => $project['id'])) ?>
            </div>
        </div>

        <!-- Filter Chips -->
        <div class="sb-deliv-filters" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px;">
            <a class="sb-chip <?= $filter === '' ? 'is-on' : '' ?>"
               href="<?= $this->url->href('DeliverableController', 'index', array('plugin' => 'TaskManager', 'project_id' => $project['id'])) ?>">
                <i class="fa fa-list"></i> <?= t('All Reports') ?> <em><?= array_sum($counts) ?></em>
            </a>
            <?php foreach ($statuses as $key => $label): ?>
                <a class="sb-chip <?= $filter === $key ? 'is-on' : '' ?> <?= $statusClass[$key] ?>"
                   href="<?= $this->url->href('DeliverableController', 'index', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'status' => $key)) ?>">
                    <?php if ($key === 'pending'): ?>
                        <i class="fa fa-clock-o" style="color: #f59e0b;"></i>
                    <?php elseif ($key === 'approved'): ?>
                        <i class="fa fa-check-circle" style="color: #10b981;"></i>
                    <?php else: ?>
                        <i class="fa fa-exclamation-circle" style="color: #ef4444;"></i>
                    <?php endif ?>
                    <?= $this->text->e($label) ?> <em><?= $counts[$key] ?></em>
                </a>
            <?php endforeach ?>
        </div>

        <?php if (empty($rows)): ?>
            <div class="sb-card" style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 25px; text-align: center;">
                <div class="sb-card-body">
                    <p class="sb-empty" style="color: #64748b; font-size: 0.95rem; margin: 0;">
                        <i class="fa fa-folder-open-o" style="font-size: 2rem; color: #cbd5e1; display: block; margin-bottom: 8px;"></i>
                        <?= $filter === '' ? t('No completion reports submitted for this project yet.') : t('No reports with that status.') ?>
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div class="sb-card" style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px -3px rgba(0,0,0,0.03);">
                <div class="sb-card-head" style="padding: 14px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; font-size: 1rem; font-weight: 700; color: #1e293b;"><i class="fa fa-file-text-o text-indigo"></i> <?= t('Submitted Reports & Evidence') ?></h3>
                    <span class="sb-card-note" style="font-size: 0.8rem; color: #64748b;"><?= t('Awaiting verification shown first') ?></span>
                </div>
                <div class="sb-card-body" style="padding: 0 20px;">
                    <ul class="sb-deliv-list">
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $isGoogleDrive = strstr($row['url'], 'drive.google.com') || strstr($row['url'], 'docs.google.com');
                                $isGithub = strstr($row['url'], 'github.com') || strstr($row['url'], 'gitlab.com');
                                $isFigma = strstr($row['url'], 'figma.com');
                            ?>
                            <li class="<?= $statusClass[$row['status']] ?>">
                                <div class="sb-deliv-main">
                                    <a class="sb-deliv-task"
                                       href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'task_id' => $row['task_id'])) ?>"
                                       data-zp-open="<?= $this->url->href('TaskPanelController', 'show', array('plugin' => 'TaskManager', 'task_id' => $row['task_id'], 'project_id' => $project['id'])) ?>">
                                        <i class="fa fa-tasks"></i> #<?= $row['task_id'] ?> <?= $this->text->e($row['task_title']) ?>
                                    </a>

                                    <a class="sb-deliv-link" href="<?= $this->text->e($row['url']) ?>" target="_blank" rel="noopener noreferrer nofollow" title="<?= t('Open submitted live document') ?>">
                                        <?php if ($isGoogleDrive): ?>
                                            <i class="fa fa-google" style="color: #4285f4;" aria-hidden="true"></i>
                                        <?php elseif ($isGithub): ?>
                                            <i class="fa fa-github" style="color: #333;" aria-hidden="true"></i>
                                        <?php elseif ($isFigma): ?>
                                            <i class="fa fa-paint-brush" style="color: #a259ff;" aria-hidden="true"></i>
                                        <?php else: ?>
                                            <i class="fa fa-external-link" style="color: #6366f1;" aria-hidden="true"></i>
                                        <?php endif ?>
                                        <span><?= $this->text->e($row['title'] !== '' ? $row['title'] : $row['url']) ?></span>
                                    </a>

                                    <span class="sb-deliv-meta">
                                        <?= t('Submitted by') ?> <strong><?= $this->text->e($row['submitter']) ?></strong><?= '' ?>
                                        <?php if ($row['date_submitted'] > 0): ?>
                                            &middot; <?= $this->dt->datetime($row['date_submitted']) ?>
                                        <?php endif ?>
                                        <?php if ($row['status'] !== 'pending' && $row['reviewer'] !== ''): ?>
                                            &middot; <?= t('reviewed by') ?> <strong><?= $this->text->e($row['reviewer']) ?></strong><?= '' ?>
                                        <?php endif ?>
                                    </span>

                                    <?php if ($row['note'] !== '' && $row['note'] !== null): ?>
                                        <p class="sb-deliv-note" style="background: #f1f5f9; padding: 6px 10px; border-radius: 6px; margin-top: 6px;">
                                            <strong><i class="fa fa-sticky-note-o"></i> <?= t('Note') ?>:</strong> <?= $this->text->e($row['note']) ?>
                                        </p>
                                    <?php endif ?>

                                    <?php if ($row['review_note'] !== '' && $row['review_note'] !== null): ?>
                                        <p class="sb-deliv-note is-review" style="background: #fff1f2; border: 1px solid #fecdd3; color: #9f1239; padding: 6px 10px; border-radius: 6px; margin-top: 6px;">
                                            <strong><i class="fa fa-commenting-o"></i> <?= t('Admin Feedback') ?>:</strong> <?= $this->text->e($row['review_note']) ?>
                                        </p>
                                    <?php endif ?>
                                </div>

                                <div class="sb-deliv-side">
                                    <span class="sb-deliv-status <?= $statusClass[$row['status']] ?>">
                                        <?= $this->text->e($statuses[$row['status']]) ?>
                                    </span>

                                    <?php if ($can_approve && $row['status'] !== 'approved'): ?>
                                        <a class="sb-deliv-act is-approve"
                                           href="<?= $this->url->href('DeliverableController', 'approve', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'deliverable_id' => $row['id']), true) ?>"
                                           title="<?= t('Verify report and mark task completed') ?>">
                                            <i class="fa fa-check" aria-hidden="true"></i> <?= t('Approve & Complete') ?>
                                        </a>
                                    <?php endif ?>

                                    <?php if ($can_approve && $row['status'] !== 'rejected'): ?>
                                        <?= $this->modal->medium('undo', t('Request Changes'), 'DeliverableController', 'reviewModal', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'deliverable_id' => $row['id'])) ?>
                                    <?php endif ?>
                                </div>
                            </li>
                        <?php endforeach ?>
                    </ul>
                </div>
            </div>
        <?php endif ?>

        <?php if (! empty($open_tasks)): ?>
            <div class="sb-card sb-deliv-waiting" style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; margin-top: 20px; overflow: hidden;">
                <div class="sb-card-head" style="padding: 14px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; font-size: 1rem; font-weight: 700; color: #1e293b;"><i class="fa fa-clock-o text-amber"></i> <?= t('Open Tasks Awaiting Verification') ?></h3>
                    <span class="sb-card-note" style="font-size: 0.8rem; color: #64748b;"><?= t('Cannot be closed until verified and approved') ?></span>
                </div>
                <div class="sb-card-body" style="padding: 0 20px;">
                    <ul class="sb-deliv-waitlist">
                        <?php foreach ($open_tasks as $task): ?>
                            <li>
                                <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'task_id' => $task['id'])) ?>"
                                   data-zp-open="<?= $this->url->href('TaskPanelController', 'show', array('plugin' => 'TaskManager', 'task_id' => $task['id'], 'project_id' => $project['id'])) ?>"
                                   style="font-weight: 600; color: #334155; text-decoration: none;">
                                    #<?= $task['id'] ?> <?= $this->text->e($task['title']) ?>
                                </a>
                                <?= $this->modal->small('upload', t('Submit Report / Link'), 'DeliverableController', 'create', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'task_id' => $task['id'])) ?>
                            </li>
                        <?php endforeach ?>
                    </ul>
                </div>
            </div>
        <?php endif ?>

    </div>
</section>
