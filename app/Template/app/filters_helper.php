<?= $this->hook->render('template:app:filters-helper:before', isset($project) ? array('project' => $project) : array()) ?>
<div class="dropdown">
    <a href="#" class="dropdown-menu dropdown-menu-link-icon" title="<?= t('Filters & Sorting') ?>" aria-label="<?= t('Filters & Sorting') ?>" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 12px; color: #4338ca; text-decoration: none; font-weight: 700; font-size: 0.84rem;">
        <i class="fa fa-filter fa-fw" style="color: #6366f1;"></i>
        <span><?= t('Filter') ?></span>
        <i class="fa fa-caret-down" style="color: #94a3b8; font-size: 0.75rem;"></i>
    </a>
    <ul class="sb-nested-dropdown" style="min-width: 290px; max-height: 520px; overflow-y: auto; padding: 10px; border-radius: 14px; box-shadow: 0 12px 30px -4px rgba(15, 23, 42, 0.18); border: 1px solid #e2e8f0; background: #ffffff;">
        
        <!-- Box 1: Quick Actions & Views -->
        <li class="sb-filter-box" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px; margin-bottom: 8px;">
            <div style="font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; padding: 2px 6px 6px; display: flex; align-items: center; justify-content: space-between;">
                <span><i class="fa fa-bolt" style="color: #eab308;"></i> <?= t('Views & Quick Actions') ?></span>
            </div>
            <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px;">
                <li>
                    <a href="#" class="filter-helper filter-reset" data-filter="<?= isset($reset) ? $reset : 'status:open' ?>" style="display: flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 700; color: #4338ca; text-decoration: none; background: #e0e7ff;">
                        <i class="fa fa-refresh fa-fw"></i> <?= t('Reset All Filters') ?>
                    </a>
                </li>
                <li>
                    <a href="#" class="filter-helper" data-filter="status:open" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #1e293b; text-decoration: none;">
                        <i class="fa fa-check-circle-o fa-fw" style="color: #6366f1;"></i> <?= t('All Open Tasks') ?>
                    </a>
                </li>
                <li>
                    <a href="#" class="filter-helper" data-filter="status:open assignee:me" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #1e293b; text-decoration: none;">
                        <i class="fa fa-user-circle fa-fw" style="color: #059669;"></i> <?= t('My Assigned Tasks') ?>
                    </a>
                </li>
                <li>
                    <a href="#" class="filter-helper" data-filter="status:closed" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #1e293b; text-decoration: none;">
                        <i class="fa fa-check-square fa-fw" style="color: #10b981;"></i> <?= t('Closed / Done Tasks') ?>
                    </a>
                </li>
                <li>
                    <a href="#" class="filter-helper" data-filter="status:open due:yesterday" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #dc2626; text-decoration: none;">
                        <i class="fa fa-exclamation-circle fa-fw" style="color: #dc2626;"></i> <?= t('Overdue Tasks') ?>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Box 2: Statuses -->
        <li class="sb-filter-box" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px; margin-bottom: 8px;">
            <div style="font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; padding: 2px 6px 6px;">
                <i class="fa fa-adjust" style="color: #3b82f6;"></i> <?= t('Filter by Status') ?>
            </div>
            <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px;">
                <li>
                    <a href="#" class="filter-helper" data-filter="status:open" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #1e293b; text-decoration: none;">
                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #94a3b8;"></span> <?= t('Open') ?>
                    </a>
                </li>
                <li>
                    <a href="#" class="filter-helper" data-filter="status:open column:&quot;Work in Progress&quot;" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #1e293b; text-decoration: none;">
                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #3b82f6;"></span> <?= t('Work in Progress') ?>
                    </a>
                </li>
                <li>
                    <a href="#" class="filter-helper" data-filter="status:open column:&quot;In Review&quot;" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #1e293b; text-decoration: none;">
                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #f59e0b;"></span> <?= t('In Review') ?>
                    </a>
                </li>
                <li>
                    <a href="#" class="filter-helper" data-filter="status:closed" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #1e293b; text-decoration: none;">
                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span> <?= t('Done') ?>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Box 3: Assignees -->
        <li class="sb-filter-box" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px; margin-bottom: 8px;">
            <div style="font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; padding: 2px 6px 6px;">
                <i class="fa fa-users" style="color: #6366f1;"></i> <?= t('Filter by Assignee') ?>
            </div>
            <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px;">
                <li>
                    <a href="#" class="filter-helper" data-filter="status:open assignee:me" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #1e293b; text-decoration: none;">
                        <i class="fa fa-user-circle fa-fw" style="color: #6366f1;"></i> <?= t('Assigned to Me') ?>
                    </a>
                </li>
                <li>
                    <a href="#" class="filter-helper" data-filter="status:open assignee:nobody" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #1e293b; text-decoration: none;">
                        <i class="fa fa-user-times fa-fw" style="color: #94a3b8;"></i> <?= t('Unassigned') ?>
                    </a>
                </li>
                <li>
                    <a href="#" class="filter-helper" data-filter="status:open assignee:anybody" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #1e293b; text-decoration: none;">
                        <i class="fa fa-user-plus fa-fw" style="color: #10b981;"></i> <?= t('All Assigned') ?>
                    </a>
                </li>
                <?php if (! empty($users_list)): ?>
                    <?php foreach ($users_list as $userItem): ?>
                        <li>
                            <a href="#" class="filter-helper" data-filter='status:open assignee:"<?= $this->text->e($userItem) ?>"' style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #1e293b; text-decoration: none;">
                                <i class="fa fa-user fa-fw" style="color: #4f46e5; font-size: 0.75rem;"></i> <?= $this->text->e($userItem) ?>
                            </a>
                        </li>
                    <?php endforeach ?>
                <?php endif ?>
            </ul>
        </li>

        <!-- Box 4: Priorities -->
        <li class="sb-filter-box" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px; margin-bottom: 8px;">
            <div style="font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; padding: 2px 6px 6px;">
                <i class="fa fa-flag" style="color: #ef4444;"></i> <?= t('Filter by Priority') ?>
            </div>
            <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px;">
                <li>
                    <a href="#" class="filter-helper" data-filter="status:open priority:1" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #991b1b; text-decoration: none;">
                        <span style="font-size: 0.72rem; font-weight: 800; padding: 1px 6px; border-radius: 4px; background: #fee2e2; color: #b91c1c;">P1</span> <?= t('Critical / High Priority') ?>
                    </a>
                </li>
                <li>
                    <a href="#" class="filter-helper" data-filter="status:open priority:2" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #92400e; text-decoration: none;">
                        <span style="font-size: 0.72rem; font-weight: 800; padding: 1px 6px; border-radius: 4px; background: #fef3c7; color: #b45309;">P2</span> <?= t('Medium Priority') ?>
                    </a>
                </li>
                <li>
                    <a href="#" class="filter-helper" data-filter="status:open priority:3" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #334155; text-decoration: none;">
                        <span style="font-size: 0.72rem; font-weight: 800; padding: 1px 6px; border-radius: 4px; background: #f1f5f9; color: #475569;">P3</span> <?= t('Low Priority') ?>
                    </a>
                </li>
            </ul>
        </li>

        <!-- Box 5: Grouping & Sorting -->
        <li class="sb-filter-box" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px;">
            <div style="font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; padding: 2px 6px 6px;">
                <i class="fa fa-th-list" style="color: #8b5cf6;"></i> <?= t('Group & Sort') ?>
            </div>
            <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px;">
                <?php $pIdVal = isset($project['id']) ? $project['id'] : 1; ?>
                <li>
                    <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $pIdVal, 'group_by' => 'none')) ?>" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #1e293b; text-decoration: none;">
                        <i class="fa fa-bars fa-fw" style="color: #64748b;"></i> <?= t('Group by: None (Flat List)') ?>
                    </a>
                </li>
                <li>
                    <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $pIdVal, 'group_by' => 'status')) ?>" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #1e293b; text-decoration: none;">
                        <i class="fa fa-adjust fa-fw" style="color: #3b82f6;"></i> <?= t('Group by: Status') ?>
                    </a>
                </li>
                <li>
                    <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $pIdVal, 'group_by' => 'owner')) ?>" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #1e293b; text-decoration: none;">
                        <i class="fa fa-user fa-fw" style="color: #10b981;"></i> <?= t('Group by: Owner') ?>
                    </a>
                </li>
                <li>
                    <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $pIdVal, 'group_by' => 'priority')) ?>" style="display: flex; align-items: center; gap: 8px; padding: 5px 10px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #1e293b; text-decoration: none;">
                        <i class="fa fa-flag fa-fw" style="color: #ef4444;"></i> <?= t('Group by: Priority') ?>
                    </a>
                </li>
            </ul>
        </li>

    </ul>
</div>
<?= $this->hook->render('template:app:filters-helper:after', isset($project) ? array('project' => $project) : array()) ?>