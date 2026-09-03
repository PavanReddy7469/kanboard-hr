-- Production Database Dump Script --
-- Generated for Kanboard Task Management --

-- Data for table `users` --
INSERT INTO `users` (`id`, `username`, `password`, `is_admin`, `is_ldap_user`, `name`, `email`, `google_id`, `github_id`, `notifications_enabled`, `timezone`, `language`, `disable_login_form`, `twofactor_activated`, `twofactor_secret`, `token`, `notifications_filter`, `nb_failed_login`, `lock_expiration_date`, `is_project_admin`, `gitlab_id`, `role`, `is_active`, `avatar_path`, `api_access_token`, `filter`, `theme`) VALUES (1, 'admin', '$2y$10$ctCXbKCzoDB3zH9EZJwP3OLD/BzC70/xvTx103wmUk/td1z9ueKcq', 1, 0, NULL, NULL, NULL, NULL, 0, NULL, NULL, 0, 0, NULL, '', 4, 0, 0, 0, NULL, 'app-admin', 1, NULL, NULL, NULL, 'light');

-- Data for table `projects` --
INSERT INTO `projects` (`id`, `name`, `is_active`, `token`, `last_modified`, `is_public`, `is_private`, `is_everybody_allowed`, `default_swimlane`, `show_default_swimlane`, `description`, `identifier`, `start_date`, `end_date`, `owner_id`, `priority_default`, `priority_start`, `priority_end`, `email`, `predefined_email_subjects`, `per_swimlane_task_limits`, `task_limit`, `enable_global_tags`) VALUES (1, 'Task1', 1, '', 1787818485, 0, 0, 0, 'Default swimlane', 1, NULL, '', '', '', 1, 0, 0, 3, NULL, NULL, 0, 0, 1);

-- Data for table `columns` --
INSERT INTO `columns` (`id`, `title`, `position`, `project_id`, `task_limit`, `description`, `hide_in_dashboard`) VALUES (1, 'Open', 1, 1, 0, NULL, 0);
INSERT INTO `columns` (`id`, `title`, `position`, `project_id`, `task_limit`, `description`, `hide_in_dashboard`) VALUES (2, 'WIP', 2, 1, 0, NULL, 0);
INSERT INTO `columns` (`id`, `title`, `position`, `project_id`, `task_limit`, `description`, `hide_in_dashboard`) VALUES (3, 'Rev', 3, 1, 0, NULL, 0);
INSERT INTO `columns` (`id`, `title`, `position`, `project_id`, `task_limit`, `description`, `hide_in_dashboard`) VALUES (4, 'Closed', 4, 1, 0, NULL, 0);

-- Data for table `project_has_users` --
INSERT INTO `project_has_users` (`project_id`, `user_id`, `is_owner`, `role`) VALUES (1, 1, 0, 'project-manager');

