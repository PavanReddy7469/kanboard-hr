<section id="main">
    <div class="page-header" style="margin-bottom: 20px;">
        <h2><?= $title ?></h2>
    </div>
    <form id="project-creation-form" method="post" action="<?= $this->url->href('ProjectCreationController', 'save') ?>" autocomplete="off">

        <?= $this->form->csrf() ?>
        <?= $this->form->hidden('is_private', $values) ?>

        <div style="margin-bottom: 16px;">
            <?= $this->form->label(t('Project Name'), 'name', array('style' => 'font-weight: 700; color: #0f172a; margin-bottom: 6px;')) ?>
            <?= $this->form->text('name', $values, $errors, array('autofocus', 'required', 'placeholder' => t('e.g. SUPERBEE Drone Defense System'), 'maxlength="255"')) ?>
        </div>

        <div style="margin-bottom: 16px;">
            <?= $this->form->label(t('Project Code (4-digit alphanumeric)'), 'identifier', array('style' => 'font-weight: 700; color: #0f172a; margin-bottom: 6px;')) ?>
            <?= $this->form->text('identifier', $values, $errors, array('placeholder' => t('Auto-generated if left blank (e.g. SB03)'), 'maxlength="4"', 'style' => 'text-transform: uppercase; font-family: monospace; font-weight: 700;')) ?>
            <p class="form-help"><?= t('Unique 4-digit alphanumeric identifier for this project. If left empty, one will be generated automatically.') ?></p>
        </div>

        <div style="margin-bottom: 16px;">
            <?= $this->form->label(t('Owner'), 'owner_id', array('style' => 'font-weight: 700; color: #0f172a; margin-bottom: 6px;')) ?>
            <?= $this->form->select('owner_id', $users_list, $values, $errors) ?>
            <p class="form-help"><?= t('Who is accountable for this project. Shown in the Owner column on the Projects list.') ?></p>
        </div>

        <div style="margin-bottom: 16px;">
            <?= $this->form->label(t('Description'), 'description', array('style' => 'font-weight: 700; color: #0f172a; margin-bottom: 6px;')) ?>
            <?= $this->form->textarea('description', $values, $errors, array('placeholder' => t('Brief overview of the project scope and deliverables...'), 'style' => 'height: 90px; resize: vertical;')) ?>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
            <div>
                <?= $this->form->label(t('Start Date'), 'start_date', array('style' => 'font-weight: 700; color: #0f172a; margin-bottom: 6px;')) ?>
                <?= $this->form->text('start_date', $values, $errors, array('placeholder' => 'YYYY-MM-DD', 'class' => 'form-date')) ?>
            </div>
            <div>
                <?= $this->form->label(t('End Date'), 'end_date', array('style' => 'font-weight: 700; color: #0f172a; margin-bottom: 6px;')) ?>
                <?= $this->form->text('end_date', $values, $errors, array('placeholder' => 'YYYY-MM-DD', 'class' => 'form-date')) ?>
            </div>
        </div>

        <?= $this->hook->render('template:project:creation:form', array('values' => $values, 'errors' => $errors)) ?>

        <div class="form-actions" style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #f1f5f9; display: flex; align-items: center; gap: 10px;">
            <button type="submit" class="btn btn-blue" style="background: #4f46e5; color: white; font-weight: 700; padding: 9px 24px; border-radius: 8px; border: none; cursor: pointer;">
                <i class="fa fa-plus"></i> <?= t('Create Project') ?>
            </button>
            <a href="#" class="js-modal-close btn" style="background: #f1f5f9; color: #475569; padding: 8px 18px; border-radius: 8px; border: 1px solid #cbd5e1; text-decoration: none; font-weight: 600;">
                <?= t('Cancel') ?>
            </a>
        </div>
    </form>
</section>
