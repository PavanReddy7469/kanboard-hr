<div class="page-header">
    <h2><?= t('New task list') ?></h2>
</div>

<form method="post" action="<?= $this->url->href('TaskGroupController', 'save', array('project_id' => $project['id'], 'plugin' => 'TaskManager')) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>
    <?= $this->form->hidden('project_id', $values) ?>

    <?= $this->form->label(t('Title'), 'title') ?>
    <?= $this->form->text('title', $values, $errors, array('autofocus', 'required', 'maxlength="255"', 'tabindex="1"')) ?>

    <?= $this->form->label(t('Milestone'), 'milestone_id') ?>
    <?= $this->form->select('milestone_id', $milestones, $values, $errors, array('tabindex="2"')) ?>

    <?= $this->modal->submitButtons() ?>
</form>
