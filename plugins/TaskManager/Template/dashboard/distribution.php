<div class="sb-card sb-dash-dist">
    <div class="sb-card-head">
        <h3><?= t('Status distribution') ?></h3>
        <span class="sb-card-note"><?= t('%d task(s)', $distribution['total']) ?></span>
    </div>
    <div class="sb-card-body">
        <?php if ($distribution['total'] === 0): ?>
            <p class="sb-empty"><?= t('No tasks yet.') ?></p>
        <?php else: ?>
            <div class="sb-stack" role="img" aria-label="<?= t('Task distribution across columns') ?>">
                <?php foreach ($distribution['columns'] as $column): ?>
                    <?php if ($column['total'] > 0): ?>
                        <span class="sb-stack-seg <?= $column['class'] ?>"
                              style="width: <?= $column['percent'] ?>%"
                              title="<?= $this->text->e($column['title']) ?> — <?= $column['total'] ?>"></span>
                    <?php endif ?>
                <?php endforeach ?>
            </div>

            <ul class="sb-legend">
                <?php foreach ($distribution['columns'] as $column): ?>
                    <li>
                        <span class="sb-legend-dot <?= $column['class'] ?>"></span>
                        <span class="sb-legend-name"><?= $this->text->e($column['title']) ?></span>
                        <span class="sb-legend-value"><?= $column['total'] ?></span>
                        <span class="sb-legend-pct"><?= $column['percent'] ?>%</span>
                    </li>
                <?php endforeach ?>
            </ul>
        <?php endif ?>
    </div>
</div>