-- Data for table `tasks` --
INSERT INTO `tasks` (`id`, `title`, `description`, `date_creation`, `color_id`, `project_id`, `column_id`, `owner_id`, `position`, `is_active`, `date_completed`, `score`, `date_due`, `category_id`, `creator_id`, `date_modification`, `reference`, `date_started`, `time_spent`, `time_estimated`, `swimlane_id`, `date_moved`, `recurrence_status`, `recurrence_trigger`, `recurrence_factor`, `recurrence_timeframe`, `recurrence_basedate`, `recurrence_parent`, `recurrence_child`, `priority`, `external_provider`, `external_uri`) VALUES (1, 'Frontend Dashboard Development', NULL, 1787817971, 'blue', 1, 2, 1, 1, 1, NULL, NULL, 1788201000, 0, 1, 1787818350, '', 1787164200, 0, 14, NULL, 0, 0, 0, 0, 0, 0, NULL, NULL, 3, NULL, NULL);
INSERT INTO `tasks` (`id`, `title`, `description`, `date_creation`, `color_id`, `project_id`, `column_id`, `owner_id`, `position`, `is_active`, `date_completed`, `score`, `date_due`, `category_id`, `creator_id`, `date_modification`, `reference`, `date_started`, `time_spent`, `time_estimated`, `swimlane_id`, `date_moved`, `recurrence_status`, `recurrence_trigger`, `recurrence_factor`, `recurrence_timeframe`, `recurrence_basedate`, `recurrence_parent`, `recurrence_child`, `priority`, `external_provider`, `external_uri`) VALUES (2, 'Backend API Infrastructure', NULL, 1787817971, 'purple', 1, 1, 1, 1, 1, NULL, NULL, 1788719400, 0, 1, 1787818353, '', 1787509800, 0, 0, NULL, 0, 0, 0, 0, 0, 0, NULL, NULL, 2, NULL, NULL);
INSERT INTO `tasks` (`id`, `title`, `description`, `date_creation`, `color_id`, `project_id`, `column_id`, `owner_id`, `position`, `is_active`, `date_completed`, `score`, `date_due`, `category_id`, `creator_id`, `date_modification`, `reference`, `date_started`, `time_spent`, `time_estimated`, `swimlane_id`, `date_moved`, `recurrence_status`, `recurrence_trigger`, `recurrence_factor`, `recurrence_timeframe`, `recurrence_basedate`, `recurrence_parent`, `recurrence_child`, `priority`, `external_provider`, `external_uri`) VALUES (3, 'Quality Assurance Audit', NULL, 1787817971, 'yellow', 1, 3, 1, 1, 1, NULL, NULL, 1788373800, 0, 1, 1787818358, '', 1787682600, 0, 0, NULL, 0, 0, 0, 0, 0, 0, NULL, NULL, 1, NULL, NULL);
INSERT INTO `tasks` (`id`, `title`, `description`, `date_creation`, `color_id`, `project_id`, `column_id`, `owner_id`, `position`, `is_active`, `date_completed`, `score`, `date_due`, `category_id`, `creator_id`, `date_modification`, `reference`, `date_started`, `time_spent`, `time_estimated`, `swimlane_id`, `date_moved`, `recurrence_status`, `recurrence_trigger`, `recurrence_factor`, `recurrence_timeframe`, `recurrence_basedate`, `recurrence_parent`, `recurrence_child`, `priority`, `external_provider`, `external_uri`) VALUES (4, 'Project Deployment Signoff', NULL, 1787817971, 'green', 1, 4, 1, 1, 1, NULL, NULL, 1788719400, 0, 1, 1787818347, '', 1787941800, 0, 0, NULL, 0, 0, 0, 0, 0, 0, NULL, NULL, 2, NULL, NULL);

-- Data for table `subtasks` --
INSERT INTO `subtasks` (`id`, `title`, `status`, `time_estimated`, `time_spent`, `task_id`, `user_id`, `position`) VALUES (1, 'Design Responsive Layout', 2, 4, 0, 1, 1, 1);
INSERT INTO `subtasks` (`id`, `title`, `status`, `time_estimated`, `time_spent`, `task_id`, `user_id`, `position`) VALUES (2, 'Implement Gantt Chart Timeline', 2, 8, 0, 1, 1, 2);
INSERT INTO `subtasks` (`id`, `title`, `status`, `time_estimated`, `time_spent`, `task_id`, `user_id`, `position`) VALUES (3, 'Integrate Superbee Logo Header', 2, 2, 0, 1, 1, 3);
INSERT INTO `subtasks` (`id`, `title`, `status`, `time_estimated`, `time_spent`, `task_id`, `user_id`, `position`) VALUES (4, 'Database Schema Migration', 0, 3, 0, 2, 1, 1);
INSERT INTO `subtasks` (`id`, `title`, `status`, `time_estimated`, `time_spent`, `task_id`, `user_id`, `position`) VALUES (5, 'Role-Based Auth Middleware', 0, 5, 0, 2, 1, 2);
INSERT INTO `subtasks` (`id`, `title`, `status`, `time_estimated`, `time_spent`, `task_id`, `user_id`, `position`) VALUES (6, 'Execute End-to-End Test Suite', 1, 4, 0, 3, 1, 1);
INSERT INTO `subtasks` (`id`, `title`, `status`, `time_estimated`, `time_spent`, `task_id`, `user_id`, `position`) VALUES (7, 'Performance & Stress Testing', 0, 6, 0, 3, 1, 2);
INSERT INTO `subtasks` (`id`, `title`, `status`, `time_estimated`, `time_spent`, `task_id`, `user_id`, `position`) VALUES (8, 'Production Server Provisioning', 1, 3, 0, 4, 1, 1);
INSERT INTO `subtasks` (`id`, `title`, `status`, `time_estimated`, `time_spent`, `task_id`, `user_id`, `position`) VALUES (9, 'SSL Certificate & Domain Setup', 1, 2, 0, 4, 1, 2);

-- Data for table `comments` --
INSERT INTO `comments` (`id`, `task_id`, `user_id`, `date_creation`, `comment`, `reference`, `date_modification`, `visibility`) VALUES (1, 1, 1, 1787818499, 'hii', NULL, 1787818499, 'app-user');

