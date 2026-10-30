<?php
/* Carries no password on purpose: accounts are created with a shared
   starting password and must_change_password is set, so the person chooses
   their own on first sign-in. Mailing the shared one would hand it to
   anyone who later reads this mailbox. */
$hasUrl = $this->app->config('application_url') !== '';

ob_start();
?>
<h1 style="margin: 0 0 14px; font-size: 19px; font-weight: 700; color: #0f172a;">
    <?= t('Welcome, %s', $this->text->e($user['name'] !== '' ? $user['name'] : $user['username'])) ?>
</h1>

<p style="margin: 0 0 18px; font-size: 14px; line-height: 1.6; color: #475569;">
    <?= t('An account has been created for you on the Superbee PMO portal, where your projects, tasks and completion reports are tracked.') ?>
</p>

<table cellpadding="0" cellspacing="0" style="width: 100%; font-size: 13.5px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 18px;">
    <tr>
        <td style="padding: 11px 14px 4px; color: #64748b; width: 110px;"><?= t('Username') ?></td>
        <td style="padding: 11px 14px 4px; color: #0f172a; font-weight: 700;"><?= $this->text->e($user['username']) ?></td>
    </tr>
    <tr>
        <td style="padding: 4px 14px 11px; color: #64748b;"><?= t('Email') ?></td>
        <td style="padding: 4px 14px 11px; color: #0f172a;"><?= $this->text->e($user['email']) ?></td>
    </tr>
</table>

<p style="margin: 0 0 18px; font-size: 13.5px; line-height: 1.6; color: #475569;">
    <strong style="color: #0f172a;"><?= t('Your password') ?>:</strong>
    <?= t('Ask your administrator for the starting password. You will be asked to replace it with one of your own the first time you sign in, and nobody else will know it after that.') ?>
</p>

<?php if ($hasUrl): ?>
    <a href="<?= $this->url->base() ?>"
       style="display: inline-block; background: #0f172a; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; padding: 11px 20px; border-radius: 8px;">
        <?= t('Sign in to the portal') ?>
    </a>
<?php else: ?>
    <p style="margin: 0; font-size: 13px; color: #b45309;">
        <?= t('No portal address is configured yet. An administrator can set it under Settings, Application, Application URL.') ?>
    </p>
<?php endif ?>
<?php
$content = ob_get_clean();
echo $this->render('TaskManager:notification/_shell', array('content' => $content));
