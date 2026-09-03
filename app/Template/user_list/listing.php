<div class="sb-people-workspace" style="max-width: 1200px; margin: 0 auto; padding-bottom: 40px;">
    
    <!-- Top Header & Action Controls -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px 24px; margin-bottom: 24px; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px;">
        
        <div style="display: flex; align-items: center; gap: 14px;">
            <span style="width: 44px; height: 44px; border-radius: 12px; background: #e0e7ff; color: #4338ca; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                <i class="fa fa-users"></i>
            </span>
            <div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.01em;">
                        <?= t('People & Roles') ?>
                    </h2>
                    <span style="background: #f1f5f9; color: #475569; font-size: 0.8rem; font-weight: 700; padding: 2px 10px; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <?= $paginator->getTotal() ?> <?= $paginator->getTotal() === 1 ? t('member') : t('members') ?>
                    </span>
                </div>
                <p style="font-size: 0.84rem; color: #64748b; margin: 3px 0 0;">
                    <?= t('Manage team members, roles, system permissions, and account security.') ?>
                </p>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <?= $this->modal->medium('question-circle', t('Roles Guide'), 'UserListController', 'rolesHelp', array(), 'btn', array('style' => 'background: #eef2ff; color: #4338ca; border: 1.5px solid #c7d2fe; font-weight: 700; border-radius: 8px; padding: 7px 12px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;')) ?>

            <?php if ($this->user->hasAccess('UserCreationController', 'show')): ?>
                <?= $this->modal->medium('plus', t('New User'), 'UserCreationController', 'show', array(), 'btn btn-blue', array('style' => 'background: #4f46e5; color: #ffffff; border: none; font-weight: 700; border-radius: 8px; padding: 8px 14px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;')) ?>
                <?= $this->modal->medium('paper-plane', t('Invite People'), 'UserInviteController', 'show', array(), 'btn', array('style' => 'background: #f8fafc; color: #334155; border: 1.5px solid #cbd5e1; font-weight: 600; border-radius: 8px; padding: 7px 12px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;')) ?>
                <?= $this->modal->medium('upload', t('Import'), 'UserImportController', 'show', array(), 'btn', array('style' => 'background: #f8fafc; color: #334155; border: 1.5px solid #cbd5e1; font-weight: 600; border-radius: 8px; padding: 7px 12px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;')) ?>
                <a href="<?= $this->url->href('GroupListController', 'index') ?>" class="btn" style="background: #f8fafc; color: #334155; border: 1.5px solid #cbd5e1; font-weight: 600; border-radius: 8px; padding: 7px 12px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                    <i class="fa fa-users"></i> <?= t('Groups') ?>
                </a>
            <?php endif ?>
        </div>

    </div>

    <!-- Search & Filter Controls -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03); display: flex; align-items: center; justify-content: space-between;">
        <form method="get" action="<?= $this->url->dir() ?>" class="search" style="margin: 0; width: 100%; max-width: 450px;">
            <?= $this->form->hidden('controller', array('controller' => 'UserListController')) ?>
            <?= $this->form->hidden('action', array('action' => 'search')) ?>
            
            <div style="display: flex; align-items: center; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 6px 12px;">
                <i class="fa fa-search" style="color: #6366f1; margin-right: 10px; font-size: 0.9rem;"></i>
                <?= $this->form->text('search', $values, array(), array('placeholder="'.t('Search users by name, username, or role...').'"', 'aria-label="'.t('Search').'"', 'style' => 'border: none !important; outline: none !important; background: transparent !important; width: 100%; font-size: 0.88rem; color: #0f172a; padding: 2px 0; box-shadow: none !important;')) ?>
                <?php if (! empty($values['search'])): ?>
                    <a href="<?= $this->url->href('UserListController', 'show') ?>" style="color: #94a3b8; text-decoration: none; padding: 2px 6px;">
                        <i class="fa fa-times-circle"></i>
                    </a>
                <?php endif ?>
            </div>
        </form>

        <div style="font-size: 0.82rem; color: #64748b; font-weight: 600;">
            <?= t('Showing %d of %d active team members', count($paginator->getCollection()), $paginator->getTotal()) ?>
        </div>
    </div>

    <!-- Users Table Container -->
    <?php if ($paginator->isEmpty()): ?>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 40px 24px; text-align: center;">
            <i class="fa fa-user-times" style="font-size: 2.2rem; color: #cbd5e1; display: block; margin-bottom: 12px;"></i>
            <h4 style="margin: 0 0 6px; font-size: 1.1rem; font-weight: 700; color: #1e293b;"><?= t('No team members found') ?></h4>
            <p style="color: #64748b; font-size: 0.88rem; margin: 0;"><?= t('Try searching for a different keyword or create a new user account.') ?></p>
        </div>
    <?php else: ?>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);">
            
            <!-- Table Header -->
            <div style="display: grid; grid-template-columns: 2.5fr 1.5fr 1.5fr 1.2fr 1.5fr; padding: 12px 20px; background: #f8fafc; border-bottom: 1.5px solid #e2e8f0; font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em;">
                <div><?= t('Member') ?></div>
                <div><?= t('Role') ?></div>
                <div><?= t('Status') ?></div>
                <div><?= t('2FA Security') ?></div>
                <div style="text-align: right;"><?= t('Actions') ?></div>
            </div>

            <!-- Table Rows -->
            <?php foreach ($paginator->getCollection() as $u): ?>
                <?php
                    $initial = strtoupper(substr(! empty($u['name']) ? $u['name'] : $u['username'], 0, 1));
                    $roleLabel = $this->user->getRoleName($u['role']);
                    $isAdmin = ($u['role'] === 'app-admin');
                    $isManager = ($u['role'] === 'app-manager');
                    $isActive = ($u['is_active'] == 1);
                    $has2fa = ($u['twofactor_activated'] == 1);
                ?>
                <div class="sb-hover-row" style="display: grid; grid-template-columns: 2.5fr 1.5fr 1.5fr 1.2fr 1.5fr; padding: 16px 20px; border-bottom: 1px solid #f1f5f9; align-items: center; transition: background 0.15s;">
                    
                    <!-- Member Name & Avatar -->
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="width: 38px; height: 38px; border-radius: 50%; background: <?= $isAdmin ? 'linear-gradient(135deg, #4f46e5, #6366f1)' : ($isManager ? 'linear-gradient(135deg, #0284c7, #38bdf8)' : 'linear-gradient(135deg, #64748b, #94a3b8)') ?>; color: white; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                            <?= $initial ?>
                        </span>
                        <div>
                            <a href="<?= $this->url->href('UserViewController', 'show', array('user_id' => $u['id'])) ?>" style="font-size: 0.92rem; font-weight: 700; color: #0f172a; text-decoration: none; display: block;">
                                <?= $this->text->e(! empty($u['name']) ? $u['name'] : $u['username']) ?>
                            </a>
                            <span style="font-size: 0.78rem; color: #64748b; font-family: monospace;">
                                @<?= $this->text->e($u['username']) ?>
                                <?php if (! empty($u['email'])): ?>
                                    &bull; <?= $this->text->e($u['email']) ?>
                                <?php endif ?>
                            </span>
                        </div>
                    </div>

                    <!-- Role Badge -->
                    <div>
                        <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 0.75rem; font-weight: 700; padding: 3px 10px; border-radius: 12px; background: <?= $isAdmin ? '#e0e7ff' : ($isManager ? '#e0f2fe' : '#f1f5f9') ?>; color: <?= $isAdmin ? '#4338ca' : ($isManager ? '#0369a1' : '#475569') ?>;">
                            <i class="fa <?= $isAdmin ? 'fa-shield' : ($isManager ? 'fa-briefcase' : 'fa-user') ?>" style="font-size: 0.7rem;"></i>
                            <?= $this->text->e($roleLabel) ?>
                        </span>
                    </div>

                    <!-- Status -->
                    <div>
                        <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.78rem; font-weight: 700; color: <?= $isActive ? '#065f46' : '#991b1b' ?>; background: <?= $isActive ? '#d1fae5' : '#fee2e2' ?>; padding: 3px 10px; border-radius: 12px;">
                            <span style="width: 7px; height: 7px; border-radius: 50%; background: <?= $isActive ? '#10b981' : '#ef4444' ?>;"></span>
                            <?= $isActive ? t('Active') : t('Disabled') ?>
                        </span>
                    </div>

                    <!-- 2FA Security -->
                    <div>
                        <?php if ($has2fa): ?>
                            <span style="color: #059669; font-size: 0.78rem; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fa fa-lock"></i> <?= t('Enabled') ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #94a3b8; font-size: 0.78rem; font-weight: 500;">
                                <?= t('Off') ?>
                            </span>
                        <?php endif ?>
                    </div>

                    <!-- Actions Dropdown -->
                    <div style="text-align: right; display: flex; align-items: center; justify-content: flex-end; gap: 6px;">
                        <?php if ($this->user->hasAccess('UserModificationController', 'show') && $isActive): ?>
                            <span class="btn-group">
                                <?= $this->modal->medium('edit', t('Edit'), 'UserModificationController', 'show', array('user_id' => $u['id']), 'btn btn-sm', array('style' => 'font-size: 0.75rem; padding: 4px 8px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; color: #475569; text-decoration: none; font-weight: 600;')) ?>
                            </span>
                        <?php endif ?>

                        <div class="dropdown">
                            <a href="#" class="dropdown-menu dropdown-menu-link-icon" aria-label="<?= t('User actions') ?>" style="padding: 4px 8px; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none;">
                                <i class="fa fa-ellipsis-v"></i>
                            </a>
                            <ul style="min-width: 180px; padding: 6px; border-radius: 10px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15); border: 1px solid #e2e8f0; text-align: left;">
                                <li>
                                    <?= $this->url->link('<i class="fa fa-user fa-fw"></i> '.t('View Profile'), 'UserViewController', 'show', array('user_id' => $u['id'])) ?>
                                </li>
                                <?php if ($isActive && $this->user->hasAccess('UserModificationController', 'show')): ?>
                                    <li>
                                        <?= $this->modal->medium('edit', t('Edit User'), 'UserModificationController', 'show', array('user_id' => $u['id'])) ?>
                                    </li>
                                    <li>
                                        <?= $this->modal->medium('picture-o', t('Avatar Image'), 'AvatarFileController', 'show', array('user_id' => $u['id'])) ?>
                                    </li>
                                <?php endif ?>
                                <?php if ($u['is_ldap_user'] == 0 && $this->user->hasAccess('UserCredentialController', 'changePassword')): ?>
                                    <li>
                                        <?= $this->modal->medium('key', t('Change Password'), 'UserCredentialController', 'changePassword', array('user_id' => $u['id'])) ?>
                                    </li>
                                <?php endif ?>
                                <?php if ($this->user->isAdmin()): ?>
                                    <li style="border-top: 1px solid #f1f5f9; margin-top: 4px; padding-top: 4px;">
                                        <?= $this->url->link('<i class="fa fa-dashboard fa-fw"></i> '.t('User Dashboard'), 'DashboardController', 'show', array('user_id' => $u['id'])) ?>
                                    </li>
                                <?php endif ?>
                            </ul>
                        </div>
                    </div>

                </div>
            <?php endforeach ?>

        </div>

        <div style="margin-top: 16px;">
            <?= $paginator ?>
        </div>
    <?php endif ?>

