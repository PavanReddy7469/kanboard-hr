<div class="page-header">
    <h2><?= t('Milestones') ?></h2>
    <?php if ($is_lead): ?>
        <ul>
            <li>
                <?= $this->modal->medium('plus', t('New milestone'), 'MilestoneController', 'create', array('project_id' => $project['id'], 'plugin' => 'TaskManager')) ?>
            </li>
        </ul>
    <?php endif ?>
</div>

<?php if (empty($milestones)): ?>
    <p class="alert alert-info"><?= t('No milestone in this project yet.') ?></p>
<?php else: ?>
    <table class="table-striped milestone-table">
        <thead>
            <tr>
                <th><?= t('Milestone') ?></th>
                <th><?= t('Start date') ?></th>
                <th><?= t('Due date') ?></th>
                <th><?= t('Progress') ?></th>
                <th><?= t('Tasks') ?></th>
                <th class="tm-right"><?= t('Actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($milestones as $milestone): ?>
                <tr>
                    <td>
                        <span class="milestone-diamond <?= $milestone['is_reached'] ? 'is-done' : ($milestone['is_overdue'] ? 'is-late' : '') ?>"></span>
                        <strong><?= $this->text->e($milestone['title']) ?></strong>
                        <?php if ($milestone['is_overdue']): ?>
                            <span class="chip-risk"><?= t('AT RISK') ?></span>
                        <?php endif ?>
                    </td>
                    <td class="tm-date"><?= $milestone['date_start'] ? $this->dt->date($milestone['date_start']) : '-' ?></td>
                    <td class="tm-date"><?= $milestone['date_due'] ? $this->dt->date($milestone['date_due']) : '-' ?></td>
                    <td>
                        <div class="progress-track" title="<?= (int) $milestone['progress'] ?>%">
                            <div class="progress-fill" style="width: <?= (int) $milestone['progress'] ?>%;"></div>
                        </div>
                    </td>
                    <td><?= (int) $milestone['nb_closed'] ?> / <?= (int) $milestone['nb_tasks'] ?></td>
                    <td class="tm-right">
                        <?php if ($is_lead): ?>
                            <?= $this->modal->medium('pencil-square-o', t('Edit'), 'MilestoneController', 'edit', array('project_id' => $project['id'], 'milestone_id' => $milestone['id'], 'plugin' => 'TaskManager')) ?>
                            <?= $this->modal->medium('trash-o', t('Remove'), 'MilestoneController', 'confirm', array('project_id' => $project['id'], 'milestone_id' => $milestone['id'], 'plugin' => 'TaskManager')) ?>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
