<?php
/* Open-task line with a closed-per-day bar underneath. Inline SVG on purpose:
   no chart library, so nothing to keep in sync with a third-party upgrade. */
$points = $trend['points'];
$peak   = max(1, $trend['peak']);
$count  = max(1, count($points) - 1);
$w      = 100;
$h      = 46;
$line   = '';
$area   = '';

foreach ($points as $i => $point) {
    $x = round($i / $count * $w, 2);
    $y = round($h - ($point['open'] / $peak) * ($h - 4) - 2, 2);
    $line .= ($i === 0 ? 'M' : 'L').$x.' '.$y.' ';
}

$area = $line.'L'.$w.' '.$h.' L0 '.$h.' Z';

$closedPeak = 1;
foreach ($points as $point) {
    $closedPeak = max($closedPeak, $point['closed']);
}
?>
<div class="sb-card sb-dash-trend">
    <div class="sb-card-head">
        <h3><?= t('Last 30 days') ?></h3>
        <span class="sb-card-note"><?= t('Open tasks, peak %d', $peak) ?></span>
    </div>
    <div class="sb-card-body">
        <svg class="sb-spark" viewBox="0 0 <?= $w ?> <?= $h ?>" preserveAspectRatio="none" aria-hidden="true">
            <path class="sb-spark-area" d="<?= $area ?>"></path>
            <path class="sb-spark-line" d="<?= trim($line) ?>"></path>
        </svg>

        <div class="sb-bars" role="img" aria-label="<?= t('Tasks closed per day') ?>">
            <?php foreach ($points as $point): ?>
                <span class="sb-bar <?= $point['closed'] > 0 ? 'is-filled' : '' ?>"
                      style="height: <?= $point['closed'] > 0 ? max(8, round($point['closed'] / $closedPeak * 100)) : 3 ?>%"
                      title="<?= $this->dt->date($point['day']) ?> — <?= t('%d closed', $point['closed']) ?>"></span>
            <?php endforeach ?>
        </div>

        <div class="sb-spark-axis">
            <span><?= $this->dt->date($points[0]['day']) ?></span>
            <span><?= t('Closed per day') ?></span>
            <span><?= $this->dt->date($points[count($points) - 1]['day']) ?></span>
        </div>
    </div>
</div>
