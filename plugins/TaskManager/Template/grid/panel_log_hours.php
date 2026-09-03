<div class="zp-tabaction">
    <?= $this->url->link(t('Open timesheet'), 'TimesheetController', 'show', array('plugin' => 'TaskManager', 'project_id' => $task['project_id'], 'add_task' => $task['id'])) ?>
</div>

<?php if (empty($entries)): ?>
    <p class="zp-empty"><?= t('No hours logged against this task yet.') ?></p>
<?php else: ?>
    <table class="zp-table">
        <thead>
            <tr>
                <th><?= t('Date') ?></th>
                <th><?= t('User') ?></th>
                <th><?= t('Hours') ?></th>
                <th><?= t('Billable') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($entries as $entry): ?>
                <tr>
                    <td><?= $this->dt->date($entry['work_date']) ?></td>
                    <td><?= $this->text->e($entry['user']) ?></td>
                    <td><?= number_format($entry['hours'], 2) ?></td>
                    <td><?= $entry['is_billable'] ? t('Yes') : t('No') ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
