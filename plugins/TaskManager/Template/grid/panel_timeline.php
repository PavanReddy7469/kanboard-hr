<?php if (empty($transitions)): ?>
    <p class="zp-empty"><?= t('This task has not moved between columns yet.') ?></p>
<?php else: ?>
    <ul class="zp-timeline">
        <?php foreach ($transitions as $transition): ?>
            <li>
                <span class="zp-timeline-when"><?= $this->dt->datetime($transition['date']) ?></span>
                <span class="zp-timeline-what">
                    <?= $this->text->e($transition['src_column']) ?>
                    <i class="fa fa-long-arrow-right" aria-hidden="true"></i>
                    <strong><?= $this->text->e($transition['dst_column']) ?></strong>
                </span>
                <span class="zp-timeline-who"><?= $this->text->e($transition['name'] ?: $transition['username']) ?></span>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
