<div class="page-header">
    <h2><?= t('Scheduling') ?></h2>
</div>

<form method="post" action="<?= $this->url->href('TaskManagerConfigController', 'save', array('project_id' => $project['id'], 'plugin' => 'TaskManager')) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>

    <?= $this->form->checkbox('auto_reschedule', t('Move dependent tasks automatically'), 1, $auto_reschedule == 1) ?>

    <p class="form-help">
        <?= t('When a task\'s dates change, shift the tasks that depend on it so their dependency still holds. Task length is preserved — bars move, they do not stretch.') ?>
    </p>

    <p class="alert alert-normal">
        <?= t('Off by default. With this on, moving one date can change dates on tasks other people own, without them doing anything. Try it on a single project first.') ?>
    </p>

    <div class="form-actions">
        <button type="submit" class="btn btn-blue"><?= t('Save') ?></button>
    </div>
</form>
