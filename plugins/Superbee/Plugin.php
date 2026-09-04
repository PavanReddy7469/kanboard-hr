<?php

namespace Kanboard\Plugin\Superbee;

use Kanboard\Core\Plugin\Base;

/**
 * SUPERBEE application shell: design tokens, left navigation rail and top bar.
 *
 * Only the "header" template is overridden, so this works whatever else is
 * installed and whatever order plugins happen to load in. Nothing under app/
 * is modified.
 *
 * @package Kanboard\Plugin\Superbee
 */
class Plugin extends Base
{
    public function initialize()
    {
        $this->helper->register('shell', '\Kanboard\Plugin\Superbee\Helper\ShellHelper');

        $this->template->setTemplateOverride('header', 'Superbee:header');

        // The dashboard's left column duplicates the rail; lay it out as a
        // horizontal strip instead so the content gets the full width.
        $this->template->setTemplateOverride('dashboard/layout', 'Superbee:dashboard_layout');
        $this->template->setTemplateOverride('dashboard/overview', 'Superbee:dashboard_overview');
        $this->template->setTemplateOverride('dashboard/tasks', 'Superbee:dashboard_tasks');

        // One request instead of three. Sources live beside this bundle;
        // regenerate with: php build-assets.php
        $this->hook->on('template:layout:css', array('template' => 'plugins/Superbee/Assets/css/bundle.css'));
        
        

        // Inline handlers are dead under the app's CSP; shell behaviour binds here.
        $this->hook->on('template:layout:js', array('template' => 'plugins/Superbee/Assets/js/shell.js'));
    }

    public function getPluginName()
    {
        return 'Superbee';
    }

    public function getPluginDescription()
    {
        return 'SUPERBEE application shell — design tokens, left navigation rail, top bar and project tab strip.';
    }

    public function getPluginAuthor()
    {
        return 'SUPERBEE Aeronautics';
    }

    public function getPluginVersion()
    {
        return '1.0.0';
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
