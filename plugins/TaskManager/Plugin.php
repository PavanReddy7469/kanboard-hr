<?php

namespace Kanboard\Plugin\TaskManager;

use Kanboard\Core\Plugin\Base;
use Kanboard\Plugin\TaskManager\Action\FlagOverdueTask;
use Kanboard\Plugin\TaskManager\Action\PriorityEscalation;
use Kanboard\Plugin\TaskManager\Subscriber\RescheduleSubscriber;

class Plugin extends Base
{
    public function initialize()
    {
        // Tree helper, used by the project overview hook template
        $this->helper->register('taskTree', '\Kanboard\Plugin\TaskManager\Helper\TaskTreeHelper');

        // P1..P10 labels on the priority dropdown, without touching app/Helper/TaskHelper.php
        $this->helper->register('task', '\Kanboard\Plugin\TaskManager\Helper\TaskHelper');
        $this->helper->register('subtask', '\Kanboard\Plugin\TaskManager\Helper\SubtaskHelper');
        $this->helper->register('dependency', '\Kanboard\Plugin\TaskManager\Helper\DependencyHelper');
        $this->helper->register('dashboard', '\Kanboard\Plugin\TaskManager\Helper\DashboardHelper');
        $this->helper->register('deliverable', '\Kanboard\Plugin\TaskManager\Helper\DeliverableHelper');

        /* Feeds the aircraft-type dropdown and the next-code preview on the
           project creation form, so that form reads a helper instead of core's
           ProjectCreationController growing another responsibility. */
        $this->helper->register('projectCode', '\Kanboard\Plugin\TaskManager\Helper\ProjectCodeHelper');

        /* Replaces core's 'user' helper so a person reads as "NAME (EMP ID)".
           UserModel::prepareList() builds every user dropdown through
           getFullname(), so overriding here reaches task owner, assignee,
           project owner and group pickers without touching their queries. */
        $this->helper->register('user', '\Kanboard\Plugin\TaskManager\Helper\UserHelper');

        /* Why a backdated save was refused. Core overwrites the flash with a
           generic message right after, so this rides in its own slot. */
        $this->template->hook->attach('template:layout:top', 'TaskManager:layout/backdating_notice');

        // Assets
        $this->hook->on('template:layout:css', array('template' => 'plugins/TaskManager/Assets/css/bundle.css'));
        
        
        $this->hook->on('template:layout:js', array('template' => 'plugins/TaskManager/Assets/js/bundle.js'));
        
        // Both only reshape a rendered Gantt chart; on every other page they
        // find nothing to do and are pure download weight.
        if ($this->isGanttPage()) {
            $this->hook->on('template:layout:js', array('template' => 'plugins/TaskManager/Assets/js/dependencies-gantt.js'));
            $this->hook->on('template:layout:js', array('template' => 'plugins/TaskManager/Assets/js/gantt-zoho.js'));
        }
        
        

        // The Zoho-shaped project tab strip: five tabs plus an overflow.
        // Overriding the core template rather than appending to it, so the
        // strip is one coherent set instead of core's three plus ours.
        $this->template->setTemplateOverride('project_header/views', 'TaskManager:project_header/all_views');

        // Typed dependencies panel on the task detail page
        $this->template->hook->attach('template:task:show:before-internal-links', 'TaskManager:task/dependencies');

        // Completion evidence, on the task itself: the assignee is here when
        // the work is finished, not in the reviewer's queue.
        $this->template->hook->attach('template:task:show:before-attachments', 'TaskManager:task/deliverables');

        // Per-project scheduling settings
        $this->template->hook->attach('template:project:sidebar', 'TaskManager:project/sidebar');

        // Portfolio strip on Kanboard's own dashboard
        $this->template->hook->attach('template:dashboard:show:before-filter-box', 'TaskManager:dashboard/workspace');

        // Successor rescheduling — off unless a project switches it on
        $this->dispatcher->addSubscriber(new RescheduleSubscriber($this->container));

        // Workflow rules. These show up in Project settings > Automatic
        // actions, so each project decides whether it wants them; priority
        // escalation used to be an always-on, invisible subscriber.
        $this->actionManager->register(new PriorityEscalation($this->container));
        $this->actionManager->register(new FlagOverdueTask($this->container));
    }

    /**
     * Registered in the container as milestoneModel, taskListModel and
     * projectModel (the last overriding the core model so new projects get
     * the P1-P10 scale).
     */
    /**
     * Whether this page draws a Gantt chart. Mirrors the Gantt plugin's own
     * test; an unreadable controller loads the scripts rather than risk an
     * unstyled chart.
     *
     * @return boolean
     */
    protected function isGanttPage()
    {
        $controller = strtolower($this->request->getStringParam('controller'));

        if ($controller === '') {
            return true;
        }

        // The task grid only draws a chart in its Gantt mode; List and Kanban
        // are the common case and need none of this.
        if ($controller === 'taskgridcontroller') {
            return $this->request->getStringParam('mode') === 'gantt';
        }

        return in_array($controller, array(
            'taskganttcontroller',
            'projectganttcontroller',
        ), true);
    }

    public function getClasses()
    {
        return array(
            // TaskStatusModel overrides core's: closing a task now requires an
            // approved deliverable, and every close path goes through it.
            //
            // TaskCreationModel and TaskModificationModel refuse dates in the
            // past. Overriding the models rather than the validators catches
            // the Gantt drag, the bulk tools and the API as well as the forms.
            /* Adds the airframe-type rules on top of core's project
               validation: the type has to be chosen, and a code typed by
               hand has to match it. */
            'Plugin\TaskManager\Validator' => array('ProjectValidator'),

            'Plugin\TaskManager\Model' => array('ProjectModel', 'UserModel', 'TaskStatusModel', 'TaskCreationModel', 'TaskModificationModel', 'SubtaskModel', 'MilestoneModel', 'TaskListModel', 'DependencyModel', 'TimesheetModel', 'TimeEntryModel', 'RoleSeedModel', 'DashboardModel', 'GridModel', 'DeliverableModel'),
        );
    }

    public function getPluginName()
    {
        return 'TaskManager';
    }

    public function getPluginDescription()
    {
        return 'SUPERBEE task management extension: milestones, task lists, the 4-level tree overview, P1-P10 priority badges, typed dependencies, weekly timesheets, native project-role guardrails, configurable workflow rules the project dashboard and the Zoho-shaped project and task grids.';
    }

    public function getPluginAuthor()
    {
        return 'SUPERBEE Aeronautics';
    }

    public function getPluginVersion()
    {
        return '7.0.0';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/kanboard-hr';
    }

    public function getCompatibleVersion()
    {
        return '>=1.2.50';
    }
}
