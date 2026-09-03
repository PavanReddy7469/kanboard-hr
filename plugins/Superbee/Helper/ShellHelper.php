<?php

namespace Kanboard\Plugin\Superbee\Helper;

use Kanboard\Core\Base;

/**
 * Supplies the navigation rail with its items and works out which one is active.
 *
 * @package Kanboard\Plugin\Superbee\Helper
 */
class ShellHelper extends Base
{
    /**
     * Rail sections. Items with 'soon' => true are placeholders for features
     * that later phases deliver; they render disabled rather than linking
     * somewhere that does not exist yet.
     *
     * @return array
     */
    public function getNavigation()
    {
        return array(
            // This rail is workspace scope only. Anything belonging to one
            // project - Dashboard, Tasks, Reports, Timesheet, Calendar, Gantt,
            // Milestones, Workflow Rules - lives on the project tab strip, so
            // the two never show the same word twice. The all-projects Gantt
            // roadmap is reachable from "All projects" on any project's Gantt.
            t('Workspace') => array(
                array('label' => t('Home'),        'icon' => 'home',     'controller' => 'DashboardController',   'action' => 'show'),
                array('label' => t('Projects'),    'icon' => 'folder',   'controller' => 'ProjectGridController', 'action' => 'show', 'plugin' => 'TaskManager'),
                array('label' => t('Tasks'),       'icon' => 'check',    'controller' => 'DashboardController',   'action' => 'tasks'),
                array('label' => t('Calendar'),    'icon' => 'calendar', 'controller' => 'CalendarController',    'action' => 'user', 'plugin' => 'Calendar'),
                array('label' => t('Search'),      'icon' => 'search',   'controller' => 'SearchController',      'action' => 'index'),
            ),
            t('Administration') => array(
                array('label' => t('People & Roles'),  'icon' => 'users',    'controller' => 'UserListController', 'action' => 'show'),
                array('label' => t('Settings'),        'icon' => 'settings', 'controller' => 'ConfigController',   'action' => 'index'),
            ),
        );
    }

    /**
     * Hide items the current user has no access to, and drop empty sections.
     *
     * @return array
     */
    public function getVisibleNavigation()
    {
        $sections = array();

        foreach ($this->getNavigation() as $section => $items) {
            $visible = array();

            foreach ($items as $item) {
                if (! empty($item['soon']) || $this->helper->user->hasAccess($item['controller'], $item['action'])) {
                    $visible[] = $item;
                }
            }

            if (! empty($visible)) {
                $sections[$section] = $visible;
            }
        }

        return $sections;
    }

    /**
     * The page title, worded the way the rail words it.
     *
     * Kanboard titles these pages "Dashboard for admin" and "Tasks overview
     * for admin" while the rail calls them Home and My Tasks, and the calendar
     * has no title at all. Reusing the rail's own label keeps one name per
     * destination.
     *
     * @param  string $fallback
     * @return string
     */
    public function getPageTitle($fallback = '')
    {
        foreach ($this->getNavigation() as $items) {
            foreach ($items as $item) {
                if (empty($item['soon']) && $this->isActive($item)) {
                    return $item['label'];
                }
            }
        }

        return $fallback;
    }

    /**
     * @param  array $item
     * @return boolean
     */
    public function isActive(array $item)
    {
        if (! empty($item['soon'])) {
            return false;
        }

        if ($this->request->getStringParam('controller') !== $item['controller']) {
            return false;
        }

        // Home and My Tasks are both DashboardController; without comparing the
        // action too, both light up at once.
        $action = $this->request->getStringParam('action', 'show');

        return $action === $item['action'];
    }

    /**
     * @param  array $item
     * @return string
     */
    public function url(array $item)
    {
        $params = array();

        if (! empty($item['plugin'])) {
            $params['plugin'] = $item['plugin'];
        }

        return $this->helper->url->href($item['controller'], $item['action'], $params);
    }

    /**
     * Initials for the avatar chip, e.g. "Pavan Reddy" -> "PR".
     *
     * @return string
     */
    public function getUserInitials()
    {
        $name = trim($this->helper->user->getFullname());

        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/', $name);

        if (count($parts) === 1) {
            return strtoupper(substr($parts[0], 0, 2));
        }

        return strtoupper(substr($parts[0], 0, 1).substr(end($parts), 0, 1));
    }

    /**
     * Stroke icons on a 24px grid. No emoji, no icon font.
     *
     * @param  string $name
     * @return string
     */
    public function icon($name)
    {
        $paths = array(
            'grid'     => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
            'home'     => '<path d="M4 10.5 12 4l8 6.5V19a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 19z"/><path d="M9.5 20.5v-6h5v6"/>',
            'folder'   => '<path d="M3 7.5a2 2 0 0 1 2-2h3.6l2 2.4H19a2 2 0 0 1 2 2v8.6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
            'check'    => '<rect x="3.5" y="4" width="17" height="16" rx="2.5"/><path d="M8 12l2.6 2.6L16 9"/>',
            'flag'     => '<path d="M5 21V4"/><path d="M5 5h11l-2 3.5L16 12H5"/>',
            'gantt'    => '<rect x="3" y="5" width="12" height="4" rx="1.5"/><rect x="7" y="10.5" width="13" height="4" rx="1.5"/><rect x="5" y="16" width="9" height="4" rx="1.5"/>',
            'clock'    => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 1.8"/>',
            'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M3.5 10h17M8 3.5v3M16 3.5v3"/>',
            'search'   => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
            'users'    => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/><circle cx="17.5" cy="9.5" r="2.6"/><path d="M15.5 15c2.8-.6 5 1.2 5 4.5"/>',
            'workflow' => '<path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2.2"/><circle cx="10" cy="17" r="2.2"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M21 12h-2.5M5.5 12H3M18.4 5.6l-1.8 1.8M7.4 16.6l-1.8 1.8M18.4 18.4l-1.8-1.8M7.4 7.4L5.6 5.6"/>',
        );

        $path = isset($paths[$name]) ? $paths[$name] : $paths['grid'];

        return '<svg class="sb-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$path.'</svg>';
    }
}
