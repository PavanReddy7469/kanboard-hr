/* SUPERBEE — Gantt Integration Enhancements.
 *
 * Coordinates drawer open events and interactive scale bindings.
 */
(function () {
    'use strict';

    function initGanttInteractions() {
        var chart = document.getElementById('gantt-chart');
        if (! chart) return;

        // Ensure task clicks trigger drawer
        document.addEventListener('click', function(e) {
            var block = e.target.closest ? e.target.closest('.ganttview-block, [data-zp-open]') : null;
            if (block && block.getAttribute('data-zp-open')) {
                var url = block.getAttribute('data-zp-open');
                // The task-panel.js handles data-zp-open globally
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGanttInteractions);
    } else {
        initGanttInteractions();
    }
}());
