<?php

namespace Kanboard\Plugin\TaskManager\Console;

use Kanboard\Console\BaseCommand;
use Kanboard\Plugin\TaskManager\Model\ReminderModel;
use Kanboard\Core\Security\Role;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The daily reminder run.
 *
 *     ./cli taskmanager:reminders
 *     ./cli taskmanager:reminders --dry-run --show
 *
 * --dry-run sends nothing, records nothing, and prints what it would have
 * done. It exists because the first question anybody asks about a job that
 * mails thirty people is "what is it going to send?", and the only honest
 * way to answer that is to let them look before it goes out.
 *
 * @package Kanboard\Plugin\TaskManager\Console
 */
class SendRemindersCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('taskmanager:reminders')
            ->setDescription('Email task start, due-soon and due-today reminders, and project end dates')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be sent without sending or recording anything')
            ->addOption('show', null, InputOption::VALUE_NONE, 'Print a table of what was selected')
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Pretend today is this date (Y-m-d), for testing')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');

        /* An explicit clock, so a run can be rehearsed against a date in the
           future without waiting for it to arrive. */
        $now = time();

        if ($input->getOption('date')) {
            $parsed = strtotime($input->getOption('date').' 09:00:00');

            if ($parsed === false) {
                $output->writeln('<error>--date must be Y-m-d</error>');
                return 1;
            }

            $now = $parsed;
        }

        list($todayStart) = ReminderModel::dayBounds($now);
        $today = date('Y-m-d', $todayStart);

        $rows = array();
        $rows = array_merge($rows, $this->handleTasks($now, $today, $dryRun));
        $rows = array_merge($rows, $this->handleProjects($now, $today, $dryRun));

        if ($input->getOption('show') || $dryRun) {
            $this->showTable($output, $rows, $today, $dryRun);
        }

        if (! $dryRun) {
            $this->reminderModel->purgeOlderThan($now);
        }

        return 0;
    }

    /**
     * @return array rows for the table
     */
    protected function handleTasks($now, $today, $dryRun)
    {
        $rows = array();

        foreach ($this->reminderModel->getCandidateTasks() as $task) {
            $kind = ReminderModel::classify($task, $now);

            if ($kind === '') {
                continue;
            }

            $userId = (int) $task['owner_id'];

            /* Asked before sending rather than after, so a run that dies
               halfway through does not re-send to the people it already
               reached when somebody restarts it. */
            if (! $dryRun && $this->reminderModel->alreadySent($task['id'], $userId, $kind, $today)) {
                continue;
            }

            $sent = $dryRun ? true : $this->mailerModel->sendTaskReminder($task, $kind, $now);

            if ($sent && ! $dryRun) {
                $this->reminderModel->markSent($task['id'], $userId, $kind, $today);
            }

            $rows[] = array(
                '#'.$task['id'],
                $task['title'],
                $kind,
                $task['project_name'],
                $task['assignee_name'] ?: $task['assignee_username'],
                $sent ? 'yes' : 'no (opted out or no address)',
            );
        }

        return $rows;
    }

    /**
     * Project end dates go to the people who manage the project, not to
     * everyone in it - it is a scheduling fact, and the assignees already
     * get told about their own tasks.
     *
     * @return array
     */
    protected function handleProjects($now, $today, $dryRun)
    {
        $rows = array();

        foreach ($this->reminderModel->getProjectsEndingSoon($now) as $project) {
            $endTimestamp = strtotime($project['end_date'].' 00:00:00');

            if ($endTimestamp === false) {
                continue;
            }

            $days = ReminderModel::daysUntil($endTimestamp, $now);

            foreach ($this->managersOf($project['id']) as $manager) {
                if (! $dryRun && $this->reminderModel->alreadySent($project['id'], $manager['id'], ReminderModel::KIND_PROJECT_END, $today)) {
                    continue;
                }

                $sent = $dryRun ? true : $this->mailerModel->sendProjectEnding($project, $manager, $days);

                if ($sent && ! $dryRun) {
                    $this->reminderModel->markSent($project['id'], $manager['id'], ReminderModel::KIND_PROJECT_END, $today);
                }

                $rows[] = array(
                    'P'.$project['id'],
                    $project['name'],
                    ReminderModel::KIND_PROJECT_END,
                    $project['end_date'],
                    $manager['name'] ?: $manager['username'],
                    $sent ? 'yes' : 'no (opted out or no address)',
                );
            }
        }

        return $rows;
    }

    /**
     * Everyone who manages a project.
     *
     * The custom roles in this installation ("Manager", "Team Lead") are
     * stored under their own names and are not Role::PROJECT_MANAGER, so
     * asking only about the built-in role would find nobody here. This is
     * the same group AuthorityHelper calls management.
     *
     * @param  integer $projectId
     * @return array
     */
    protected function managersOf($projectId)
    {
        $managers = array();
        $management = \Kanboard\Plugin\TaskManager\Helper\AuthorityHelper::managementRoles();

        /* getUsers() rather than getAllUsers(): the latter runs the result
           through prepareList() and hands back id => "Full Name", which has
           no role on it to test. Group members are merged in by hand for
           the same reason. */
        $members = array_merge(
            $this->projectUserRoleModel->getUsers($projectId),
            $this->projectGroupRoleModel->getUsers($projectId)
        );

        foreach ($members as $member) {
            if (isset($member['role']) && in_array($member['role'], $management, true)) {
                /* Re-read the whole row: these columns carry no
                   notifications_enabled, and MailerModel treats a missing
                   flag as "no consent" rather than guessing. */
                $managers[(int) $member['id']] = $this->userModel->getById($member['id']);
            }
        }

        /* A project with no manager among its members would otherwise tell
           nobody its end date. The owner is the one person always attached
           to it. */
        if (empty($managers)) {
            $project = $this->projectModel->getById($projectId);

            if (! empty($project['owner_id'])) {
                $managers[(int) $project['owner_id']] = $this->userModel->getById($project['owner_id']);
            }
        }

        return array_values(array_filter($managers));
    }

    protected function showTable(OutputInterface $output, array $rows, $today, $dryRun)
    {
        $output->writeln(sprintf('<info>%s for %s</info>', $dryRun ? 'Dry run' : 'Reminder run', $today));

        if (empty($rows)) {
            $output->writeln('Nothing to send today.');
            return;
        }

        $table = new Table($output);
        $table->setHeaders(array('Id', 'Title', 'Reminder', 'Project / date', 'Recipient', 'Sent'))
              ->setRows($rows)
              ->render();
    }
}
