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

    <p style="color: #94a3b8; font-size: 11.5px; line-height: 1.6; margin: 16px 2px 0;">
        <?= t('Sent by the Superbee PMO portal. You are receiving this because this work is assigned to you.') ?><br>
        <?= t('To stop these emails, open your profile and turn off notifications.') ?>
    </p>
</div>
</body>
</html>
