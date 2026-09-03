<?php if ($can_edit): ?>
    <div class="zp-tabaction">
        <?= $this->modal->medium('upload', t('Attach a document'), 'TaskFileController', 'create', array('task_id' => $task['id'], 'project_id' => $task['project_id'])) ?>
    </div>
<?php endif ?>

<?php if (empty($files)): ?>
    <p class="zp-empty"><?= t('There is no document.') ?></p>
<?php else: ?>
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
<?php endif ?>
