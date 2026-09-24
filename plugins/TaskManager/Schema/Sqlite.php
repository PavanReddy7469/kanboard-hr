<?php

namespace Kanboard\Plugin\TaskManager\Schema;

use PDO;

const VERSION = 10;

/**
 * Put every project on the SUPERBEE P1-P10 scale.
 *
 * Kanboard's stock defaults (start 0, end 3, default 0) render a P0-P3
 * dropdown and create every task at P0.
 */
function version_1(PDO $pdo)
{
    $pdo->exec('UPDATE projects SET priority_start = 1 WHERE priority_start < 1');
    $pdo->exec('UPDATE projects SET priority_end = 10 WHERE priority_end < 10');
    $pdo->exec('UPDATE projects SET priority_default = 5 WHERE priority_default < priority_start OR priority_default > priority_end');
    $pdo->exec('UPDATE tasks SET priority = 5 WHERE priority < 1');
}

/**
 * Milestones and task lists: the two levels above Task in the hierarchy.
 *
 * Project > Milestone > Task list > Task > Subtask
 */
function version_2(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE taskmanager_milestones (
            id            INTEGER PRIMARY KEY,
            project_id    INTEGER NOT NULL,
            title         TEXT NOT NULL,
            description   TEXT,
            date_start    INTEGER DEFAULT 0,
            date_due      INTEGER DEFAULT 0,
            date_reached  INTEGER DEFAULT 0,
            owner_id      INTEGER DEFAULT 0,
            position      INTEGER DEFAULT 1,
            is_active     INTEGER DEFAULT 1,
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec('CREATE INDEX taskmanager_milestones_project_idx ON taskmanager_milestones(project_id)');

    $pdo->exec("
        CREATE TABLE taskmanager_task_lists (
            id            INTEGER PRIMARY KEY,
            project_id    INTEGER NOT NULL,
            milestone_id  INTEGER DEFAULT 0,
            title         TEXT NOT NULL,
            position      INTEGER DEFAULT 1,
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec('CREATE INDEX taskmanager_task_lists_project_idx ON taskmanager_task_lists(project_id)');

    $pdo->exec('ALTER TABLE tasks ADD COLUMN milestone_id INTEGER DEFAULT 0');
    $pdo->exec('ALTER TABLE tasks ADD COLUMN task_list_id INTEGER DEFAULT 0');
    $pdo->exec('ALTER TABLE subtasks ADD COLUMN date_due INTEGER DEFAULT 0');
}

/**
 * Typed task dependencies: FS / SS / FF / SF with a lag in days.
 *
 * Kanboard's own task_has_links stays where it is — it handles soft relations
 * ("relates to", "duplicates") well. Scheduling semantics need their own
 * table because a link row cannot carry a type or a lag.
 */
function version_3(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE taskmanager_dependencies (
            id               INTEGER PRIMARY KEY,
            project_id       INTEGER NOT NULL,
            task_id          INTEGER NOT NULL,
            depends_on_id    INTEGER NOT NULL,
            dependency_type  TEXT NOT NULL DEFAULT 'FS',
            lag_days         INTEGER DEFAULT 0,
            date_creation    INTEGER DEFAULT 0,
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY(depends_on_id) REFERENCES tasks(id) ON DELETE CASCADE,
            UNIQUE(task_id, depends_on_id)
        )
    ");

    $pdo->exec('CREATE INDEX taskmanager_dependencies_task_idx ON taskmanager_dependencies(task_id)');
    $pdo->exec('CREATE INDEX taskmanager_dependencies_pred_idx ON taskmanager_dependencies(depends_on_id)');
}

/**
 * Timesheets: task-level time entries wrapped in a weekly approval envelope.
 *
 * Kanboard's subtask_time_tracking stays where it is; it records timer runs
 * against subtasks and has no notion of billable hours or approval.
 */
function version_4(PDO $pdo)
{
    $pdo->exec("
        CREATE TABLE taskmanager_timesheets (
            id            INTEGER PRIMARY KEY,
            user_id       INTEGER NOT NULL,
            project_id    INTEGER NOT NULL,
            week_start    INTEGER NOT NULL,
            state         TEXT NOT NULL DEFAULT 'draft',
            approver_id   INTEGER DEFAULT 0,
            decided_at    INTEGER DEFAULT 0,
            note          TEXT,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
            UNIQUE(user_id, project_id, week_start)
        )
    ");

    $pdo->exec("
        CREATE TABLE taskmanager_time_entries (
            id            INTEGER PRIMARY KEY,
            user_id       INTEGER NOT NULL,
            task_id       INTEGER NOT NULL,
            project_id    INTEGER NOT NULL,
            timesheet_id  INTEGER DEFAULT 0,
            work_date     INTEGER NOT NULL,
            hours         REAL NOT NULL DEFAULT 0,
            is_billable   INTEGER DEFAULT 1,
            note          TEXT,
            date_creation INTEGER DEFAULT 0,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec('CREATE INDEX taskmanager_time_entries_week_idx ON taskmanager_time_entries(user_id, project_id, work_date)');
    $pdo->exec('CREATE INDEX taskmanager_time_entries_task_idx ON taskmanager_time_entries(task_id)');
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
        CREATE TABLE IF NOT EXISTS taskmanager_deliverables (
            id             INTEGER PRIMARY KEY,
            task_id        INTEGER NOT NULL,
            project_id     INTEGER NOT NULL,
            user_id        INTEGER NOT NULL,
            title          TEXT,
            url            TEXT NOT NULL,
            note           TEXT,
            status         TEXT NOT NULL DEFAULT 'pending',
            reviewer_id    INTEGER DEFAULT 0,
            review_note    TEXT,
            date_submitted INTEGER DEFAULT 0,
            date_reviewed  INTEGER DEFAULT 0,
            FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $pdo->exec('CREATE INDEX IF NOT EXISTS taskmanager_deliverables_task_idx ON taskmanager_deliverables(task_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS taskmanager_deliverables_project_idx ON taskmanager_deliverables(project_id, status)');
}

function version_7(PDO $pdo)
{
    // Employee ID, shown alongside the name as "NAME (EMP ID)" wherever a
    // person is picked or displayed. Nullable and unconstrained: existing
    // accounts predate it, and not every account is an employee.
    $pdo->exec("ALTER TABLE users ADD COLUMN employee_id TEXT DEFAULT NULL");
    $pdo->exec("CREATE INDEX IF NOT EXISTS users_employee_idx ON users(employee_id)");
}

function version_8(PDO $pdo)
{
    // Subtasks already carry a due date; they gain a start date so the pair
    // reads the same way it does on a task. Both are plain timestamps, 0 when
    // unset, matching how date_due is already stored.
    $pdo->exec("ALTER TABLE subtasks ADD COLUMN date_started INTEGER DEFAULT 0");
}

function version_9(PDO $pdo)
{
    /* Airframe type: MC (MultiCopter), FW (FixedWing), HI (HybridAircraft).
       It is what the project code is built from - MC01, MC02, FW01 - so it is
       stored rather than parsed back out of the code, which would break the
       moment someone typed a code by hand. Existing projects keep their random
       four-character codes and an empty type; nothing is rewritten, because a
       code already printed on a document or pasted into a link has to keep
       meaning the same project. */
    $pdo->exec("ALTER TABLE projects ADD COLUMN project_type TEXT DEFAULT ''");
    $pdo->exec("CREATE INDEX projects_type_idx ON projects(project_type)");
}

function version_10(PDO $pdo)
{
    /* Set when an account is created, or when an administrator resets
       somebody's password; cleared the moment that person sets one of their
       own. Everyone issued the onboarding default shares it until they change
       it, and nothing in Kanboard made them - this is what makes them.

       Existing accounts default to 0: they already chose their passwords, and
       locking the current users out of their own instance to prove a point
       would be a poor trade. */
    $pdo->exec("ALTER TABLE users ADD COLUMN must_change_password INTEGER NOT NULL DEFAULT 0");
}
