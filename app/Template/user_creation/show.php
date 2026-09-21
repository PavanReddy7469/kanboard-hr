<?php
/* Onboarding default.
 *
 * When config.php defines DEFAULT_USER_PASSWORD, the two password boxes open
 * already filled with it, so onboarding is: name, email, role, Create. The
 * admin can still type something else over it.
 *
 * The value itself lives only in config.php, which git ignores and does not
 * track - it is deliberately not written into any file under version control,
 * and not into this template. If the constant is absent the form behaves
 * exactly as before and the field is required.
 *
 * Worth knowing: a default shared by everyone is only as good as how quickly
 * it gets replaced. Kanboard has no forced change at first login, so until
 * one is added, every account keeps this password until its owner changes it.
 */
$sb_values = $values;

if (defined('DEFAULT_USER_PASSWORD') && DEFAULT_USER_PASSWORD !== ''
    && empty($sb_values['password']) && empty($sb_values['confirmation'])) {
    $sb_values['password']     = DEFAULT_USER_PASSWORD;
    $sb_values['confirmation'] = DEFAULT_USER_PASSWORD;
}
?>
<div class="page-header" style="margin-bottom: 20px;">
    <h2 style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
        <span style="width: 32px; height: 32px; border-radius: 8px; background: #e0e7ff; color: #4338ca; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem;">
            <i class="fa fa-user-plus"></i>
        </span>
        <?= t('New User') ?>
    </h2>
    <p style="font-size: 0.84rem; color: #64748b; margin: 4px 0 0;">
        <?= t('Create a new team member account with role permissions and initial password.') ?>
    </p>
</div>

<form method="post" action="<?= $this->url->href('UserCreationController', 'save') ?>" style="margin: 0;">
    <?= $this->form->csrf() ?>
    
    <!-- Hidden sensible defaults for removed bloat -->
    <input type="hidden" name="is_ldap_user" value="0">
    <input type="hidden" name="disable_login_form" value="0">
    <input type="hidden" name="timezone" value="<?= isset($values['timezone']) ? $this->text->e($values['timezone']) : '' ?>">
    <input type="hidden" name="language" value="<?= isset($values['language']) ? $this->text->e($values['language']) : '' ?>">
    <input type="hidden" name="filter" value="">
    <input type="hidden" name="notifications_enabled" value="0">

    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
        
        <!-- Row 1: Name & Username -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
            <div>
                <label for="form-name" style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    <?= t('Full Name') ?>
                </label>
                <?= $this->form->text('name', $values, $errors, array('placeholder="e.g. Jane Doe"', 'style' => 'width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; outline: none; background: #ffffff;')) ?>
            </div>
            <div>
                <label for="form-username" style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    <?= t('Username') ?> <span style="color: #ef4444;">*</span>
                </label>
                <?= $this->form->text('username', $values, $errors, array('autofocus', 'required', 'placeholder="e.g. janedoe"', 'style' => 'width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; outline: none; background: #ffffff;')) ?>
            </div>
        </div>

                <!-- Employee ID: shown as NAME (EMP ID) wherever a person is picked -->
        <div style="margin-bottom: 16px;">
            <div style="max-width: calc(50% - 8px);">
                <label for="form-employee_id" style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    <?= t('Employee ID') ?>
                </label>
                <?= $this->form->text('employee_id', $values, $errors, array('placeholder="e.g. SB-1042"', 'maxlength="50"', 'style' => 'width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; outline: none; background: #ffffff;')) ?>
            </div>
        </div>

        <!-- Row 2: Email & Role -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
            <div>
                <label for="form-email" style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    <?= t('Email Address') ?>
                </label>
                <?= $this->form->email('email', $values, $errors, array('placeholder="jane.doe@example.com"', 'style' => 'width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; outline: none; background: #ffffff;')) ?>
            </div>
            <div>
                <label for="form-role" style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    <?= t('Role') ?> <span style="color: #ef4444;">*</span>
                </label>
                <?= $this->form->select('role', $roles, $values, $errors, array('style' => 'width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; outline: none; background: #ffffff;')) ?>
            </div>
        </div>

        <!-- Row 3: Password & Confirm Password -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
            <div>
                <label for="form-password" style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    <?= t('Password') ?> <span style="color: #ef4444;">*</span>
                </label>
                <?= $this->form->password('password', $sb_values, $errors, array('placeholder="••••••••"', 'style' => 'width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; outline: none; background: #ffffff;')) ?>
            </div>
            <div>
                <label for="form-confirmation" style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    <?= t('Confirm Password') ?> <span style="color: #ef4444;">*</span>
                </label>
                <?= $this->form->password('confirmation', $sb_values, $errors, array('placeholder="••••••••"', 'style' => 'width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; outline: none; background: #ffffff;')) ?>
            </div>
        </div>

        <?php if (defined('DEFAULT_USER_PASSWORD') && DEFAULT_USER_PASSWORD !== ''): ?>
            <p style="font-size: 0.78rem; color: #92400e; background: #fef3c7; border: 1px solid #fde68a; border-radius: 8px; padding: 8px 12px; margin: -6px 0 16px;">
                <i class="fa fa-info-circle"></i>
                <?= t('Filled in with the onboarding default. Ask the new user to change it the first time they sign in - everyone issued this password shares it until they do.') ?>
            </p>
        <?php endif ?>

        <!-- Row 4: Initial Project Assignment -->
        <?php if (! empty($projects)): ?>
        <div>
            <label for="form-project_id" style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
                <?= t('Assign to Project') ?> <span style="font-weight: 500; color: #94a3b8; font-size: 0.78rem;">(Optional)</span>
            </label>
            <?= $this->form->select('project_id', $projects, $values, $errors, array('style' => 'width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; outline: none; background: #ffffff;')) ?>
        </div>
        <?php endif ?>

    </div>

    <div style="display: flex; justify-content: flex-end; gap: 10px; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 16px;">
        <button type="button" class="btn" data-sb-modal-close style="background: #f8fafc; color: #475569; border: 1.5px solid #cbd5e1; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer;">
            <?= t('Cancel') ?>
        </button>
        <button type="submit" class="btn btn-blue" style="background: #4f46e5; color: #ffffff; border: none; padding: 8px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 6px rgba(79, 70, 229, 0.3);">
            <i class="fa fa-check"></i> <?= t('Create User') ?>
        </button>
    </div>
</form>
