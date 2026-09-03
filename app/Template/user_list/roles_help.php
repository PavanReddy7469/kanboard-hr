<div class="page-header" style="margin-bottom: 20px;">
    <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 10px;">
        <span style="width: 32px; height: 32px; border-radius: 8px; background: #e0e7ff; color: #4338ca; display: inline-flex; align-items: center; justify-content: center; font-size: 1rem;">
            <i class="fa fa-graduation-cap"></i>
        </span>
        <?= t('SUPERBEE Roles & Access Rights Guide') ?>
    </h2>
</div>

<div style="font-size: 0.88rem; color: #475569; line-height: 1.5; margin-bottom: 20px;">
    <p style="margin: 0 0 10px;">
        <?= t('Permissions in SUPERBEE PMO are configured to ensure clean separation of leadership governance and contributor execution:') ?>
    </p>
</div>

<!-- Unified Roles Cards -->
<div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 24px;">
    
    <!-- 1. Administrator & Project Manager -->
    <div style="background: #f8fafc; border: 1.5px solid #e0e7ff; border-radius: 12px; padding: 16px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 0.82rem; font-weight: 800; padding: 3px 10px; border-radius: 12px; background: #e0e7ff; color: #4338ca; display: inline-flex; align-items: center; gap: 5px;">
                    <i class="fa fa-shield"></i> <?= t('1. Administrator & Project Manager') ?>
                </span>
                <span style="font-weight: 700; color: #0f172a; font-size: 0.92rem;"><?= t('Leadership & System Ownership') ?></span>
            </div>
            <span style="font-size: 0.75rem; font-weight: 700; color: #059669; background: #d1fae5; padding: 2px 8px; border-radius: 10px;">
                <?= t('Full Administrative Access') ?>
            </span>
        </div>
        <ul style="margin: 0; padding-left: 20px; font-size: 0.84rem; color: #334155; line-height: 1.5;">
            <li><strong><?= t('Full Governance:') ?></strong> <?= t('Full access to all projects, Settings, and People & Roles management.') ?></li>
            <li><strong><?= t('Task Control:') ?></strong> <?= t('Create tasks (+ Add Task), move tasks across stages, and edit task parameters.') ?></li>
            <li><strong><?= t('User & Permission Management:') ?></strong> <?= t('Create user accounts, invite team members, and assign project roles.') ?></li>
            <li><strong><?= t('Review & Oversight:') ?></strong> <?= t('Review submitted task deliverables and generate executive reports.') ?></li>
        </ul>
    </div>

    <!-- 2. Team Member -->
    <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 16px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 0.82rem; font-weight: 800; padding: 3px 10px; border-radius: 12px; background: #f1f5f9; color: #334155; display: inline-flex; align-items: center; gap: 5px;">
                    <i class="fa fa-user"></i> <?= t('2. Team Member') ?>
                </span>
                <span style="font-weight: 700; color: #0f172a; font-size: 0.92rem;"><?= t('Task Contributor & Delivery Execution') ?></span>
            </div>
            <span style="font-size: 0.75rem; font-weight: 700; color: #475569; background: #f1f5f9; padding: 2px 8px; border-radius: 10px;">
                <?= t('Contributor Access') ?>
            </span>
        </div>
        <ul style="margin: 0; padding-left: 20px; font-size: 0.84rem; color: #334155; line-height: 1.5;">
            <li><strong><?= t('Work on Tasks:') ?></strong> <?= t('View assigned tasks on Tasks Grid, Personal Dashboard, Gantt, and Calendar.') ?></li>
            <li><strong><?= t('Subtasks & Progress:') ?></strong> <?= t('Check off completed subtasks and post progress updates.') ?></li>
            <li><strong><?= t('Submit Deliverables & Reports:') ?></strong> <?= t('Upload deliverable files, evidence, and completion reports.') ?></li>
            <li><span style="color: #b91c1c;"><strong><?= t('Restricted:') ?></strong> <?= t('Cannot create new tasks, edit task titles/dates, or move tasks across workflow columns.') ?></span></li>
        </ul>
    </div>

    <!-- 3. Viewer -->
    <div style="background: #f8fafc; border: 1.5px solid #f1f5f9; border-radius: 12px; padding: 16px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 0.82rem; font-weight: 800; padding: 3px 10px; border-radius: 12px; background: #f8fafc; color: #64748b; border: 1px solid #cbd5e1; display: inline-flex; align-items: center; gap: 5px;">
                    <i class="fa fa-eye"></i> <?= t('3. Viewer') ?>
                </span>
                <span style="font-weight: 700; color: #0f172a; font-size: 0.92rem;"><?= t('Client & Observer') ?></span>
            </div>
            <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; background: #f1f5f9; padding: 2px 8px; border-radius: 10px;">
                <?= t('Read-Only') ?>
            </span>
        </div>
        <ul style="margin: 0; padding-left: 20px; font-size: 0.84rem; color: #334155; line-height: 1.5;">
            <li><strong><?= t('Full Read-Only Visibility:') ?></strong> <?= t('Inspect task lists, Gantt roadmaps, and calendars without modifying data.') ?></li>
        </ul>
    </div>

</div>

<div style="text-align: right; padding-top: 10px; border-top: 1px solid #e2e8f0;">
    <button type="button" class="btn btn-blue" data-sb-modal-close style="background: #4f46e5; color: white; border: none; padding: 8px 18px; border-radius: 8px; font-weight: 700; cursor: pointer;">
        <?= t('Got it!') ?>
    </button>
</div>
