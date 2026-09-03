<?php
/* Zoho's inline status control: a pill that opens a searchable list.
   $options is id => label; $current is the selected id. */
$token = $this->app->getToken()->getReusableCSRFToken();
?>
<span class="zs <?= empty($editable) ? 'is-readonly' : '' ?>">
    <button type="button" class="zs-pill <?= $this->text->e($current_class) ?>" data-zs-toggle <?= empty($editable) ? 'disabled' : '' ?>>
        <span class="zs-dot"></span>
        <span class="zs-label"><?= $this->text->e($current_label) ?></span>
        <?php if (! empty($editable)): ?><i class="fa fa-caret-down" aria-hidden="true"></i><?php endif ?>
    </button>

    <?php if (! empty($editable)): ?>
        <span class="zs-menu" hidden>
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
                        <span class="zs-dot <?= $this->text->e($option_classes[$id]) ?>"></span>
                        <?= $this->text->e($label) ?>
                    </button>
                <?php endforeach ?>
            </span>
        </span>
    <?php endif ?>
</span>
