<div class="page-header">
    <h2><?= t('Timesheet') ?></h2>
</div>

<div class="ts-weekbar">
    <div class="ts-weeknav">
        <a class="ts-weeknav-arrow" href="<?= $this->url->href('TimesheetController', 'show', array('project_id' => $project['id'], 'user_id' => $user_id, 'week' => strtotime('-7 day', $week_start), 'plugin' => 'TaskManager')) ?>" title="<?= t('Previous week') ?>">
            <i class="fa fa-chevron-left" aria-hidden="true"></i>
        </a>
        <span class="ts-weeklabel">
            <?= $this->dt->date($week_start) ?> &ndash; <?= $this->dt->date(strtotime('+6 day', $week_start)) ?>
        </span>
        <a class="ts-weeknav-arrow" href="<?= $this->url->href('TimesheetController', 'show', array('project_id' => $project['id'], 'user_id' => $user_id, 'week' => strtotime('+7 day', $week_start), 'plugin' => 'TaskManager')) ?>" title="<?= t('Next week') ?>">
            <i class="fa fa-chevron-right" aria-hidden="true"></i>
        </a>
    </div>

    <?php if ($can_approve): ?>
        <form method="get" action="<?= $this->url->dir() ?>" class="ts-userpick">
            <input type="hidden" name="controller" value="TimesheetController">
            <input type="hidden" name="action" value="show">
            <input type="hidden" name="plugin" value="TaskManager">
            <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
            <input type="hidden" name="week" value="<?= $week_start ?>">
            <select name="user_id" data-zg-submit aria-label="<?= t('Member') ?>">
                <?php foreach ($users as $id => $name): ?>
                    <option value="<?= $id ?>" <?= $id == $user_id ? 'selected' : '' ?>><?= $this->text->e($name) ?></option>
                <?php endforeach ?>
            </select>
        </form>
    <?php endif ?>

    <div class="ts-weekbar-actions">
        <span class="ts-state ts-state-<?= $this->text->e($sheet['state']) ?>"><?= $this->text->e($states[$sheet['state']]) ?></span>

        <?php if ($can_approve): ?>
            <a class="btn-add-secondary" href="<?= $this->url->href('TimesheetController', 'export', array('project_id' => $project['id'], 'week' => $week_start, 'plugin' => 'TaskManager')) ?>">
                <i class="fa fa-download" aria-hidden="true"></i> <?= t('Export CSV') ?>
            </a>
        <?php endif ?>

        <?php if (! $locked && $sheet['state'] !== 'submitted'): ?>
            <a class="btn-add-task" href="<?= $this->url->href('TimesheetController', 'submit', array('project_id' => $project['id'], 'user_id' => $user_id, 'week' => $week_start, 'plugin' => 'TaskManager', 'csrf_token' => $this->app->getToken()->getReusableCSRFToken())) ?>">
                <i class="fa fa-check" aria-hidden="true"></i> <?= t('Submit for approval') ?>
            </a>
        <?php endif ?>
    </div>
</div>

<?php if ($sheet['state'] === 'submitted' && $can_approve && $sheet['id']): ?>
    <div class="ts-approval">
        <span><?= t('This week is waiting on a decision.') ?></span>
        <a class="btn-add-task" href="<?= $this->url->href('TimesheetController', 'decide', array('project_id' => $project['id'], 'timesheet_id' => $sheet['id'], 'state' => 'approved', 'plugin' => 'TaskManager', 'csrf_token' => $this->app->getToken()->getReusableCSRFToken())) ?>"><?= t('Approve') ?></a>
        <a class="btn-add-secondary" href="<?= $this->url->href('TimesheetController', 'decide', array('project_id' => $project['id'], 'timesheet_id' => $sheet['id'], 'state' => 'rejected', 'plugin' => 'TaskManager', 'csrf_token' => $this->app->getToken()->getReusableCSRFToken())) ?>"><?= t('Send back') ?></a>
    </div>
<?php endif ?>

<div class="ts-tiles">
    <div class="ts-tile">
        <span class="ts-tile-label"><?= t('Total logged') ?></span>
        <span class="ts-tile-value"><?= number_format($summary['total'], 1) ?><small>h</small></span>
    </div>
    <div class="ts-tile">
        <span class="ts-tile-label"><?= t('Billable') ?></span>
        <span class="ts-tile-value is-billable"><?= number_format($summary['billable'], 1) ?><small>h</small></span>
    </div>
    <div class="ts-tile">
        <span class="ts-tile-label"><?= t('Internal') ?></span>
        <span class="ts-tile-value"><?= number_format($summary['non_billable'], 1) ?><small>h</small></span>
    </div>
    <div class="ts-tile">
        <span class="ts-tile-label"><?= t('Approval') ?></span>
        <span class="ts-state ts-state-<?= $this->text->e($sheet['state']) ?>"><?= $this->text->e($states[$sheet['state']]) ?></span>
    </div>
</div>

