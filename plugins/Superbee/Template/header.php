<?php $_nav = $this->shell->getVisibleNavigation() ?>

<a class="sb-skip" href="#main"><?= t('Skip to content') ?></a>

<nav id="sb-rail" class="sb-rail" aria-label="<?= t('Main navigation') ?>">
    <a class="sb-brand" href="<?= $this->url->href('DashboardController', 'show') ?>">
        <span class="sb-brand-mark">
            <img src="<?= $this->url->dir() ?>plugins/Superbee/Assets/images/superbee_logo.png" alt="SUPERBEE">
        </span>
        <span class="sb-brand-text">
            <span class="sb-brand-name" style="font-size: 14px; font-weight: 800; letter-spacing: .05em; color: #ffffff;">SUPERBEE PMO</span>
        </span>
    </a>

    <div class="sb-rail-scroll">
        <?php foreach ($_nav as $_section => $_items): ?>
            <div class="sb-rail-label"><?= $this->text->e($_section) ?></div>
            <ul class="sb-rail-list">
                <?php foreach ($_items as $_item): ?>
                    <li>
                        <?php if (! empty($_item['soon'])): ?>
                            <span class="sb-rail-item is-soon" aria-disabled="true">
                                <?= $this->shell->icon($_item['icon']) ?>
                                <span class="sb-rail-text"><?= $this->text->e($_item['label']) ?></span>
                                <span class="sb-soon"><?= t('Soon') ?></span>
                            </span>
                        <?php else: ?>
                            <a class="sb-rail-item <?= $this->shell->isActive($_item) ? 'is-active' : '' ?>" href="<?= $this->shell->url($_item) ?>">
                                <?= $this->shell->icon($_item['icon']) ?>
                                <span class="sb-rail-text"><?= $this->text->e($_item['label']) ?></span>
                            </a>
                        <?php endif ?>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endforeach ?>
    </div>

    <div class="sb-rail-user">
        <span class="sb-avatar"><?= $this->text->e($this->shell->getUserInitials()) ?></span>
        <span class="sb-rail-user-text">
            <span class="sb-rail-user-name"><?= $this->text->e($this->user->getFullname()) ?></span>
            <span class="sb-rail-user-role"><?= $this->text->e($this->user->getRoleName()) ?></span>
        </span>
    </div>
</nav>

<header id="sb-topbar" class="sb-topbar">
    <div class="sb-crumbs">
        <?php if (! empty($project)): ?>
            <a class="sb-crumb-root" href="<?= $this->url->href('ProjectGridController', 'show', array('plugin' => 'TaskManager')) ?>"><?= t('Projects') ?></a>
            <svg class="sb-crumb-sep" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>
            <span class="sb-project-chip">
                <span class="sb-project-dot <?= empty($project['is_active']) ? 'is-off' : '' ?>"></span>
                <span class="sb-project-name"><?= $this->text->e($project['name']) ?></span>
            </span>
        <?php else: ?>
            <span class="sb-page-title"><?= $this->text->e($this->shell->getPageTitle(isset($title) ? $title : '')) ?></span>
        <?php endif ?>
    </div>

    <?php /* The "Display another project" selector is deliberately absent: it
             repeated the rail's Projects entry on every single page. */ ?>

    <div class="sb-topbar-actions">
        <?= $this->render('header/creation_dropdown') ?>
        <?= $this->render('header/user_notifications') ?>
        <?= $this->render('header/user_dropdown') ?>
    </div>
</header>

<?php if (! empty($description)): ?>
    <?= $this->app->tooltipHTML($description) ?>
<?php endif ?>
