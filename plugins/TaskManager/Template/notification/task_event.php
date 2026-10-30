<?php
/* Every task notification: created, updated, reassigned, closed, reopened,
   moved, commented, subtask changed, file attached, mentioned, overdue.
   The headline comes from core's own per-event title, so an event this
   template has never heard of still arrives labelled correctly. */

/* Accent by severity, not by event name - a list of 23 cases would need
   editing every time core adds one. */
$accent = '#2563eb';
if (strpos($event_name, 'close') !== false)            { $accent = '#059669'; }
elseif (strpos($event_name, 'overdue') !== false)      { $accent = '#dc2626'; }
elseif (strpos($event_name, 'assignee') !== false)     { $accent = '#7c3aed'; }
elseif (strpos($event_name, 'comment') !== false)      { $accent = '#0891b2'; }

$hasUrl = $this->app->config('application_url') !== '';

/* Human labels for the columns that actually change. A field with no entry
   here is still shown, under its own column name, rather than hidden - a
   change the reader cannot see is worse than one labelled awkwardly. */
$labels = array(
    'title'        => t('Title'),
    'description'  => t('Description'),
    'owner_id'     => t('Assignee'),
    'column_id'    => t('Status'),
    'priority'     => t('Priority'),
    'date_due'     => t('Due Date'),
    'date_started' => t('Start Date'),
    'category_id'  => t('Category'),
    'swimlane_id'  => t('Swimlane'),
    'score'        => t('Complexity'),
    'time_spent'   => t('Time spent'),
    'time_estimated' => t('Time estimated'),
    'reference'    => t('Reference'),
    'is_active'    => t('Status'),
);

/* Fields whose stored value is an id or a timestamp and would otherwise be
   reported to a human as "New owner_id: 4". The id ones cannot be resolved
   to a name from the event alone, so they are reported as changed without a
   value rather than with a meaningless one. */
$dateFields = array('date_due', 'date_started', 'date_completed');
$opaqueIds  = array('owner_id', 'column_id', 'category_id', 'swimlane_id');

$render = function ($field, $value) use ($dateFields, $opaqueIds) {
    if (in_array($field, $dateFields, true)) {
        return $value > 0 ? date('D, j M Y', (int) $value) : t('not set');
    }
    if (in_array($field, $opaqueIds, true)) {
        return '';
    }
    if ($field === 'description') {
        return '';
    }
    $value = (string) $value;
    return $value === '' ? t('empty') : $value;
};

ob_start();
?>

<p style="margin: 0 0 4px; font-size: 12.5px; color: <?= $accent ?>; font-weight: 700; text-transform: uppercase; letter-spacing: .6px;">
    <?= $this->text->e($headline) ?>
</p>

<h1 style="margin: 0 0 4px; font-size: 19px; line-height: 1.35; font-weight: 700; color: #0f172a;">
    #<?= (int) $task['id'] ?> <?= $this->text->e($task['title']) ?>
</h1>

<?php if ($actor !== ''): ?>
    <p style="margin: 0 0 16px; font-size: 12.5px; color: #64748b;">
        <?= t('by %s', $this->text->e($actor)) ?> &middot; <?= date('j M Y, H:i') ?>
    </p>
<?php else: ?>
    <p style="margin: 0 0 16px; font-size: 12.5px; color: #64748b;"><?= date('j M Y, H:i') ?></p>
<?php endif ?>

<?php /* ---- what changed ---------------------------------------------- */ ?>
<?php if (! empty($changes)): ?>
    <div style="background: #fffbeb; border: 1px solid #fde68a; border-left: 3px solid <?= $accent ?>; border-radius: 8px; padding: 12px 14px; margin-bottom: 18px;">
        <p style="margin: 0 0 8px; font-size: 11.5px; font-weight: 700; color: #92400e; text-transform: uppercase; letter-spacing: .5px;">
            <?= t('What changed') ?>
        </p>
        <?php foreach ($changes as $field => $value): ?>
            <?php
                $label = isset($labels[$field]) ? $labels[$field] : ucfirst(str_replace('_', ' ', $field));
                $to    = $render($field, $value);
                $from  = array_key_exists($field, $previous) ? $render($field, $previous[$field]) : null;
            ?>
            <div style="font-size: 13.5px; color: #1e293b; padding: 2px 0;">
                <strong><?= $this->text->e($label) ?></strong>
                <?php if ($to === ''): ?>
                    <?= t('was changed') ?>
                <?php elseif ($from !== null && $from !== $to): ?>
                    <span style="color: #94a3b8; text-decoration: line-through;"><?= $this->text->e($from) ?></span>
                    <span style="color: #94a3b8;">&rarr;</span>
                    <strong style="color: <?= $accent ?>;"><?= $this->text->e($to) ?></strong>
                <?php else: ?>
                    &rarr; <strong style="color: <?= $accent ?>;"><?= $this->text->e($to) ?></strong>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>

<?php /* ---- a new comment, in full ------------------------------------ */ ?>
<?php if (! empty($comment['comment'])): ?>
    <div style="background: #ecfeff; border: 1px solid #a5f3fc; border-radius: 8px; padding: 12px 14px; margin-bottom: 18px;">
        <p style="margin: 0 0 6px; font-size: 11.5px; font-weight: 700; color: #155e75; text-transform: uppercase; letter-spacing: .5px;"><?= t('Comment') ?></p>
        <div style="font-size: 13.5px; color: #1e293b; line-height: 1.55; white-space: pre-wrap;"><?= $this->text->e($comment['comment']) ?></div>
    </div>
