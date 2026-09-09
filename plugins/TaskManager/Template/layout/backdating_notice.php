<?php
/* Shows why a save was refused for being backdated.
 *
 * Core's controllers overwrite the flash with a generic "unable to save"
 * straight after the model returns false, so the real reason travels in its
 * own session slot instead. Read once, then cleared, so it appears on the
 * page the user lands on and never again.
 */

$sbBackdating = (string) session_get('sb_backdating_message');

if ($sbBackdating !== ''):
    session_set('sb_backdating_message', '');
?>
<div class="sb-backdating-notice" role="alert">
    <i class="fa fa-calendar-times-o" aria-hidden="true"></i>
    <span><?= $this->text->e($sbBackdating) ?></span>
</div>
<?php endif ?>
