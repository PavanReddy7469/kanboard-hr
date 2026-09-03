<section class="zg">
    <div class="zg-title" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2><i class="fa fa-list-ul text-indigo"></i> <?= t('Task Lists') ?></h2>
            <p style="color: #64748b; font-size: 0.88rem; margin: 4px 0 0;">
                <?= t('Organize and track tasks grouped by feature batches, sprints, and deliverable packages.') ?>
            </p>
        </div>
        <div>
            <?= $this->modal->medium('plus', t('New Task List'), 'TaskGroupController', 'create', array('plugin' => 'TaskManager', 'project_id' => $project['id'])) ?>
        </div>
    </div>

    <?php if (empty($task_lists)): ?>
        <div class="panel" style="background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 40px; text-align: center;">
            <div style="width: 60px; height: 60px; border-radius: 50%; background: #e0e7ff; color: #4338ca; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                <i class="fa fa-list-ul" style="font-size: 1.6rem;"></i>
            </div>
            <h3 style="margin: 0 0 8px; color: #0f172a; font-size: 1.15rem;"><?= t('No Task Lists Yet') ?></h3>
            <p style="color: #64748b; font-size: 0.9rem; max-width: 460px; margin: 0 auto 20px;">
                <?= t('Task lists help group related tasks together within milestones. Create your first task list to structure your project workflow.') ?>
            </p>
            <?= $this->modal->medium('plus', t('Create First Task List'), 'TaskGroupController', 'create', array('plugin' => 'TaskManager', 'project_id' => $project['id'])) ?>
        </div>
    <?php else: ?>
        <div class="zg-scroll" style="background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <table class="zg-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 12px 16px; text-align: left; font-size: 0.82rem; font-weight: 700; color: #475569; text-transform: uppercase;"><?= t('Task List Name') ?></th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 0.82rem; font-weight: 700; color: #475569; text-transform: uppercase;"><?= t('Milestone') ?></th>
                        <th style="padding: 12px 16px; text-align: center; font-size: 0.82rem; font-weight: 700; color: #475569; text-transform: uppercase;"><?= t('Tasks') ?></th>
                        <th style="padding: 12px 16px; text-align: left; font-size: 0.82rem; font-weight: 700; color: #475569; text-transform: uppercase; width: 220px;"><?= t('Progress') ?></th>
                        <th style="padding: 12px 16px; text-align: right; font-size: 0.82rem; font-weight: 700; color: #475569; text-transform: uppercase;"><?= t('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($task_lists as $tl): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 14px 16px;">
                                <strong style="font-size: 0.95rem; color: #0f172a; display: block;">
                                    <?= $this->text->e($tl['title']) ?>
                                </strong>
                            </td>
                            <td style="padding: 14px 16px; font-size: 0.88rem; color: #64748b;">
                                <?php if (! empty($tl['milestone_title']) && $tl['milestone_title'] !== t('None')): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 4px; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 6px; font-size: 0.78rem; font-weight: 600;">
                                        <i class="fa fa-flag"></i> <?= $this->text->e($tl['milestone_title']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">&mdash;</span>
                                <?php endif ?>
                            </td>
                            <td style="padding: 14px 16px; text-align: center; font-weight: 600; font-size: 0.9rem; color: #334155;">
                                <?= $tl['total_tasks'] ?> <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 400;"><?= t('tasks') ?></span>
                            </td>
                            <td style="padding: 14px 16px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="flex: 1; height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden;">
                                        <div style="width: <?= $tl['progress'] ?>%; height: 100%; background: <?= $tl['progress'] == 100 ? '#10b981' : '#6366f1' ?>; border-radius: 4px;"></div>
                                    </div>
                                    <span style="font-size: 0.82rem; font-weight: 700; color: #475569; width: 38px; text-align: right;"><?= $tl['progress'] ?>%</span>
                                </div>
                            </td>
                            <td style="padding: 14px 16px; text-align: right; white-space: nowrap;">
                                <div style="display: inline-flex; gap: 6px;">
                                    <?= $this->modal->medium('edit', t('Edit'), 'TaskGroupController', 'edit', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'task_list_id' => $tl['id'])) ?>
                                    <?= $this->modal->confirm('trash-o', t('Remove'), 'TaskGroupController', 'confirm', array('plugin' => 'TaskManager', 'project_id' => $project['id'], 'task_list_id' => $tl['id'])) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
