<style>
    html, body {
        margin: 0 !important;
        padding: 0 !important;
        height: 100% !important;
        width: 100% !important;
        background: #0f172a !important;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
        overflow-x: hidden !important;
    }
    .sb-login-viewport {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100vw;
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: radial-gradient(circle at 50% 20%, #1e1b4b 0%, #0f172a 70%, #080c14 100%);
        overflow-y: auto;
        padding: 24px;
        box-sizing: border-box;
        z-index: 99999;
    }
    .sb-login-card {
        width: 100%;
        max-width: 440px;
        margin: auto;
        background: #ffffff;
        border-radius: 24px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.12);
        padding: 44px 38px 36px;
        box-sizing: border-box;
        position: relative;
        overflow: hidden;
        animation: sbCardPop 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes sbCardPop {
        from { opacity: 0; transform: scale(0.96) translateY(10px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .sb-login-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 6px;
        background: linear-gradient(90deg, #4f46e5 0%, #6366f1 50%, #06b6d4 100%);
    }
    .sb-logo-container {
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 20px;
    }
    .sb-logo-badge {
        width: 76px;
        height: 76px;
        border-radius: 20px;
        background: #ffffff;
        border: 2px solid #e0e7ff;
        box-shadow: 0 8px 24px -4px rgba(99, 102, 241, 0.28);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 10px;
        box-sizing: border-box;
        transition: transform 0.25s ease;
    }
    .sb-logo-badge:hover {
        transform: scale(1.05);
    }
    .sb-logo-img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    .sb-brand-header {
        text-align: center;
        margin-bottom: 28px;
    }
    .sb-brand-title {
        font-size: 1.55rem;
        font-weight: 850;
        color: #0f172a;
        margin: 0 0 6px;
        letter-spacing: -0.02em;
    }
    .sb-brand-subtitle {
        font-size: 0.86rem;
        color: #64748b;
        margin: 0;
        font-weight: 500;
        line-height: 1.4;
    }
    .sb-form-group {
        margin-bottom: 18px;
    }
    .sb-form-label {
        display: block;
        font-size: 0.82rem;
        font-weight: 700;
        color: #334155;
        margin-bottom: 7px;
    }
    
    /* Flex Container for Guaranteed Alignment */
    .sb-input-pill {
        display: flex !important;
        align-items: center !important;
        background: #f8fafc !important;
        border: 1.5px solid #cbd5e1 !important;
        border-radius: 12px !important;
        padding: 0 !important;
        box-sizing: border-box !important;
        transition: all 0.2s ease !important;
        overflow: hidden !important;
        width: 100% !important;
    }
    .sb-input-pill:focus-within {
        border-color: #4f46e5 !important;
        background: #ffffff !important;
        box-shadow: 0 0 0 3.5px rgba(79, 70, 229, 0.15) !important;
    }
    .sb-input-pill-icon {
        width: 42px !important;
        min-width: 42px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        color: #94a3b8 !important;
        font-size: 1rem !important;
        transition: color 0.2s ease !important;
        flex-shrink: 0 !important;
    }
    .sb-input-pill:focus-within .sb-input-pill-icon {
        color: #4f46e5 !important;
    }
    .sb-input-pill-field {
        flex: 1 !important;
        width: calc(100% - 42px) !important;
        border: none !important;
        outline: none !important;
        background: transparent !important;
        box-shadow: none !important;
        padding: 12px 14px 12px 0 !important;
        margin: 0 !important;
        font-size: 0.94rem !important;
        color: #0f172a !important;
        font-family: inherit !important;
        box-sizing: border-box !important;
    }
    .sb-input-pill-field:focus {
        border: none !important;
        outline: none !important;
        box-shadow: none !important;
        background: transparent !important;
    }
    
    .sb-options-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
        font-size: 0.84rem;
    }
    .sb-remember-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #475569;
        cursor: pointer;
        user-select: none;
        margin: 0;
        font-weight: 500;
    }
    .sb-remember-label input[type="checkbox"] {
        accent-color: #4f46e5;
        width: 16px;
        height: 16px;
        cursor: pointer;
    }
    .sb-forgot-link {
        color: #4f46e5;
        font-weight: 600;
        text-decoration: none;
        transition: color 0.15s ease;
    }
    .sb-forgot-link:hover {
        color: #3730a3;
        text-decoration: underline;
    }
    .sb-submit-btn {
        width: 100%;
        padding: 13px;
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
        color: #ffffff;
        border: none;
        border-radius: 12px;
        font-size: 0.96rem;
        font-weight: 750;
        cursor: pointer;
        transition: all 0.2s ease;
        box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .sb-submit-btn:hover {
        transform: translateY(-1.5px);
        box-shadow: 0 8px 20px rgba(79, 70, 229, 0.45);
        background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
    }
    .sb-submit-btn:active {
        transform: translateY(0);
    }
    .sb-security-badge {
        margin-top: 26px;
        text-align: center;
        font-size: 0.78rem;
        color: #94a3b8;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        font-weight: 500;
    }
    .alert-error {
        background: #fef2f2;
        border: 1.5px solid #fecaca;
        color: #991b1b;
        padding: 12px 14px;
        border-radius: 12px;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 22px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
</style>

<div class="sb-login-viewport">
    <div class="sb-login-card">
        
        <!-- Centered Logo Badge -->
        <div class="sb-logo-container">
            <div class="sb-logo-badge">
                <img src="<?= $this->url->dir() ?>assets/img/superbee_logo.png?v=2" alt="SUPERBEE PMO" class="sb-logo-img">
            </div>
        </div>

        <!-- Brand Title & Tagline -->
        <div class="sb-brand-header">
            <h1 class="sb-brand-title">SUPERBEE PMO</h1>
            <p class="sb-brand-subtitle"><?= t('Enterprise Project Management & Aerospace Operations') ?></p>
        </div>

        <?= $this->hook->render('template:auth:login-form:before') ?>

        <?php if (isset($errors['login'])): ?>
            <div class="alert alert-error">
                <i class="fa fa-exclamation-circle" style="color: #ef4444; font-size: 1.1rem;"></i>
                <span><?= $this->text->e($errors['login']) ?></span>
            </div>
        <?php endif ?>

        <?php if (! HIDE_LOGIN_FORM): ?>
        <form method="post" action="<?= $this->url->href('AuthController', 'check') ?>" style="margin: 0;">
            <?= $this->form->csrf() ?>

            <!-- Username Field -->
            <div class="sb-form-group">
                <label for="form-username" class="sb-form-label">
                    <?= t('Username') ?>
                </label>
                <div class="sb-input-pill">
                    <span class="sb-input-pill-icon">
                        <i class="fa fa-user"></i>
                    </span>
                    <input type="text" name="username" id="form-username" value="<?= isset($values['username']) ? $this->text->e($values['username']) : '' ?>" class="sb-input-pill-field" placeholder="<?= t('Enter your username') ?>" autofocus required autocomplete="username">
                </div>
            </div>

            <!-- Password Field -->
            <div class="sb-form-group">
                <label for="form-password" class="sb-form-label">
                    <?= t('Password') ?>
                </label>
                <div class="sb-input-pill">
                    <span class="sb-input-pill-icon">
                        <i class="fa fa-lock"></i>
                    </span>
                    <input type="password" name="password" id="form-password" class="sb-input-pill-field" placeholder="<?= t('Enter your password') ?>" required autocomplete="current-password">
                </div>
            </div>

            <!-- Captcha (if locked) -->
            <?php if (isset($captcha) && $captcha): ?>
                <div class="sb-form-group" style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 14px; margin-bottom: 18px;">
                    <label for="form-captcha" class="sb-form-label"><?= t('Security Verification') ?></label>
                    <div style="margin-bottom: 10px; text-align: center;">
                        <img src="<?= $this->url->href('CaptchaController', 'image') ?>" alt="Captcha" style="border-radius: 8px; border: 1px solid #cbd5e1;">
                    </div>
                    <div class="sb-input-pill">
                        <input type="text" name="captcha" id="form-captcha" class="sb-input-pill-field" placeholder="<?= t('Enter security text') ?>" required style="padding-left: 14px !important;">
                    </div>
                </div>
            <?php endif ?>

            <!-- Options Row (Remember Me & Forgot Password) -->
            <div class="sb-options-row">
                <?php if (REMEMBER_ME_AUTH): ?>
                    <label class="sb-remember-label">
                        <input type="checkbox" name="remember_me" value="1" checked>
                        <span><?= t('Remember me') ?></span>
                    </label>
                <?php endif ?>

                <?php if ($this->app->config('password_reset') == 1): ?>
                    <a href="<?= $this->url->href('PasswordResetController', 'create') ?>" class="sb-forgot-link">
                        <?= t('Forgot password?') ?>
                    </a>
                <?php endif ?>
            </div>

            <!-- Sign In Action Button -->
            <button type="submit" class="sb-submit-btn">
                <span><?= t('Sign In to Workspace') ?></span>
                <i class="fa fa-arrow-right" style="font-size: 0.88rem;"></i>
            </button>
        </form>
        <?php endif ?>

        <?= $this->hook->render('template:auth:login-form:after') ?>

        <!-- Footer Security Trust Badge -->
        <div class="sb-security-badge">
            <i class="fa fa-shield" style="color: #6366f1; font-size: 0.95rem;"></i>
            <span><?= t('SUPERBEE Aeronautics & Defence Internal Workspace') ?></span>
        </div>

    </div>
</div>
