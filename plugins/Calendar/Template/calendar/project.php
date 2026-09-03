<?= $this->projectHeader->render($project, 'CalendarController', 'project', false, 'Calendar') ?>

<div class="sb-calendar-wrapper" style="margin-top: 16px; padding-bottom: 40px;">
    
    <!-- Top Filter Bar -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px 20px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04); display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px;">
        
        <!-- Left: Project Info -->
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-family: monospace; font-size: 0.8rem; font-weight: 800; color: #4338ca; background: #e0e7ff; padding: 3px 8px; border-radius: 6px;">
                <?= ! empty($project['identifier']) ? strtoupper(substr($project['identifier'], 0, 4)) : sprintf('P%03d', $project['id']) ?>
            </span>
            <span style="font-size: 0.95rem; font-weight: 700; color: #0f172a;">
                <?= $this->text->e($project['name']) ?> <?= t('Calendar') ?>
            </span>
        </div>

        <!-- Right: Filter Controls -->
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            
            <!-- Status Filter -->
            <div style="position: relative;">
                <select id="sb-cal-status-filter" style="padding: 7px 28px 7px 12px; font-size: 0.84rem; font-weight: 600; color: #1e293b; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; cursor: pointer; outline: none; appearance: none; -webkit-appearance: none;">
                    <option value="open"><?= t('Status: Open Tasks') ?></option>
                    <option value="closed"><?= t('Status: Closed / Done') ?></option>
                    <option value="all"><?= t('Status: All Tasks') ?></option>
                </select>
                <i class="fa fa-caret-down" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #64748b; font-size: 0.75rem;"></i>
            </div>

            <!-- Priority Filter -->
            <div style="position: relative;">
                <select id="sb-cal-priority-filter" style="padding: 7px 28px 7px 12px; font-size: 0.84rem; font-weight: 600; color: #1e293b; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; cursor: pointer; outline: none; appearance: none; -webkit-appearance: none;">
                    <option value="0"><?= t('Priority: All') ?></option>
                    <option value="1"><?= t('Priority: P1 (High)') ?></option>
                    <option value="2"><?= t('Priority: P2 (Medium)') ?></option>
                    <option value="3"><?= t('Priority: P3 (Low)') ?></option>
                </select>
                <i class="fa fa-caret-down" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #64748b; font-size: 0.75rem;"></i>
            </div>

            <!-- Keyword Search Box -->
            <div style="position: relative; display: flex; align-items: center;">
                <i class="fa fa-search" style="position: absolute; left: 10px; color: #94a3b8; font-size: 0.8rem;"></i>
                <input type="text" class="sb-grow-input" id="sb-cal-search-filter" placeholder="<?= t('Filter by title...') ?>" style="padding: 7px 10px 7px 28px; font-size: 0.84rem; color: #1e293b; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; outline: none; width: 160px; transition: width 0.2s;" />
            </div>

            <!-- Reset Button -->
            <button type="button" id="sb-cal-filter-reset" class="btn btn-sm" style="font-size: 0.8rem; padding: 7px 12px; background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; border-radius: 8px; cursor: pointer; font-weight: 600;" title="<?= t('Reset all filters') ?>">
                <i class="fa fa-refresh"></i> <?= t('Reset') ?>
            </button>

        </div>
    </div>

    <!-- Calendar Card Container -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);">
        <?= $this->calendar->render(
            $this->url->href('CalendarController', 'projectEvents', array('project_id' => $project['id'], 'plugin' => 'Calendar')),
            $this->url->href('CalendarController', 'save', array('project_id' => $project['id'], 'plugin' => 'Calendar'))
        ) ?>
    </div>

</div>