<?php endif ?>

<?php if (! empty($subtask['title'])): ?>
    <div style="font-size: 13.5px; color: #1e293b; margin-bottom: 18px;">
        <strong><?= t('Subtask') ?>:</strong> <?= $this->text->e($subtask['title']) ?>
    </div>
<?php endif ?>

<?php if (! empty($file['name'])): ?>
    <div style="font-size: 13.5px; color: #1e293b; margin-bottom: 18px;">
        <strong><?= t('Attachment') ?>:</strong> <?= $this->text->e($file['name']) ?>
    </div>
<?php endif ?>

<?php /* ---- where the task stands now ---------------------------------- */ ?>
<table cellpadding="0" cellspacing="0" style="width: 100%; font-size: 13px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 18px;">
    <tr>
        <td style="padding: 10px 14px 3px; color: #64748b; width: 108px;"><?= t('Project') ?></td>
        <td style="padding: 10px 14px 3px; color: #0f172a; font-weight: 600;"><?= $this->text->e($task['project_name']) ?></td>
    </tr>
    <tr>
        <td style="padding: 3px 14px; color: #64748b;"><?= t('Status') ?></td>
        <td style="padding: 3px 14px; color: #0f172a;"><?= $this->text->e($task['column_title']) ?></td>
    </tr>
    <tr>
        <td style="padding: 3px 14px; color: #64748b;"><?= t('Assignee') ?></td>
        <td style="padding: 3px 14px; color: #0f172a;">
            <?php if (! empty($task['assignee_name']) || ! empty($task['assignee_username'])): ?>
                <?= $this->text->e($task['assignee_name'] ?: $task['assignee_username']) ?>
            <?php else: ?>
                <span style="color: #b45309;"><?= t('Nobody') ?></span>
            <?php endif ?>
        </td>
    </tr>
    <?php if (! empty($task['priority'])): ?>
        <tr>
            <td style="padding: 3px 14px; color: #64748b;"><?= t('Priority') ?></td>
            <td style="padding: 3px 14px; color: #0f172a;">P<?= (int) $task['priority'] ?></td>
        </tr>
    <?php endif ?>
    <tr>
        <td style="padding: 3px 14px; color: #64748b;"><?= t('Start Date') ?></td>
        <td style="padding: 3px 14px; color: #0f172a;"><?= ! empty($task['date_started']) ? date('D, j M Y', (int) $task['date_started']) : '&mdash;' ?></td>
    </tr>
    <tr>
        <td style="padding: 3px 14px 10px; color: #64748b;"><?= t('Due Date') ?></td>
        <td style="padding: 3px 14px 10px; color: #0f172a;"><?= ! empty($task['date_due']) ? date('D, j M Y', (int) $task['date_due']) : '&mdash;' ?></td>
    </tr>
</table>

<?php /* ---- description, trimmed --------------------------------------- */ ?>
<?php if (! empty($task['description'])): ?>
    <?php
        $description = trim((string) $task['description']);
        $truncated   = mb_strlen($description) > 400;
        $description = $truncated ? mb_substr($description, 0, 400).'...' : $description;
    ?>
    <p style="margin: 0 0 4px; font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px;"><?= t('Description') ?></p>
    <div style="font-size: 13.5px; color: #334155; line-height: 1.6; margin-bottom: 18px; white-space: pre-wrap;"><?= $this->text->e($description) ?></div>
<?php endif ?>

<?php /* ---- the last couple of comments -------------------------------- */ ?>
<?php if (! empty($comments)): ?>
    <p style="margin: 0 0 6px; font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px;"><?= t('Recent comments') ?></p>
    <?php foreach ($comments as $c): ?>
        <div style="border-left: 2px solid #e2e8f0; padding: 2px 0 2px 10px; margin-bottom: 10px;">
            <div style="font-size: 11.5px; color: #94a3b8;">
                <?= $this->text->e(! empty($c['name']) ? $c['name'] : (isset($c['username']) ? $c['username'] : '')) ?>
                <?php if (! empty($c['date_creation'])): ?>
                    &middot; <?= date('j M, H:i', (int) $c['date_creation']) ?>
                <?php endif ?>
            </div>
            <div style="font-size: 13px; color: #475569; line-height: 1.55; white-space: pre-wrap;"><?= $this->text->e(mb_strimwidth((string) $c['comment'], 0, 240, '...')) ?></div>
        </div>
    <?php endforeach ?>
<?php endif ?>

<?php if ($hasUrl): ?>
    <a href="<?= $this->url->base() ?>?controller=TaskGridController&amp;action=show&amp;plugin=TaskManager&amp;project_id=<?= (int) $task['project_id'] ?>&amp;task_id=<?= (int) $task['id'] ?>"
       style="display: inline-block; background: #0f172a; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; padding: 11px 20px; border-radius: 8px; margin-top: 4px;">
        <?= t('Open this task') ?>
    </a>
<?php else: ?>
    <p style="margin: 0; font-size: 13px; color: #b45309;">
        <?= t('No portal address is configured yet. An administrator can set it under Settings, Application, Application URL.') ?>
    </p>
<?php endif ?>
<?php
$content = ob_get_clean();
echo $this->render('TaskManager:notification/_shell', array(
    'content' => $content,
    'reason'  => t('You are receiving this because you follow this task.'),
));
