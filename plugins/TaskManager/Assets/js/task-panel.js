/* SUPERBEE — the Zoho-style task drawer.
 *
 * Clicking a task in the grid slides its detail in from the right instead of
 * navigating away, so the list keeps its place, filter and scroll position.
 * The panel is a fragment fetched from TaskPanelController; every edit action
 * inside it is one of Kanboard's own modals, so nothing here writes a task.
 */
(function () {
    'use strict';

    var panel = null;
    var backdrop = null;

    function build() {
        if (panel) {
            return;
        }

        backdrop = document.createElement('div');
        backdrop.className = 'zp-backdrop';
        backdrop.addEventListener('click', close);

        panel = document.createElement('aside');
        panel.className = 'zp-panel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Task details');

        document.body.appendChild(backdrop);
        document.body.appendChild(panel);
    }

    function open(url) {
        build();
        panel.innerHTML = '<div class="zp-loading">&hellip;</div>';
        document.body.classList.add('zp-open');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) {
                if (! r.ok) {
                    throw new Error('panel');
                }
                return r.text();
            })
            .then(function (html) {
                panel.innerHTML = html;
                bindPanel();
            })
            .catch(function () {
                /* Fall back to the modern task grid */
                close();
                window.location = url.replace('TaskPanelController', 'TaskGridController');
            });
    }

    function close() {
        document.body.classList.remove('zp-open');
    }

    function bindPanel() {
        var closer = panel.querySelector('.zp-close');

        if (closer) {
            closer.addEventListener('click', function (e) {
                e.preventDefault();
                close();
            });
        }

        /* Tabs reload just the panel, keeping the drawer open. */
        var tabs = panel.querySelectorAll('.zp-tab');

        for (var i = 0; i < tabs.length; i++) {
            tabs[i].addEventListener('click', function (e) {
                e.preventDefault();
                open(this.getAttribute('data-zp-url'));
            });
        }
    }

    /* Grid rows: intercept the task link. Modifier-clicks and middle-clicks
       keep their normal "open in a new tab" behaviour. */
    function onClick(e) {
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }

        var link = e.target.closest ? e.target.closest('a[data-zp-open]') : null;

        if (! link) {
            return;
        }

        e.preventDefault();
        open(link.getAttribute('data-zp-open'));
    }

    /* Capture phase: Kanboard binds its own delegated click handlers on
       document, and a bubbling listener here never saw the event. */
    document.addEventListener('click', onClick, true);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('zp-open')) {
            close();
        }
    });

    /* Auto-open task drawer when task_id and project_id are in the URL */
    function checkAutoOpen() {
        try {
            var params = new URLSearchParams(window.location.search);
            var autoTaskId = params.get('task_id');
            var autoProjectId = params.get('project_id');
            var controller = params.get('controller');

            if (autoTaskId && autoProjectId && (!controller || controller === 'TaskGridController')) {
                var drawerUrl = '?controller=TaskPanelController&action=show&plugin=TaskManager&task_id=' + encodeURIComponent(autoTaskId) + '&project_id=' + encodeURIComponent(autoProjectId);
                open(drawerUrl);
            }
        } catch (err) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkAutoOpen);
    } else {
        setTimeout(checkAutoOpen, 150);
    }
}());
