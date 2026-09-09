<?php
/* Filter panel.
 *
 * A slide-out on the right: one collapsed row per field, each opening to its
 * own options. Nothing is applied until Find is pressed, so a filter can be
 * built up in several steps without the list rearranging underneath.
 *
 * The fields are only those this project actually has, and only those the
 * search language can express. Duration and completion percentage are
 * computed for display and have no filter keyword, so they are not offered -
 * showing them would promise something that could not be applied.
 */
?>
<div class="zf-overlay" hidden data-zf-overlay></div>

<aside class="zf-panel" hidden data-zf-panel aria-label="<?= t('Filter') ?>">
    <header class="zf-head">
        <h3><?= t('Filter') ?></h3>
        <button type="button" class="zf-reset" data-zf-reset><?= t('Reset') ?></button>
    </header>

    <div class="zf-search">
        <i class="fa fa-search" aria-hidden="true"></i>
        <input type="text" data-zf-fieldsearch placeholder="<?= t('Filter Search') ?>" aria-label="<?= t('Find a field') ?>">
    </div>

    <div class="zf-body">

        <!-- Task name ---------------------------------------------------- -->
        <section class="zf-row" data-zf-field="title" data-zf-label="<?= t('Task Name') ?>">
            <button type="button" class="zf-rowhead" data-zf-toggle>
                <span class="zf-rowlabel"><?= t('Task Name') ?></span>
                <i class="fa fa-angle-right zf-caret" aria-hidden="true"></i>
            </button>
            <div class="zf-rowbody" hidden>
                <span class="zf-op"><?= t('Contains') ?></span>
                <input type="text" class="zf-text" data-zf-value placeholder="<?= t('Type a word from the task name') ?>">
            </div>
        </section>

        <!-- Status (the project's own columns) --------------------------- -->
        <section class="zf-row" data-zf-field="column" data-zf-label="<?= t('Status') ?>">
            <button type="button" class="zf-rowhead" data-zf-toggle>
                <span class="zf-rowlabel"><?= t('Status') ?></span>
                <i class="fa fa-angle-right zf-caret" aria-hidden="true"></i>
            </button>
            <div class="zf-rowbody" hidden>
                <?php foreach ($columns as $columnId => $columnTitle): ?>
                    <label class="zf-check">
                        <input type="checkbox" data-zf-value value="<?= $this->text->e($columnTitle) ?>">
                        <span class="zs-dot <?= $this->text->e($column_classes[$columnId]) ?>"></span>
                        <?= $this->text->e($columnTitle) ?>
                    </label>
                <?php endforeach ?>
            </div>
        </section>

        <!-- Open / closed ------------------------------------------------ -->
        <section class="zf-row" data-zf-field="status" data-zf-label="<?= t('Task state') ?>">
            <button type="button" class="zf-rowhead" data-zf-toggle>
                <span class="zf-rowlabel"><?= t('Task state') ?></span>
                <i class="fa fa-angle-right zf-caret" aria-hidden="true"></i>
            </button>
            <div class="zf-rowbody" hidden>
                <label class="zf-check"><input type="checkbox" data-zf-value value="open"> <?= t('Open') ?></label>
                <label class="zf-check"><input type="checkbox" data-zf-value value="closed"> <?= t('Closed') ?></label>
            </div>
        </section>

        <!-- Owner -------------------------------------------------------- -->
        <section class="zf-row" data-zf-field="assignee" data-zf-label="<?= t('Owner') ?>">
            <button type="button" class="zf-rowhead" data-zf-toggle>
                <span class="zf-rowlabel"><?= t('Owner') ?></span>
                <i class="fa fa-angle-right zf-caret" aria-hidden="true"></i>
            </button>
            <div class="zf-rowbody" hidden>
                <label class="zf-check"><input type="checkbox" data-zf-value value="nobody"> <?= t('Unassigned') ?></label>
                <?php foreach ($filter_users as $userId => $userName): ?>
                    <?php if ($userId === \Kanboard\Model\UserModel::EVERYBODY_ID) { continue; } ?>
                    <?php /* The id, not the name: TaskAssigneeFilter matches a
                             numeric value straight against owner_id, where a
                             name would be a fuzzy ilike on two columns. */ ?>
                    <label class="zf-check">
                        <input type="checkbox" data-zf-value value="<?= (int) $userId ?>">
                        <?= $this->text->e($userName) ?>
                    </label>
                <?php endforeach ?>
            </div>
        </section>

        <!-- Priority ----------------------------------------------------- -->
        <section class="zf-row" data-zf-field="priority" data-zf-label="<?= t('Priority') ?>">
            <button type="button" class="zf-rowhead" data-zf-toggle>
                <span class="zf-rowlabel"><?= t('Priority') ?></span>
                <i class="fa fa-angle-right zf-caret" aria-hidden="true"></i>
            </button>
            <div class="zf-rowbody" hidden>
                <?php foreach ($filter_priorities as $value => $label): ?>
                    <label class="zf-check">
                        <input type="checkbox" data-zf-value value="<?= (int) $value ?>">
                        <span class="zg-priority <?= $this->taskTree->getPriorityClass($value) ?>"><?= $this->text->e($label) ?></span>
                    </label>
                <?php endforeach ?>
            </div>
        </section>

        <!-- Start date --------------------------------------------------- -->
        <section class="zf-row" data-zf-field="started" data-zf-label="<?= t('Start date') ?>" data-zf-kind="range">
            <button type="button" class="zf-rowhead" data-zf-toggle>
                <span class="zf-rowlabel"><?= t('Start date') ?></span>
                <i class="fa fa-angle-right zf-caret" aria-hidden="true"></i>
            </button>
            <div class="zf-rowbody" hidden>
                <label class="zf-daterow"><span><?= t('From') ?></span><input type="date" data-zf-from></label>
                <label class="zf-daterow"><span><?= t('To') ?></span><input type="date" data-zf-to></label>
            </div>
        </section>

        <!-- Due date ----------------------------------------------------- -->
        <section class="zf-row" data-zf-field="due" data-zf-label="<?= t('Due Date') ?>" data-zf-kind="range">
            <button type="button" class="zf-rowhead" data-zf-toggle>
                <span class="zf-rowlabel"><?= t('Due Date') ?></span>
                <i class="fa fa-angle-right zf-caret" aria-hidden="true"></i>
            </button>
            <div class="zf-rowbody" hidden>
                <label class="zf-daterow"><span><?= t('From') ?></span><input type="date" data-zf-from></label>
                <label class="zf-daterow"><span><?= t('To') ?></span><input type="date" data-zf-to></label>
            </div>
        </section>

        <!-- Tags --------------------------------------------------------- -->
        <?php if (! empty($filter_tags)): ?>
            <section class="zf-row" data-zf-field="tag" data-zf-label="<?= t('Tags') ?>">
                <button type="button" class="zf-rowhead" data-zf-toggle>
                    <span class="zf-rowlabel"><?= t('Tags') ?></span>
                    <i class="fa fa-angle-right zf-caret" aria-hidden="true"></i>
                </button>
                <div class="zf-rowbody" hidden>
                    <?php foreach ($filter_tags as $tag): ?>
                        <label class="zf-check">
                            <input type="checkbox" data-zf-value value="<?= $this->text->e($tag['name']) ?>">
                            <?= $this->text->e($tag['name']) ?>
                        </label>
                    <?php endforeach ?>
                </div>
            </section>
        <?php endif ?>

    </div>

    <footer class="zf-foot">
        <div class="zf-match">
            <label><input type="radio" name="zf-match" value="any" data-zf-match> <?= t('Any of these') ?></label>
            <label><input type="radio" name="zf-match" value="all" data-zf-match checked> <?= t('All of these') ?></label>
        </div>
        <div class="zf-buttons">
            <button type="button" class="zf-find" data-zf-find><?= t('Find') ?></button>
            <button type="button" class="zf-cancel" data-zf-cancel><?= t('Cancel') ?></button>
        </div>
    </footer>
</aside>
