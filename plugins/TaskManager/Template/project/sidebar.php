<li <?= $this->app->checkMenuSelection('TaskManagerConfigController') ? 'class="active"' : '' ?>>
    <?= $this->url->icon('clock-o', t('Scheduling'), 'TaskManagerConfigController', 'show', array('project_id' => $project['id'], 'plugin' => 'TaskManager')) ?>
</li>
