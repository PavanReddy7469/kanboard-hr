<?php
/* Zoho's footer: the range, the total, and prev/next. $base carries the
   view's own state so paging never loses the filter, sort or grouping. */
?>
<div class="zg-footer">
    <span class="zg-count">
        <?php if ($page['total'] > 0): ?>
            <?= t('Total Count: %d', $page['total']) ?>
        <?php else: ?>
            <?= t('Total Count: 0') ?>
        <?php endif ?>
    </span>

    <?php if ($page['pages'] > 1): ?>
        <span class="zg-pager">
            <span class="zg-range"><?= $page['from'] ?>&ndash;<?= $page['to'] ?></span>

            <?php if ($page['has_prev']): ?>
                <a class="zg-pagebtn" href="<?= $this->url->href($controller, 'show', $base + array('page' => $page['page'] - 1)) ?>" title="<?= t('Previous page') ?>">
                    <i class="fa fa-chevron-left" aria-hidden="true"></i>
                </a>
            <?php else: ?>
                <span class="zg-pagebtn is-disabled"><i class="fa fa-chevron-left" aria-hidden="true"></i></span>
            <?php endif ?>

            <?php if ($page['has_next']): ?>
                <a class="zg-pagebtn" href="<?= $this->url->href($controller, 'show', $base + array('page' => $page['page'] + 1)) ?>" title="<?= t('Next page') ?>">
                    <i class="fa fa-chevron-right" aria-hidden="true"></i>
                </a>
            <?php else: ?>
                <span class="zg-pagebtn is-disabled"><i class="fa fa-chevron-right" aria-hidden="true"></i></span>
            <?php endif ?>
        </span>
    <?php endif ?>
</div>
