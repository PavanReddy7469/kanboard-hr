<div style="max-width: 1000px; margin: 0 auto; padding: 10px 0 40px;">
    
    <!-- Clean Search Header & Input -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05); margin-bottom: 24px;">
        <form method="get" action="<?= $this->url->dir() ?>" class="search" style="margin: 0;">
            <?= $this->form->hidden('controller', $values) ?>
            <?= $this->form->hidden('action', $values) ?>

            <div class="sb-search-shell">
                <i class="fa fa-search" style="font-size: 1.1rem; color: #6366f1; margin-right: 12px;"></i>
                <?php /* FormHelper::input() does implode(' ', $attributes) - it joins the
                     VALUES and throws the keys away, so 'placeholder' => '...' emitted
                     the bare text into the tag instead of an attribute. These have to
                     be complete attribute strings. */ ?>
                <?= $this->form->text('search', $values, array(), array(
                    empty($values['search']) ? 'autofocus' : '',
                    'placeholder="'.$this->text->e(t('Search projects, tasks, or keywords (typo & fuzzy search enabled)...')).'"',
                ), 'sb-search-input') ?>
                
                <?php if (! empty($values['search'])): ?>
                    <a href="<?= $this->url->href('SearchController', 'index') ?>" style="color: #94a3b8; text-decoration: none; padding: 4px 8px;" title="<?= t('Clear') ?>">
                        <i class="fa fa-times-circle"></i>
                    </a>
                <?php endif ?>

                <button type="submit" class="btn btn-blue" style="margin-left: 8px; padding: 7px 18px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; background: #4f46e5; color: white; border: none; cursor: pointer;">
                    <?= t('Search') ?>
                </button>
            </div>
        </form>

        <!-- Quick Filter Tags -->
        <div style="margin-top: 14px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <span style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-right: 4px;">
                <?= t('Quick Filters:') ?>
            </span>
            <a href="<?= $this->url->href('SearchController', 'index', array('search' => 'status:open')) ?>" style="font-size: 0.78rem; font-weight: 600; color: #4338ca; background: #e0e7ff; padding: 3px 10px; border-radius: 20px; text-decoration: none; transition: background 0.15s;">
                <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #6366f1; margin-right: 4px;"></span> <?= t('Open Tasks') ?>
            </a>
            <a href="<?= $this->url->href('SearchController', 'index', array('search' => 'status:open assignee:me')) ?>" style="font-size: 0.78rem; font-weight: 600; color: #065f46; background: #d1fae5; padding: 3px 10px; border-radius: 20px; text-decoration: none;">
                <i class="fa fa-user-circle" style="font-size: 0.72rem; margin-right: 3px;"></i> <?= t('My Tasks') ?>
            </a>
            <a href="<?= $this->url->href('SearchController', 'index', array('search' => 'status:open due:today')) ?>" style="font-size: 0.78rem; font-weight: 600; color: #991b1b; background: #fee2e2; padding: 3px 10px; border-radius: 20px; text-decoration: none;">
                <i class="fa fa-clock-o" style="font-size: 0.72rem; margin-right: 3px;"></i> <?= t('Due Today') ?>
            </a>
            <a href="<?= $this->url->href('SearchController', 'index', array('search' => 'priority:1')) ?>" style="font-size: 0.78rem; font-weight: 600; color: #b45309; background: #fef3c7; padding: 3px 10px; border-radius: 20px; text-decoration: none;">
                <i class="fa fa-flag" style="font-size: 0.72rem; margin-right: 3px;"></i> <?= t('P1 Priority') ?>
            </a>
        </div>
    </div>

    <!-- Empty / Initial State (Clean & Modern) -->
    <?php if (empty($values['search'])): ?>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 40px 24px; text-align: center;">
            <div style="width: 56px; height: 56px; border-radius: 16px; background: #e0e7ff; color: #4338ca; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 16px;">
                <i class="fa fa-search"></i>
            </div>
            <h3 style="margin: 0 0 8px; font-size: 1.15rem; font-weight: 800; color: #0f172a;">
                <?= t('SUPERBEE Smart Search') ?>
            </h3>
            <p style="color: #64748b; font-size: 0.9rem; max-width: 520px; margin: 0 auto; line-height: 1.5;">
                <?= t('Search across all projects, tasks, and deliverables. Supports typo-tolerance, phonetic search, and partial keywords.') ?>
            </p>
        </div>

    <!-- Search Results View -->
    <?php else: ?>

        <!-- Matched Projects -->
        <?php if (! empty($matched_projects)): ?>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; margin-bottom: 24px;">
                <h3 style="margin: 0 0 16px; font-size: 0.95rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.04em;">
                    <i class="fa fa-folder" style="color: #6366f1;"></i>
                    <?= t('Matching Projects') ?>
                    <span style="font-size: 0.75rem; background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 12px; font-weight: 700;">
                        <?= count($matched_projects) ?>
                    </span>
                </h3>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 14px;">
                    <?php foreach ($matched_projects as $project): ?>
                        <?php
                            $pCode = ! empty($project['identifier']) ? strtoupper(substr($project['identifier'], 0, 4)) : sprintf('P%03d', $project['id']);
                        ?>
                        <div class="sb-hover-card" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 8px;">
                                <span style="font-family: monospace; font-size: 0.78rem; font-weight: 800; color: #4338ca; background: #e0e7ff; padding: 2px 7px; border-radius: 6px;">
                                    <?= $pCode ?>
                                </span>
                                <?php if (! empty($project['_is_fuzzy'])): ?>
                                    <span style="font-size: 0.72rem; background: #fef3c7; color: #92400e; padding: 2px 6px; border-radius: 4px; font-weight: 700;">
                                        <i class="fa fa-bolt"></i> <?= $project['_fuzzy_score'] ?>% <?= t('Match') ?>
                                    </span>
                                <?php endif ?>
                            </div>

                            <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $project['id'])) ?>" style="font-size: 1rem; font-weight: 700; color: #1e293b; text-decoration: none; display: block; margin-bottom: 6px;">
                                <?= $this->text->e($project['name']) ?>
                            </a>

                            <?php if (! empty($project['description'])): ?>
                                <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 12px; line-height: 1.4;">
                                    <?= $this->text->e(mb_substr($project['description'], 0, 90)) ?><?= mb_strlen($project['description']) > 90 ? '...' : '' ?>
                                </p>
                            <?php endif ?>

                            <div style="display: flex; gap: 8px; margin-top: 10px; border-top: 1px solid #edf2f7; padding-top: 10px;">
                                <a href="<?= $this->url->href('TaskGridController', 'show', array('plugin' => 'TaskManager', 'project_id' => $project['id'])) ?>" class="btn btn-sm" style="font-size: 0.78rem; background: #ffffff; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; text-decoration: none;">
                                    <i class="fa fa-check-square-o"></i> <?= t('Tasks') ?>
                                </a>
                                <a href="<?= $this->url->href('DeliverableController', 'index', array('plugin' => 'TaskManager', 'project_id' => $project['id'])) ?>" class="btn btn-sm" style="font-size: 0.78rem; background: #ffffff; color: #475569; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; text-decoration: none;">
                                    <i class="fa fa-check-circle-o"></i> <?= t('Reports') ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach ?>
                </div>
            </div>
        <?php endif ?>

        <!-- Matched Tasks -->
        <?php if (! $paginator->isEmpty()): ?>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px;">
                <h3 style="margin: 0 0 16px; font-size: 0.95rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.04em;">
                    <i class="fa fa-tasks" style="color: #6366f1;"></i>
                    <?= t('Matching Tasks') ?>
                    <span style="font-size: 0.75rem; background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 12px; font-weight: 700;">
                        <?= $paginator->getTotal() ?>
                    </span>
                </h3>

                <?= $this->render('search/results', array(
                    'paginator' => $paginator,
                )) ?>
            </div>
        <?php endif ?>

        <!-- No Results Found -->
        <?php if ($paginator->isEmpty() && empty($matched_projects)): ?>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 40px 24px; text-align: center;">
                <i class="fa fa-search-minus" style="font-size: 2.4rem; color: #cbd5e1; display: block; margin-bottom: 12px;"></i>
                <h4 style="margin: 0 0 6px; font-size: 1.1rem; font-weight: 700; color: #1e293b;">
                    <?= t('No matching results found') ?>
                </h4>
                <p style="color: #64748b; font-size: 0.88rem; margin: 0;">
                    <?= t('No projects or tasks matched "%s". Try partial keywords or one of the quick filter buttons.', $this->text->e($values['search'])) ?>
                </p>
            </div>
        <?php endif ?>

    <?php endif ?>

</div>
