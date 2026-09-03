<?php if ($can_edit): ?>
    <div class="zp-tabaction">
        <?= $this->modal->medium('comment-o', t('Add a comment'), 'CommentController', 'create', array('task_id' => $task['id'], 'project_id' => $task['project_id'])) ?>
    </div>
<?php endif ?>

<?php if (empty($comments)): ?>
    <p class="zp-empty"><?= t('There is no comment.') ?></p>
<?php else: ?>
    <ul class="zp-list">
        <?php foreach ($comments as $comment): ?>
            <li>
                <div class="zp-list-head">
                    <strong><?= $this->text->e($comment['name'] ?: $comment['username']) ?></strong>
                    <span><?= $this->dt->datetime($comment['date_creation']) ?></span>
                </div>
                <div class="zp-list-body"><?= $this->text->markdown($comment['comment']) ?></div>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
