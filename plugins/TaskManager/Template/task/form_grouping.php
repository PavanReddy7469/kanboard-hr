<?php
/**
 * Milestone and task-list selects, attached to the task form's second column.
 *
 * Attached through template:task:form:second-column, which both the creation
 * and the modification form render, so one template serves both and neither
 * core template is touched.
 *
 * @var array $values
 * @var array $errors
 */

$projectId = $this->taskGrouping->getProjectId($values);

if ($projectId > 0 && $this->taskGrouping->hasGroups($projectId)):
    $milestones = $this->taskGrouping->getMilestoneOptions($projectId);
    $taskLists  = $this->taskGrouping->getTaskListOptions($projectId);
?>
    <div class="tm-task-grouping">
        <?= $this->form->label(t('Milestone'), 'milestone_id') ?>
        <?= $this->form->select('milestone_id', $milestones, $values, $errors) ?>

        <?php if (count($taskLists) > 1): ?>
            <?= $this->form->label(t('Task list'), 'task_list_id') ?>
            <?= $this->form->select('task_list_id', $taskLists, $values, $errors) ?>
            <p class="form-help">
                <?= t('A task list already belongs to a milestone, so choosing one sets the milestone too.') ?>
            </p>
        <?php endif ?>
    </div>
<?php endif ?>