<form method="post" action="<?= $this->url->href('TimesheetController', 'save', array('project_id' => $project['id'], 'user_id' => $user_id, 'week' => $week_start, 'plugin' => 'TaskManager')) ?>">
    <?= $this->form->csrf() ?>

    <div class="ts-grid-wrapper">
        <table class="ts-grid">
            <thead>
                <tr>
                    <th class="ts-col-task"><?= t('Task') ?></th>
                    <th class="ts-col-type"><?= t('Billable') ?></th>
                    <?php foreach ($days as $day): ?>
                        <th class="ts-col-day <?= date('Y-m-d', $day) === date('Y-m-d') ? 'is-today' : '' ?> <?= (int) date('N', $day) >= 6 ? 'is-weekend' : '' ?>">
                            <?= date('D', $day) ?> <span><?= date('j', $day) ?></span>
                        </th>
                    <?php endforeach ?>
                    <th class="ts-col-total"><?= t('Total') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="<?= count($days) + 3 ?>" class="ts-empty"><?= t('Nothing logged for this week yet.') ?></td></tr>
                <?php endif ?>

                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="ts-col-task">
                            <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'task_id' => $row['task_id'])) ?>"
                               data-zp-open="<?= $this->url->href('TaskPanelController', 'show', array('plugin' => 'TaskManager', 'task_id' => $row['task_id'], 'project_id' => $project['id'])) ?>">
                                <?= $this->text->e($row['title']) ?>
                            </a>
                        </td>
                        <td class="ts-col-type">
                            <input type="checkbox" name="billable[<?= $row['task_id'] ?>]" value="1" <?= $row['is_billable'] ? 'checked' : '' ?> <?= $locked ? 'disabled' : '' ?>>
                        </td>
                        <?php foreach ($days as $day): ?>
                            <td class="ts-col-day <?= (int) date('N', $day) >= 6 ? 'is-weekend' : '' ?>">
                                <input type="text" inputmode="decimal" class="ts-cell"
                                       name="hours[<?= $row['task_id'] ?>][<?= $day ?>]"
                                       value="<?= isset($row['days'][$day]) && $row['days'][$day] > 0 ? rtrim(rtrim(number_format($row['days'][$day], 2, '.', ''), '0'), '.') : '' ?>"
                                       <?= $locked ? 'readonly' : '' ?>>
                            </td>
                        <?php endforeach ?>
                        <td class="ts-col-total"><?= number_format($row['total'], 1) ?></td>
                    </tr>
                <?php endforeach ?>

                <?php if (! $locked): ?>
                    <tr class="ts-addrow">
                        <td colspan="<?= count($days) + 3 ?>">
                            <form method="get" action="<?= $this->url->dir() ?>">
                                <input type="hidden" name="controller" value="TimesheetController">
                                <input type="hidden" name="action" value="show">
                                <input type="hidden" name="plugin" value="TaskManager">
                                <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                                <input type="hidden" name="user_id" value="<?= $user_id ?>">
                                <input type="hidden" name="week" value="<?= $week_start ?>">
                                <select name="add_task" data-zg-submit aria-label="<?= t('Add a task') ?>">
                                    <?php foreach ($tasks as $id => $label): ?>
                                        <option value="<?= $id ?>"><?= $this->text->e($label) ?></option>
                                    <?php endforeach ?>
                                </select>
                            </form>
                        </td>
                    </tr>
                <?php endif ?>
            </tbody>
            <tfoot>
                <tr>
                    <td class="ts-col-task"><?= t('Daily total') ?></td>
                    <td></td>
                    <?php foreach ($days as $day): ?>
                        <td class="ts-col-day"><?= $summary['by_day'][$day] > 0 ? number_format($summary['by_day'][$day], 1) : '&mdash;' ?></td>
                    <?php endforeach ?>
                    <td class="ts-col-total"><?= number_format($summary['total'], 1) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <?php if (! $locked): ?>
        <div class="form-actions ts-actions">
            <button type="submit" class="btn btn-blue"><?= t('Save') ?></button>
            <?php if ($can_approve): ?>
                <a class="btn-add-secondary" href="<?= $this->url->href('TimesheetController', 'backfill', array('project_id' => $project['id'], 'plugin' => 'TaskManager', 'csrf_token' => $this->app->getToken()->getReusableCSRFToken())) ?>">
                    <?= t('Import existing subtask timers') ?>
                </a>
            <?php endif ?>
        </div>
    <?php else: ?>
        <p class="alert alert-info"><?= t('This week is locked. Only a draft or a sent-back week can be edited.') ?></p>
    <?php endif ?>
</form>

<?php if (! empty($pending)): ?>
    <div class="ts-pending">
        <h3><?= t('Waiting for approval') ?></h3>
        <ul>
            <?php foreach ($pending as $item): ?>
                <li>
                    <a href="<?= $this->url->href('TimesheetController', 'show', array('project_id' => $project['id'], 'user_id' => $item['user_id'], 'week' => $item['week_start'], 'plugin' => 'TaskManager')) ?>">
                        <?= $this->text->e(isset($users[$item['user_id']]) ? $users[$item['user_id']] : $item['user_id']) ?>
                        &mdash; <?= $this->dt->date($item['week_start']) ?>
                    </a>
                </li>
            <?php endforeach ?>
        </ul>
    </div>
<?php endif ?>
