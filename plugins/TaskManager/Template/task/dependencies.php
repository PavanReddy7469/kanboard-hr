<?php $dependencies = $this->dependency->getAllByTask($task['id']) ?>
<?php $successors  = $this->dependency->getSuccessorDetails($task['id']) ?>
<?php $canManage   = $this->taskTree->canManageTasks($task['project_id']) ?>

<div class="task-show-section">
    <div class="buttons-header">
        <?php if ($canManage): ?>
            <?= $this->modal->medium('sitemap', t('Add dependency'), 'DependencyController', 'create', array('task_id' => $task['id'], 'project_id' => $task['project_id'], 'plugin' => 'TaskManager')) ?>
        <?php endif ?>
    </div>

    <h3><?= t('Dependencies') ?></h3>

    <?php if (empty($dependencies)): ?>
        <p class="alert"><?= t('This task does not depend on anything.') ?></p>
    <?php else: ?>
        <table class="table-striped">
            <thead>
                <tr>
                    <th><?= t('Depends on') ?></th>
                    <th><?= t('Type') ?></th>
                    <th><?= t('Lag') ?></th>
                    <?php if ($canManage): ?><th class="tm-right"><?= t('Actions') ?></th><?php endif ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dependencies as $dependency): ?>
                    <tr>
                        <td>
                            <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $task['project_id'], 'task_id' => $dependency['depends_on_id'])) ?>"
                               data-zp-open="<?= $this->url->href('TaskPanelController', 'show', array('plugin' => 'TaskManager', 'task_id' => $dependency['depends_on_id'], 'project_id' => $task['project_id'])) ?>">
                                #<?= $dependency['depends_on_id'] ?> <?= $this->text->e($dependency['depends_on_title']) ?>
                            </a>
                            <?php if (empty($dependency['depends_on_is_active'])): ?>
                                <span class="status-badge status-closed"><?= t('Closed') ?></span>
                            <?php endif ?>
                        </td>
                        <td><span class="dep-chip"><?= $this->text->e($dependency['dependency_type']) ?></span></td>
                        <td><?= (int) $dependency['lag_days'] ? sprintf('%+dd', (int) $dependency['lag_days']) : '&mdash;' ?></td>
                        <?php if ($canManage): ?>
                            <td class="tm-right">
                                <?= $this->modal->medium('trash-o', t('Remove'), 'DependencyController', 'confirm', array('task_id' => $task['id'], 'dependency_id' => $dependency['id'], 'project_id' => $task['project_id'], 'plugin' => 'TaskManager')) ?>
                            </td>
                        <?php endif ?>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>

    <?php /* The same edges read the other way round. Without this a person can
             see what is holding their task up, but not who is waiting on them -
             which is the half that decides whether something is urgent. */ ?>
    <?php if (! empty($successors)): ?>
        <h3><?= t('Blocking') ?></h3>
        <p class="form-help"><?= t('These tasks are waiting on this one.') ?></p>

        <table class="table-striped">
            <thead>
                <tr>
                    <th><?= t('Task') ?></th>
                    <th><?= t('Type') ?></th>
                    <th><?= t('Lag') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($successors as $successor): ?>
                    <tr>
                        <td>
                            <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $task['project_id'], 'task_id' => $successor['task_id'])) ?>"
                               data-zp-open="<?= $this->url->href('TaskPanelController', 'show', array('plugin' => 'TaskManager', 'task_id' => $successor['task_id'], 'project_id' => $task['project_id'])) ?>">
                                #<?= $successor['task_id'] ?> <?= $this->text->e($successor['task_title']) ?>
                            </a>
                            <?php if (empty($successor['task_is_active'])): ?>
                                <span class="status-badge status-closed"><?= t('Closed') ?></span>
                            <?php endif ?>
                        </td>
                        <td><span class="dep-chip"><?= $this->text->e($successor['dependency_type']) ?></span></td>
                        <td><?= (int) $successor['lag_days'] ? sprintf('%+dd', (int) $successor['lag_days']) : '&mdash;' ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>
