<div class="sb-calendar-wrapper" style="max-width: 1400px; margin: 0 auto; padding-bottom: 40px;">
    
    <?php /* The bar is kept to one line. It used to carry a 38px badge, a
             heading and a sentence of explanation, which together with the
             application header pushed the first day of the month 468px down
             a 742px screen - so four empty weeks filled the view and every
             week that actually had work in it was below the fold. */ ?>
    <!-- Top Filter Bar -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 16px; margin-bottom: 12px; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04); display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;">

        <!-- Left: Title & what is in view -->
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="width: 30px; height: 30px; border-radius: 8px; background: #e0e7ff; color: #4338ca; display: inline-flex; align-items: center; justify-content: center; font-size: 0.95rem;">
                <i class="fa fa-calendar"></i>
            </span>
            <h2 style="font-size: 1.02rem; font-weight: 800; color: #0f172a; margin: 0; letter-spacing: -0.01em;">
                <?= t('Calendar & Schedule') ?>
            </h2>
            <?php /* Filled in by calendar.js once the events for the visible
                     range come back. Without it an empty top half of the
                     month reads as a broken page rather than as a quiet
                     fortnight with the work further down. */ ?>
            <span id="sb-cal-count" data-empty="<?= t('No tasks in this view') ?>" style="font-size: 0.78rem; font-weight: 700; color: #475569; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2px 10px;"></span>
        </div>

        <!-- Right: Filter Controls -->
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">

            <?php /* This page shows one person's work, and until now that
                     person was always whoever was signed in - which is why an
                     administrator with nothing assigned to them saw an empty
                     calendar and read it as broken. Whose work it shows is
                     now a choice. "Everyone" is bounded by the projects the
                     viewer can open, which the controller enforces. */ ?>
            <!-- Assignee Filter -->
            <div style="position: relative;">
                <select id="sb-cal-assignee-filter" data-me="<?= (int) $user['id'] ?>" style="padding: 7px 28px 7px 12px; font-size: 0.84rem; font-weight: 600; color: #1e293b; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; cursor: pointer; outline: none; appearance: none; -webkit-appearance: none;">
                    <option value="<?= (int) $user['id'] ?>"><?= t('My tasks') ?></option>
                    <option value="0"><?= t('★ Everyone') ?></option>
                    <?php foreach ($assignees as $assigneeId => $assigneeName): ?>
                        <?php if ((int) $assigneeId === (int) $user['id']) { continue; } ?>
                        <option value="<?= (int) $assigneeId ?>"><?= $this->text->e($assigneeName) ?></option>
                    <?php endforeach ?>
                </select>
                <i class="fa fa-caret-down" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #64748b; font-size: 0.75rem;"></i>
            </div>

            <!-- Project Filter -->
            <div style="position: relative;">
                <select id="sb-cal-project-filter" style="padding: 7px 28px 7px 12px; font-size: 0.84rem; font-weight: 600; color: #1e293b; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; cursor: pointer; outline: none; appearance: none; -webkit-appearance: none;">
                    <option value="0"><?= t('★ All Projects') ?></option>
                    <?php if (! empty($projects)): ?>
                        <?php foreach ($projects as $projId => $projName): ?>
                            <option value="<?= $projId ?>"><?= $this->text->e($projName) ?></option>
                        <?php endforeach ?>
                    <?php endif ?>
                </select>
                <i class="fa fa-caret-down" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #64748b; font-size: 0.75rem;"></i>
            </div>

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

    <!-- Calendar Container Card -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; box-shadow: 0 1px 4px rgba(15, 23, 42, 0.04);">
        <?= $this->calendar->render(
            $this->url->href('CalendarController', 'userEvents', array('user_id' => $user['id'], 'plugin' => 'Calendar')),
            $this->url->href('CalendarController', 'save', array('plugin' => 'Calendar'))
        ) ?>
    </div>

</div>
