<?php
/* Documents & references.
 *
 * Two different things live here, and the tab used to show only one of them.
 *
 *   Files      - uploaded and stored by us.
 *   References - links out to where the material actually lives: a shared
 *                drive, a specification, a repository, a drawing.
 *
 * Neither is completion evidence. That is the Reports tab, where a submission
 * is reviewed and gates the task closing. These are the materials you consult
 * while doing the work, and nobody has to approve them.
 */
?>
<?php if ($can_edit): ?>
    <div class="zp-docactions">
        <a class="zp-docbtn js-modal-medium"
           href="<?= $this->url->href('TaskExternalLinkController', 'find', array('task_id' => $task['id'], 'project_id' => $task['project_id'])) ?>">
            <i class="fa fa-link" aria-hidden="true"></i>
            <span>
                <strong><?= t('Add reference link') ?></strong>
                <em><?= t('Drive, spec, repository, drawing') ?></em>
            </span>
        </a>

        <a class="zp-docbtn js-modal-medium"
           href="<?= $this->url->href('TaskFileController', 'create', array('task_id' => $task['id'], 'project_id' => $task['project_id'])) ?>">
            <i class="fa fa-upload" aria-hidden="true"></i>
            <span>
                <strong><?= t('Upload a file') ?></strong>
                <em><?= t('Stored with this task') ?></em>
            </span>
        </a>
    </div>
<?php endif ?>

<?php if (empty($links) && empty($files)): ?>
    <p class="zp-empty"><?= t('No reference material yet. Add a link to where the document lives, or upload the file itself.') ?></p>
<?php endif ?>

<?php if (! empty($links)): ?>
    <div class="zp-docgroup">
        <h4 class="zp-docheading">
            <i class="fa fa-link" aria-hidden="true"></i>
            <?= t('Reference links') ?>
            <span class="zp-doccount"><?= count($links) ?></span>
        </h4>

        <ul class="zp-list">
            <?php foreach ($links as $link): ?>
                <li>
                    <div class="zp-list-head">
                        <a href="<?= $this->text->e($link['url']) ?>" target="_blank" rel="noreferrer noopener">
                            <?= $this->text->e($link['title'] !== '' ? $link['title'] : $link['url']) ?>
                            <i class="fa fa-external-link zp-extico" aria-hidden="true"></i>
                        </a>

                        <?php if ($can_edit): ?>
                            <span class="zp-docrowactions">
                                <?= $this->url->link('<i class="fa fa-pencil" aria-hidden="true"></i>', 'TaskExternalLinkController', 'edit', array('task_id' => $task['id'], 'project_id' => $task['project_id'], 'link_id' => $link['id']), false, 'js-modal-medium', t('Edit')) ?>
                                <?= $this->url->link('<i class="fa fa-times" aria-hidden="true"></i>', 'TaskExternalLinkController', 'confirm', array('task_id' => $task['id'], 'project_id' => $task['project_id'], 'link_id' => $link['id']), false, 'js-modal-confirm', t('Remove')) ?>
                            </span>
                        <?php endif ?>
                    </div>

                    <div class="zp-list-meta">
                        <span class="zp-docurl"><?= $this->text->e($link['url']) ?></span>
                    </div>
                </li>
            <?php endforeach ?>
        </ul>
    </div>
<?php endif ?>

<?php if (! empty($files)): ?>
    <div class="zp-docgroup">
        <h4 class="zp-docheading">
            <i class="fa fa-paperclip" aria-hidden="true"></i>
            <?= t('Uploaded files') ?>
            <span class="zp-doccount"><?= count($files) ?></span>
        </h4>

        <ul class="zp-list">
            <?php foreach ($files as $file): ?>
                <li>
                    <div class="zp-list-head">
                        <a href="<?= $this->url->href('FileViewerController', 'show', array('file_id' => $file['id'], 'project_id' => $task['project_id'])) ?>">
                            <?= $this->text->e($file['name']) ?>
                        </a>
                        <span><?= $this->dt->date($file['date']) ?></span>
                    </div>
                </li>
            <?php endforeach ?>
        </ul>
    </div>
<?php endif ?>
