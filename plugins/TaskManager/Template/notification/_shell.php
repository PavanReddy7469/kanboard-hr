<?php
/* One frame for every mail this plugin sends.
 *
 * All styling is inline. Gmail, Outlook and every webmail strip <style>
 * blocks from the head, so a stylesheet here would be thrown away and the
 * mail would arrive as unstyled black Times New Roman. */
?>
<html>
<body style="margin: 0; padding: 0; background: #f1f5f9;">
<div style="font-family: -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; max-width: 560px; margin: 0 auto; padding: 24px 16px;">

    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;">
        <div style="background: #0f172a; padding: 16px 22px;">
            <span style="color: #ffffff; font-size: 15px; font-weight: 700; letter-spacing: .3px;">SUPERBEE</span>
            <span style="color: #94a3b8; font-size: 15px; font-weight: 400;"> &nbsp;PMO</span>
        </div>

        <div style="padding: 22px;">
            <?= $content ?>
        </div>
    </div>

    <?php
        /* Why this particular person got this particular message. One footer
           was shared by all four templates, which put "because this work is
           assigned to you" on the welcome email - sent to somebody who has
           just been given an account and has no work at all. */
        $reason = isset($reason) ? $reason : t('You are receiving this because this work is assigned to you.');

        /* The opt-out is only true where it is true. The welcome email is
           transactional and is sent whatever the notification setting says,
           so offering a switch that would not stop it is a lie. */
        $optout = isset($optout) ? (bool) $optout : true;
    ?>
    <p style="color: #94a3b8; font-size: 11.5px; line-height: 1.6; margin: 16px 2px 0;">
        <?= t('Sent by the Superbee PMO portal.') ?> <?= $this->text->e($reason) ?>
        <?php if ($optout): ?>
            <br><?= t('To stop these emails, open your profile and turn off notifications.') ?>
        <?php endif ?>
    </p>
</div>
</body>
</html>
