<?php

namespace Kanboard\Plugin\TaskManager\Schema;

use PDO;

const VERSION = 8;

function version_1(PDO $pdo)
{
    $pdo->exec('UPDATE projects SET priority_start = 1 WHERE priority_start < 1');
    $pdo->exec('UPDATE projects SET priority_end = 10 WHERE priority_end < 10');
    $pdo->exec('UPDATE projects SET priority_default = 5 WHERE priority_default < priority_start OR priority_default > priority_end');
    $pdo->exec('UPDATE tasks SET priority = 5 WHERE priority < 1');
}

function version_2(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE taskmanager_milestones (
            id            INT NOT NULL AUTO_INCREMENT,
            project_id    INT NOT NULL,
            title         VARCHAR(255) NOT NULL,
            description   TEXT,
            date_start    INT DEFAULT 0,
            date_due      INT DEFAULT 0,
            date_reached  INT DEFAULT 0,
            owner_id      INT DEFAULT 0,
            position      INT DEFAULT 1,
            is_active     TINYINT(1) DEFAULT 1,
            PRIMARY KEY(id),
            INDEX taskmanager_milestones_project_idx (project_id),
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE taskmanager_task_lists (
            id            INT NOT NULL AUTO_INCREMENT,
            project_id    INT NOT NULL,
            milestone_id  INT DEFAULT 0,
            title         VARCHAR(255) NOT NULL,
            position      INT DEFAULT 1,
            PRIMARY KEY(id),
            INDEX taskmanager_task_lists_project_idx (project_id),
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB CHARSET=utf8mb4
    ");

    $pdo->exec('ALTER TABLE tasks ADD COLUMN milestone_id INT DEFAULT 0');
    $pdo->exec('ALTER TABLE tasks ADD COLUMN task_list_id INT DEFAULT 0');
    $pdo->exec('ALTER TABLE subtasks ADD COLUMN date_due INT DEFAULT 0');
}

function version_3(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE taskmanager_dependencies (
            id               INT NOT NULL AUTO_INCREMENT,
            project_id       INT NOT NULL,
            task_id          INT NOT NULL,
            depends_on_id    INT NOT NULL,
            dependency_type  VARCHAR(2) NOT NULL DEFAULT 'FS',
            lag_days         INT DEFAULT 0,
            date_creation    INT DEFAULT 0,
            PRIMARY KEY(id),
            UNIQUE KEY taskmanager_dependencies_pair (task_id, depends_on_id),
            INDEX taskmanager_dependencies_pred_idx (depends_on_id),
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY(depends_on_id) REFERENCES tasks(id) ON DELETE CASCADE
        ) ENGINE=InnoDB CHARSET=utf8mb4
    ");
}

function version_4(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE taskmanager_timesheets (
            id            INT NOT NULL AUTO_INCREMENT,
            user_id       INT NOT NULL,
            project_id    INT NOT NULL,
            week_start    INT NOT NULL,
            state         VARCHAR(16) NOT NULL DEFAULT 'draft',
            approver_id   INT DEFAULT 0,
            decided_at    INT DEFAULT 0,
            note          TEXT,
            PRIMARY KEY(id),
            UNIQUE KEY taskmanager_timesheets_week (user_id, project_id, week_start),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE taskmanager_time_entries (
            id            INT NOT NULL AUTO_INCREMENT,
            user_id       INT NOT NULL,
            task_id       INT NOT NULL,
            project_id    INT NOT NULL,
            timesheet_id  INT DEFAULT 0,
            work_date     INT NOT NULL,
            hours         DECIMAL(6,2) NOT NULL DEFAULT 0,
            is_billable   TINYINT(1) DEFAULT 1,
            note          TEXT,
            date_creation INT DEFAULT 0,
            PRIMARY KEY(id),
            INDEX taskmanager_time_entries_week_idx (user_id, project_id, work_date),
            INDEX taskmanager_time_entries_task_idx (task_id),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB CHARSET=utf8mb4
    ");
}

/**
 * Seed the SUPERBEE project roles onto every existing project.
 *
 * Manager and Team Lead are unrestricted; Engineer loses task creation,
 * deletion and reassignment of other people's tasks. Kanboard's restrictions
 * are negative, so "no rows" means "no limits".
 *
 * Nobody is reassigned. Existing members keep the role they have today, so
 * permissions do not change until someone is deliberately moved onto one of
 * these roles under Project settings > Permissions.
 */
function version_5(PDO $pdo)
{
    $blueprint = array(
        'Manager'   => array(),
        'Team Lead' => array(),
        'Engineer'  => array('task_creation', 'task_remove', 'task_update_assigned'),
    );

    $projects = $pdo->query('SELECT id FROM projects')->fetchAll(PDO::FETCH_COLUMN, 0);

    $findRole   = $pdo->prepare('SELECT role_id FROM project_has_roles WHERE project_id = ? AND role = ?');
    $insertRole = $pdo->prepare('INSERT INTO project_has_roles (project_id, role) VALUES (?, ?)');
    $insertRule = $pdo->prepare('INSERT INTO project_role_has_restrictions (project_id, role_id, rule) VALUES (?, ?, ?)');

    foreach ($projects as $projectId) {
        foreach ($blueprint as $role => $rules) {
            $findRole->execute(array($projectId, $role));

            if ($findRole->fetchColumn() !== false) {
                continue;
            }

            $insertRole->execute(array($projectId, $role));

            // Read the id back rather than lastInsertId(), which needs an
            // explicit sequence name on Postgres.
            $findRole->execute(array($projectId, $role));
            $roleId = (int) $findRole->fetchColumn();

            foreach ($rules as $rule) {
                $insertRule->execute(array($projectId, $roleId, $rule));
            }
        }
    }
}


/**
 * Deliverables: the link an assignee submits as proof a task is finished, and
 * the approval an admin or project manager gives it.
 *
 * The work itself lives in Google Drive, a repo, a live URL - anywhere. This
 * table records the pointer and the decision, not the document, so nothing is
 * uploaded to or stored on the Kanboard host.
 *
 * A task can carry several submissions over time (rejected, resubmitted); the
 * gate in TaskStatusModel asks only whether at least one is approved.
 */
function version_6(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE taskmanager_deliverables (
            id             INT NOT NULL AUTO_INCREMENT,
            task_id        INTEGER NOT NULL,
            project_id     INTEGER NOT NULL,
            user_id        INTEGER NOT NULL,
            title          TEXT,
            url            TEXT NOT NULL,
            note           TEXT,
            status         VARCHAR(20) NOT NULL DEFAULT 'pending',
            reviewer_id    INTEGER DEFAULT 0,
            review_note    TEXT,
            date_submitted INTEGER DEFAULT 0,
            date_reviewed  INTEGER DEFAULT 0,
            FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            PRIMARY KEY(id)
        ) ENGINE=InnoDB CHARSET=utf8mb4
    ");

    $pdo->exec('CREATE INDEX taskmanager_deliverables_task_idx ON taskmanager_deliverables(task_id)');
    $pdo->exec('CREATE INDEX taskmanager_deliverables_project_idx ON taskmanager_deliverables(project_id, status)');
}

function version_7(PDO $pdo)
{
    // Employee ID, shown alongside the name as "NAME (EMP ID)" wherever a
    // person is picked or displayed. Nullable and unconstrained: existing
    // accounts predate it, and not every account is an employee.
    $pdo->exec("ALTER TABLE users ADD COLUMN employee_id VARCHAR(50) DEFAULT NULL");
    $pdo->exec("CREATE INDEX users_employee_idx ON users(employee_id)");
}

function version_8(PDO $pdo)
{
    // Subtasks already carry a due date; they gain a start date so the pair
    // reads the same way it does on a task. Both are plain timestamps, 0 when
    // unset, matching how date_due is already stored.
    $pdo->exec("ALTER TABLE subtasks ADD COLUMN date_started INT DEFAULT 0");
}
