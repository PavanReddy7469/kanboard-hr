/* SUPERBEE — behaviour for the grid toolbars.
 *
 * Kanboard sends Content-Security-Policy: default-src 'self', which blocks
 * every inline <script> and every inline onclick/onchange attribute. All of
 * this therefore has to be bound from an external file; anything written as
 * an HTML attribute silently does nothing.
 */
(function () {
    'use strict';

    var SCALES = ['year', 'quarter', 'month', 'week', 'day'];

    function on(event, selector, handler) {
        document.addEventListener(event, function (e) {
            var el = e.target && e.target.closest ? e.target.closest(selector) : null;

            if (el) {
                handler.call(el, e);
            }
        });
    }

    /* Any select that just re-submits its form on change. */
    on('change', '[data-zg-submit]', function () {
        if (this.form) {
            this.form.submit();
        }
    });

    /* Gantt zoom: the select and the -/+ stepper both drive the Gantt
       plugin's own setGanttScale(). */
    on('change', '#zg-scale', function () {
        if (typeof window.setGanttScale === 'function') {
            window.setGanttScale(this.value, null);
        }
    });

    on('click', '[data-zg-step]', function (e) {
        e.preventDefault();

        var select = document.getElementById('zg-scale');

        if (! select || typeof window.setGanttScale !== 'function') {
            return;
        }

        var next = select.selectedIndex + parseInt(this.getAttribute('data-zg-step'), 10);

        if (next < 0 || next >= select.options.length) {
            return;
        }

        select.selectedIndex = next;
        window.setGanttScale(select.value, null);
    });

    on('click', '[data-zg-fit]', function (e) {
        e.preventDefault();

        if (typeof window.toggleFitToWidth === 'function') {
            window.toggleFitToWidth(this);
        }
    });

    /* The standalone Gantt page's scale buttons. */
    on('click', '[data-zg-scale]', function (e) {
        e.preventDefault();

        if (typeof window.setGanttScale === 'function') {
            window.setGanttScale(this.getAttribute('data-zg-scale'), this);
        }
    });

    /* Tree collapse toggles on the outline view. */
    on('click', '[data-zg-tree]', function (e) {
        e.preventDefault();

        if (typeof window.toggleTreeRow === 'function') {
            window.toggleTreeRow(this.getAttribute('data-zg-tree'), this);
        }
    });
}());
