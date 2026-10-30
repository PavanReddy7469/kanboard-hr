<?php
/* A single task, one of: starting today, due soon, due today. */

$accent = $kind === 'due_today' ? '#dc2626' : ($kind === 'due_soon' ? '#d97706' : '#2563eb');

if ($kind === 'due_today') {
    $headline = t('This is due today');
} elseif ($kind === 'due_soon') {
    $headline = $days === 1 ? t('This is due tomorrow') : t('This is due in %d days', $days);
} else {
    $headline = t('This starts today');
}

/* application_url is a stored setting, not a constant in config.php. Until
   somebody fills it in under Settings > Application, absoluteLink() builds
   http://localhost/ and every link in every mail is dead - so when it is
   empty the mail says so plainly instead of shipping a broken button. */
$hasUrl = $this->app->config('application_url') !== '';

ob_start();
?>
<p style="margin: 0 0 4px; font-size: 13px; color: <?= $accent ?>; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;">
    <?= $this->text->e($headline) ?>
</p>

<h1 style="margin: 0 0 14px; font-size: 19px; line-height: 1.35; font-weight: 700; color: #0f172a;">
    #<?= (int) $task['id'] ?> <?= $this->text->e($task['title']) ?>
</h1>

<table cellpadding="0" cellspacing="0" style="width: 100%; font-size: 13.5px; margin-bottom: 20px;">
    <tr>
        <td style="padding: 5px 0; color: #64748b; width: 110px;"><?= t('Project') ?></td>
        <td style="padding: 5px 0; color: #1e293b; font-weight: 600;"><?= $this->text->e($task['project_name']) ?></td>
    </tr>
    <?php if (! empty($task['date_started'])): ?>
        <tr>
            <td style="padding: 5px 0; color: #64748b;"><?= t('Start Date') ?></td>
            <td style="padding: 5px 0; color: #1e293b;"><?= $this->dt->date($task['date_started']) ?></td>
        </tr>
    <?php endif ?>
    <?php if (! empty($task['date_due'])): ?>
        <tr>
            <td style="padding: 5px 0; color: #64748b;"><?= t('Due Date') ?></td>
            <td style="padding: 5px 0; color: <?= $accent ?>; font-weight: 700;"><?= $this->dt->date($task['date_due']) ?></td>
        </tr>
    <?php endif ?>
</table>

<?php if ($hasUrl): ?>
    <a href="<?= $this->url->base() ?>?controller=TaskGridController&amp;action=show&amp;plugin=TaskManager&amp;project_id=<?= (int) $task['project_id'] ?>&amp;task_id=<?= (int) $task['id'] ?>"
       style="display: inline-block; background: #0f172a; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; padding: 11px 20px; border-radius: 8px;">
        <?= t('Open this task') ?>
    </a>
<?php else: ?>
    <p style="margin: 0; font-size: 13px; color: #b45309;">
        <?= t('No portal address is configured yet, so this email cannot link to the task. An administrator can set it under Settings, Application, Application URL.') ?>
    </p>
<?php endif ?>
<?php
$content = ob_get_clean();
echo $this->render('TaskManager:notification/_shell', array(
    'content' => $content,
    'reason'  => t('You are receiving this because this task is assigned to you.'),
));
