<div class="page-header" style="margin-bottom: 20px;">
    <h2 style="font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px;">
        <span style="width: 32px; height: 32px; border-radius: 8px; background: #e0e7ff; color: #4338ca; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem;">
            <i class="fa fa-pencil-square-o"></i>
        </span>
        <?= t('Edit User Profile') ?>
    </h2>
    <p style="font-size: 0.84rem; color: #64748b; margin: 4px 0 0;">
        <?= t('Update member profile details and role permissions.') ?>
    </p>
</div>

<form method="post" action="<?= $this->url->href('UserModificationController', 'save', array('user_id' => $user['id'])) ?>" style="margin: 0;">
    <?= $this->form->csrf() ?>
    <?= $this->form->hidden('id', $values) ?>

    <!-- Hidden sensible defaults for removed bloat -->
    <input type="hidden" name="theme" value="<?= isset($values['theme']) ? $this->text->e($values['theme']) : '' ?>">
    <input type="hidden" name="timezone" value="<?= isset($values['timezone']) ? $this->text->e($values['timezone']) : '' ?>">
    <input type="hidden" name="language" value="<?= isset($values['language']) ? $this->text->e($values['language']) : '' ?>">
    <input type="hidden" name="filter" value="<?= isset($values['filter']) ? $this->text->e($values['filter']) : '' ?>">

    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 20px;">
        
        <!-- Row 1: Full Name & Username -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
            <div>
                <label for="form-name" style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    <?= t('Full Name') ?>
                </label>
                <?= $this->form->text('name', $values, $errors, array('style' => 'width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; outline: none; background: #ffffff;')) ?>
            </div>
            <div>
                <label for="form-username" style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    <?= t('Username') ?> <span style="color: #ef4444;">*</span>
                </label>
                <?= $this->form->text('username', $values, $errors, array('required', 'maxlength="191"', isset($user['is_ldap_user']) && $user['is_ldap_user'] == 1 ? 'readonly' : '', 'style' => 'width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; outline: none; background: #ffffff;')) ?>
            </div>
        </div>

        <!-- Row 2: Email & Role -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <label for="form-email" style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    <?= t('Email Address') ?>
                </label>
                <?= $this->form->email('email', $values, $errors, array('style' => 'width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; outline: none; background: #ffffff;')) ?>
            </div>

            <?php if ($this->user->isAdmin()): ?>
            <div>
                <label for="form-role" style="display: block; font-size: 0.84rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
                    <?= t('Role') ?> <span style="color: #ef4444;">*</span>
                </label>
                <?= $this->form->select('role', $roles, $values, $errors, array('style' => 'width: 100%; padding: 9px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 0.88rem; outline: none; background: #ffffff;')) ?>
            </div>
            <?php endif ?>
        </div>

    </div>

    <div style="display: flex; justify-content: flex-end; gap: 10px; align-items: center; border-top: 1px solid #e2e8f0; padding-top: 16px;">
        <button type="button" class="btn" data-sb-modal-close style="background: #f8fafc; color: #475569; border: 1.5px solid #cbd5e1; padding: 8px 16px; border-radius: 8px; font-weight: 600; cursor: pointer;">
            <?= t('Cancel') ?>
        </button>
        <button type="submit" class="btn btn-blue" style="background: #4f46e5; color: #ffffff; border: none; padding: 8px 20px; border-radius: 8px; font-weight: 700; cursor: pointer; box-shadow: 0 2px 6px rgba(79, 70, 229, 0.3);">
            <i class="fa fa-check"></i> <?= t('Save Changes') ?>
        </button>
    </div>
</form>
