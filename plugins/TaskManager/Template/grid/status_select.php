<?php
/* Zoho's inline pill: a button that opens a searchable list.
   $options is id => label; $current is the selected id.

   Two things are picked this way now - a status and an assignee - so the
   widget takes a $variant. A status carries a coloured dot standing for the
   column it is in; a person does not, and a dot beside a name would be
   inventing a meaning. The owner variant shows a person mark instead.

   Everything else is identical, including the script that drives it: it
   reads the URL off the chosen option and repaints the pill from whatever
   the server sends back, so it never needs to know which field it is
   setting. */
$token   = $this->app->getToken()->getReusableCSRFToken();
$variant = isset($variant) ? $variant : 'status';
$isOwner = $variant === 'owner';
?>
<span class="zs <?= $isOwner ? 'zs--owner' : '' ?> <?= empty($editable) ? 'is-readonly' : '' ?>">
    <button type="button" class="zs-pill <?= $this->text->e($current_class) ?>" data-zs-toggle <?= empty($editable) ? 'disabled' : '' ?>>
        <?php if ($isOwner): ?>
            <i class="fa fa-user-circle-o zs-face" aria-hidden="true"></i>
        <?php else: ?>
            <span class="zs-dot"></span>
        <?php endif ?>
        <span class="zs-label"><?= $this->text->e($current_label) ?></span>
        <?php if (! empty($editable)): ?><i class="fa fa-caret-down" aria-hidden="true"></i><?php endif ?>
    </button>

    <?php if (! empty($editable)): ?>
        <span class="zs-menu <?= $isOwner ? 'zs-menu--owner' : '' ?>" hidden>
            <span class="zs-searchwrap">
                <i class="fa fa-search" aria-hidden="true"></i>
                <input type="text" class="zs-search" placeholder="<?= t('Search') ?>" aria-label="<?= t('Search') ?>">
            </span>
            <span class="zs-options">
                <?php foreach ($options as $id => $label): ?>
                    <button type="button"
                            class="zs-option <?= $id == $current ? 'is-current' : '' ?>"
                            data-zs-value="<?= $this->text->e($id) ?>"
                            data-zs-label="<?= $this->text->e($label) ?>"
                            data-zs-url="<?= $this->text->e(sprintf($url_template, urlencode($id), $token)) ?>">
                        <?php if ($isOwner): ?>
                            <i class="fa <?= (int) $id === 0 ? 'fa-user-times' : 'fa-user-circle-o' ?> zs-face" aria-hidden="true"></i>
                        <?php else: ?>
                            <span class="zs-dot <?= $this->text->e($option_classes[$id]) ?>"></span>
                        <?php endif ?>
                        <?= $this->text->e($label) ?>
                    </button>
                <?php endforeach ?>
            </span>
        </span>
    <?php endif ?>
</span>