</div>

<!-- Interactive Roles & Permissions Guide Modal -->
<div id="sb-roles-guide-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; padding: 20px; box-sizing: border-box;" onclick="if(event.target === this) this.style.display='none';">
    <div style="background: #ffffff; border-radius: 18px; max-width: 820px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); border: 1px solid #e2e8f0; display: flex; flex-direction: column;" onclick="event.stopPropagation();">
        
        <!-- Modal Header -->
        <div style="padding: 20px 26px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: #f8fafc; border-radius: 18px 18px 0 0;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span style="width: 36px; height: 36px; border-radius: 10px; background: #e0e7ff; color: #4338ca; display: inline-flex; align-items: center; justify-content: center; font-size: 1.15rem;">
                    <i class="fa fa-book"></i>
                </span>
                <div>
                    <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: #0f172a;">
                        <?= t('Roles & Responsibilities Guide') ?>
                    </h3>
                    <span style="font-size: 0.78rem; color: #64748b;">
                        <?= t('Detailed permissions overview for global and project-level user roles.') ?>
                    </span>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('sb-roles-guide-modal').style.display='none';" style="background: transparent; border: none; font-size: 1.3rem; color: #94a3b8; cursor: pointer; padding: 4px 8px; border-radius: 6px;" onmouseover="this.style.color='#0f172a';" onmouseout="this.style.color='#94a3b8';">
                <i class="fa fa-times"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div style="padding: 24px 26px; display: flex; flex-direction: column; gap: 24px;">
            
            <!-- 1. System Level Roles -->
            <div>
                <h4 style="margin: 0 0 12px; font-size: 0.95rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.04em;">
                    <i class="fa fa-globe" style="color: #6366f1;"></i> <?= t('1. Global System Roles') ?>
                </h4>
                
                <div style="display: grid; grid-template-columns: 1fr; gap: 10px;">
                    
                    <!-- Admin -->
                    <div style="background: #f8fafc; border: 1.5px solid #e0e7ff; border-radius: 12px; padding: 14px 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                            <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 800; color: #4338ca; background: #e0e7ff; padding: 2px 9px; border-radius: 6px;">
                                <i class="fa fa-shield"></i> <?= t('Administrator') ?>
                            </span>
                            <span style="font-size: 0.75rem; font-weight: 700; color: #059669; background: #d1fae5; padding: 2px 7px; border-radius: 4px;">
                                <?= t('Full System Access') ?>
                            </span>
                        </div>
                        <p style="font-size: 0.84rem; color: #475569; margin: 0; line-height: 1.45;">
                            <?= t('Full ownership of the entire PMO environment. Can create/delete any project, manage all users, assign global roles, configure plugins and automated workflow rules, and view organization-wide portfolio analytics.') ?>
                        </p>
                    </div>

                    <!-- Manager -->
                    <div style="background: #f8fafc; border: 1.5px solid #e0f2fe; border-radius: 12px; padding: 14px 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                            <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 800; color: #0369a1; background: #e0f2fe; padding: 2px 9px; border-radius: 6px;">
                                <i class="fa fa-briefcase"></i> <?= t('Manager') ?>
                            </span>
                            <span style="font-size: 0.75rem; font-weight: 700; color: #0369a1; background: #e0f2fe; padding: 2px 7px; border-radius: 4px;">
                                <?= t('Project Creation & Leadership') ?>
                            </span>
                        </div>
                        <p style="font-size: 0.84rem; color: #475569; margin: 0; line-height: 1.45;">
                            <?= t('Can initiate and create new projects, assign team members to their own projects, schedule roadmap timelines, and oversee project deliverables. Cannot modify global system configuration or manage other admins.') ?>
                        </p>
                    </div>

                    <!-- User/Member -->
                    <div style="background: #f8fafc; border: 1.5px solid #f1f5f9; border-radius: 12px; padding: 14px 16px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                            <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 800; color: #475569; background: #f1f5f9; padding: 2px 9px; border-radius: 6px;">
                                <i class="fa fa-user"></i> <?= t('Member / User') ?>
                            </span>
                            <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 2px 7px; border-radius: 4px;">
                                <?= t('Task Execution & Delivery') ?>
                            </span>
                        </div>
                        <p style="font-size: 0.84rem; color: #475569; margin: 0; line-height: 1.45;">
                            <?= t('Core team member. Has access to assigned projects to create/update tasks, complete subtasks, submit deliverables/reports for review, and track deadlines on the calendar and personal task list.') ?>
                        </p>
                    </div>

                </div>
            </div>

            <!-- 2. Project Level Roles -->
            <div>
                <h4 style="margin: 0 0 12px; font-size: 0.95rem; font-weight: 800; color: #1e293b; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.04em;">
                    <i class="fa fa-folder-open" style="color: #6366f1;"></i> <?= t('2. Project-Specific Roles') ?>
                </h4>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 10px;">
                    
                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px;">
                        <div style="font-weight: 800; color: #1e293b; font-size: 0.85rem; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                            <span style="color: #eab308;">★</span> <?= t('Project Manager') ?>
                        </div>
                        <div style="font-size: 0.78rem; color: #64748b; line-height: 1.4;">
                            <?= t('Full project control: configure stages, invite project members, set deadlines, and manage workflow rules.') ?>
                        </div>
                    </div>

                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px;">
                        <div style="font-weight: 800; color: #1e293b; font-size: 0.85rem; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                            <span style="color: #3b82f6;">●</span> <?= t('Project Member') ?>
                        </div>
                        <div style="font-size: 0.78rem; color: #64748b; line-height: 1.4;">
                            <?= t('Active contributor: create & edit tasks, check off subtasks, link dependencies, and upload reports.') ?>
                        </div>
                    </div>

                    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px;">
                        <div style="font-weight: 800; color: #1e293b; font-size: 0.85rem; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                            <span style="color: #94a3b8;">👁</span> <?= t('Project Viewer') ?>
                        </div>
                        <div style="font-size: 0.78rem; color: #64748b; line-height: 1.4;">
                            <?= t('Read-only stakeholder: view task cards, Gantt chart, calendar, and deliverables without modifying data.') ?>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <!-- Modal Footer -->
        <div style="padding: 14px 26px; border-top: 1px solid #e2e8f0; background: #f8fafc; border-radius: 0 0 18px 18px; text-align: right;">
            <button type="button" onclick="document.getElementById('sb-roles-guide-modal').style.display='none';" class="btn btn-blue" style="background: #4f46e5; color: white; border: none; font-weight: 700; border-radius: 8px; padding: 8px 18px; font-size: 0.85rem; cursor: pointer;">
                <?= t('Got it, Close') ?>
            </button>
        </div>

    </div>
</div>
