<div class="page-header">
    <h2><?= t('Edit milestone') ?></h2>
</div>

<form method="post" action="<?= $this->url->href('MilestoneController', 'update', array('project_id' => $project['id'], 'milestone_id' => $values['id'], 'plugin' => 'TaskManager')) ?>" autocomplete="off">
    <?= $this->form->csrf() ?>
    <?= $this->form->hidden('id', $values) ?>

    <?= $this->form->label(t('Title'), 'title') ?>
    <?= $this->form->text('title', $values, $errors, array('autofocus', 'required', 'maxlength="255"', 'tabindex="1"')) ?>

    <?= $this->form->label(t('Description'), 'description') ?>
    <?= $this->form->textEditor('description', $values, $errors, array('tabindex' => 2)) ?>

    <?= $this->form->label(t('Owner'), 'owner_id') ?>
    <?= $this->form->select('owner_id', $users, $values, $errors, array('tabindex="3"')) ?>

    <?= $this->form->date(t('Start date'), 'date_start', $values, $errors, array('tabindex="4"')) ?>

    <?= $this->form->date(t('Due date'), 'date_due', $values, $errors, array('tabindex="5"')) ?>

    <?= $this->modal->submitButtons() ?>
</form>
