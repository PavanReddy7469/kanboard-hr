<?php if ($can_edit): ?>
    <div class="zp-tabaction" style="margin-bottom: 12px; display: flex; justify-content: flex-end;">
        <?= $this->modal->medium('plus', t('Add a dependency'), 'DependencyController', 'create', array('plugin' => 'TaskManager', 'task_id' => $task['id'], 'project_id' => $task['project_id'])) ?>
    </div>
<?php endif ?>

<?php if (empty($dependencies)): ?>
    <p class="zp-empty"><?= t('This task has no dependency.') ?></p>
<?php else: ?>
    <table class="zp-table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom: 1px solid #e2e8f0; font-size: 0.75rem; color: #64748b; text-transform: uppercase;">
                <th style="padding: 8px 10px; width: 140px;"><?= t('Relation') ?></th>
                <th style="padding: 8px 10px;"><?= t('Dependent Task') ?></th>
                <th style="padding: 8px 10px; width: 80px;"><?= t('Lag') ?></th>
                <?php if ($can_edit): ?>
                    <th style="padding: 8px 10px; width: 60px; text-align: right;"><?= t('Action') ?></th>
                <?php endif ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dependencies as $dependency): ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 10px;">
                        <span class="zp-chip" style="display: inline-block; font-size: 0.76rem; font-weight: 700; padding: 2px 8px; border-radius: 6px; background: #f1f5f9; color: #475569;">
                            <?= $this->text->e($this->dependency->getShortLabel($dependency)) ?>
                        </span>
                    </td>
                    <td style="padding: 10px;">
                        <a href="<?= $this->url->href('TaskViewController', 'show', array('task_id' => $dependency['depends_on_id'], 'project_id' => $task['project_id'])) ?>"
                           data-zp-open="<?= $this->url->href('TaskPanelController', 'show', array('plugin' => 'TaskManager', 'task_id' => $dependency['depends_on_id'], 'project_id' => $task['project_id'])) ?>"
                           style="font-weight: 600; color: #4f46e5; text-decoration: none;">
                            #<?= $dependency['depends_on_id'] ?> <?= $this->text->e($dependency['depends_on_title']) ?>
                        </a>
                    </td>
                    <td style="padding: 10px; color: #64748b; font-weight: 600; font-size: 0.82rem;">
                        <?= (int) $dependency['lag_days'] ?>d
                    </td>
                    <?php if ($can_edit): ?>
                        <td style="padding: 10px; text-align: right;">
                            <?= $this->modal->confirm('trash-o', '', 'DependencyController', 'confirm', array('plugin' => 'TaskManager', 'task_id' => $task['id'], 'project_id' => $task['project_id'], 'dependency_id' => $dependency['id']), 'zp-action-link zp-action-danger') ?>
                        </td>
                    <?php endif ?>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
