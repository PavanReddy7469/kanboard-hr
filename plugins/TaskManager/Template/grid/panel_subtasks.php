<?php if ($can_edit): ?>
    <div class="zp-tabaction" style="margin-bottom: 12px; display: flex; justify-content: flex-end;">
        <?= $this->modal->medium('plus', t('Add a subtask'), 'SubtaskController', 'create', array('task_id' => $task['id'], 'project_id' => $task['project_id'])) ?>
    </div>
<?php endif ?>

<?php if (empty($subtasks)): ?>
    <p class="zp-empty"><?= t('There is no subtask.') ?></p>
<?php else: ?>
    <table class="zp-table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 1px solid #e2e8f0; font-size: 0.75rem; color: #64748b; text-transform: uppercase;">
                <th style="padding: 8px 10px; width: 40px;"></th>
                <th style="padding: 8px 10px;"><?= t('Subtask') ?></th>
                <th style="padding: 8px 10px; width: 120px;"><?= t('Assignee') ?></th>
                <th style="padding: 8px 10px; width: 110px;"><?= t('Status') ?></th>
                <?php if ($can_edit): ?>
                    <th style="padding: 8px 10px; width: 90px; text-align: right;"><?= t('Actions') ?></th>
                <?php endif ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($subtasks as $subtask): ?>
                <?php
                    $isDone = (int)$subtask['status'] === 2;
                    $isInProgress = (int)$subtask['status'] === 1;
                    $statusBadgeClass = $isDone ? 'status-closed' : ($isInProgress ? 'status-wip' : 'status-open');
                ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <!-- Status Toggle Checkbox -->
                    <td style="padding: 10px; text-align: center;">
                        <?php if ($can_edit): ?>
                            <?= $this->subtask->renderToggleStatusCheckbox($task, $subtask) ?>
                        <?php else: ?>
                            <i class="fa <?= $isDone ? 'fa-check-square-o' : ($isInProgress ? 'fa-gears' : 'fa-square-o') ?>" style="color: <?= $isDone ? '#10b981' : '#64748b' ?>;"></i>
                        <?php endif ?>
                    </td>

                    <!-- Title -->
                    <td style="padding: 10px;">
                        <span style="font-weight: 600; color: <?= $isDone ? '#94a3b8; text-decoration: line-through;' : '#1e293b;' ?>">
                            <?= $this->text->e($subtask['title']) ?>
                        </span>
                    </td>

                    <!-- Assignee -->
                    <td style="padding: 10px;">
                        <?php if (! empty($subtask['username'])): ?>
                            <span class="zp-chip" style="display: inline-block; font-size: 0.76rem; padding: 2px 8px; border-radius: 6px; background: #e0e7ff; color: #4338ca; font-weight: 600;">
                                <?= $this->text->e($subtask['name'] ?: $subtask['username']) ?>
                            </span>
                        <?php else: ?>
                            <span class="zp-muted" style="color: #94a3b8; font-size: 0.8rem;">&mdash;</span>
                        <?php endif ?>
                    </td>

                    <!-- Status Badge -->
                    <td style="padding: 10px;">
                        <span class="zg-status <?= $statusBadgeClass ?>" style="display: inline-block; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 6px;">
                            <?= $this->text->e($subtask['status_name']) ?>
                        </span>
                    </td>

                    <!-- Actions (Edit & Delete) -->
                    <?php if ($can_edit): ?>
                        <td style="padding: 10px; text-align: right; white-space: nowrap;">
                            <?= $this->modal->medium('pencil', '', 'SubtaskController', 'edit', array('task_id' => $task['id'], 'project_id' => $task['project_id'], 'subtask_id' => $subtask['id']), 'zp-action-link') ?>
                            &nbsp;
                            <?= $this->modal->confirm('trash-o', '', 'SubtaskController', 'confirm', array('task_id' => $task['id'], 'project_id' => $task['project_id'], 'subtask_id' => $subtask['id']), 'zp-action-link zp-action-danger') ?>
                        </td>
                    <?php endif ?>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
