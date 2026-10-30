<?php
$headline = $days <= 0 ? t('This project ends today') : ($days === 1 ? t('This project ends tomorrow') : t('This project ends in %d days', $days));
$hasUrl = $this->app->config('application_url') !== '';

ob_start();
?>
<p style="margin: 0 0 4px; font-size: 13px; color: #7c3aed; font-weight: 700; text-transform: uppercase; letter-spacing: .5px;">
    <?= $this->text->e($headline) ?>
</p>

<h1 style="margin: 0 0 14px; font-size: 19px; line-height: 1.35; font-weight: 700; color: #0f172a;">
    <?= $this->text->e($project['name']) ?>
</h1>

<p style="margin: 0 0 18px; font-size: 13.5px; line-height: 1.6; color: #475569;">
    <?= t('Scheduled end date: %s', $this->text->e($project['end_date'])) ?>.
    <?= t('You are receiving this because you manage this project.') ?>
</p>

<?php if ($hasUrl): ?>
    <a href="<?= $this->url->base() ?>?controller=TaskGridController&amp;action=show&amp;plugin=TaskManager&amp;project_id=<?= (int) $project['id'] ?>"
       style="display: inline-block; background: #0f172a; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; padding: 11px 20px; border-radius: 8px;">
        <?= t('Review open work') ?>
    </a>
<?php endif ?>
<?php
$content = ob_get_clean();
echo $this->render('TaskManager:notification/_shell', array('content' => $content));
