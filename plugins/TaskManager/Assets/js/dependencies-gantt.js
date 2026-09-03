/* SUPERBEE — dependency arrows on the Gantt chart.
 *
 * Draws an SVG overlay on top of the Gantt's slide container: one elbow arrow
 * per dependency, plus critical-path highlighting.
 *
 * Deliberately standalone. The Gantt plugin is third-party and is not
 * modified: this reads the DOM it produces and fetches its own data, so a
 * Gantt upgrade cannot silently drop the arrows — they would just stop
 * drawing, visibly.
 */
(function () {
    'use strict';

    var NS = 'http://www.w3.org/2000/svg';
    var COLOUR = '#94a3b8';
    var CRITICAL = '#be123c';
    var edges = null;
    var redrawTimer = null;

    function projectId() {
        var m = window.location.search.match(/[?&]project_id=(\d+)/);
        return m ? m[1] : null;
    }

    function slider() {
        return document.querySelector('#gantt-chart .ganttview-slide-container');
    }

    /* task id -> its bar element, read from the record jQuery stashed on each block */
    function blocksByTask() {
        var map = {};
        var $ = window.jQuery;

        if (! $) {
            return map;
        }

        $('#gantt-chart .ganttview-block').each(function () {
            var record = $(this).data('record');

            if (record && record.type === 'task') {
                map[record.id] = this;
            }
        });

        return map;
    }

    function box(el, root) {
        var a = el.getBoundingClientRect();
        var b = root.getBoundingClientRect();

        return {
            left: a.left - b.left,
            right: a.left - b.left + a.width,
            top: a.top - b.top,
            mid: a.top - b.top + a.height / 2,
            bottom: a.top - b.top + a.height
        };
    }

    /* Which end of each bar the constraint attaches to. */
    function anchors(type, from, to) {
        switch (type) {
            case 'SS': return [from.left, to.left];
            case 'FF': return [from.right, to.right];
            case 'SF': return [from.left, to.right];
            default:   return [from.right, to.left]; // FS
        }
    }

    /* Tasks on the longest chain of FS dependencies. */
    function criticalTasks(list) {
        var predecessors = {};
        var nodes = {};

        list.forEach(function (e) {
            (predecessors[e.task_id] = predecessors[e.task_id] || []).push(e.depends_on_id);
            nodes[e.task_id] = true;
            nodes[e.depends_on_id] = true;
        });

        var depthCache = {};
        var chainCache = {};

        function walk(id, seen) {
            if (depthCache[id] !== undefined) {
                return depthCache[id];
            }

            if (seen[id]) {
                return 0;
            }

            seen[id] = true;
            var best = 0;
            var bestChain = [];

            (predecessors[id] || []).forEach(function (p) {
                var d = walk(p, seen);

                if (d + 1 > best) {
                    best = d + 1;
                    bestChain = (chainCache[p] || []).concat([p]);
                }
            });

            depthCache[id] = best;
            chainCache[id] = bestChain;
            return best;
        }

        var deepest = null;
        var max = -1;

        Object.keys(nodes).forEach(function (id) {
            var d = walk(id, {});

            if (d > max) {
                max = d;
                deepest = id;
            }
        });

        if (deepest === null || max < 1) {
            return {};
        }

        var flags = {};
        (chainCache[deepest] || []).concat([parseInt(deepest, 10)]).forEach(function (id) {
            flags[id] = true;
        });

        return flags;
    }

    function draw() {
        var root = slider();

        if (! root || ! edges) {
            return;
        }

        var old = root.querySelector('.sb-dep-overlay');

        if (old) {
            old.parentNode.removeChild(old);
        }

        if (! edges.length) {
            return;
        }

        var blocks = blocksByTask();
        var critical = criticalTasks(edges);

        var svg = document.createElementNS(NS, 'svg');
        svg.setAttribute('class', 'sb-dep-overlay');
        svg.setAttribute('width', root.scrollWidth);
        svg.setAttribute('height', root.scrollHeight);
        svg.style.position = 'absolute';
        svg.style.left = '0';
        svg.style.top = '0';
        svg.style.pointerEvents = 'none';
        svg.style.zIndex = '5';
        svg.style.overflow = 'visible';

        edges.forEach(function (edge) {
            var fromEl = blocks[edge.depends_on_id];
            var toEl = blocks[edge.task_id];

            if (! fromEl || ! toEl) {
                return;
            }

            var from = box(fromEl, root);
            var to = box(toEl, root);
            var ends = anchors(edge.type, from, to);
            var x1 = ends[0];
            var x2 = ends[1];
            var y1 = from.mid;
            var y2 = to.mid;
            var isCritical = critical[edge.task_id] && critical[edge.depends_on_id];
            var stroke = isCritical ? CRITICAL : COLOUR;

            /* Elbow: out of the predecessor, down, into the successor. Routed
               around the left when the successor starts before the anchor. */
            var gap = 10;
            var midX = x2 - gap;
            var path;

            if (x2 > x1 + gap) {
                path = 'M' + x1 + ' ' + y1 + ' H' + midX + ' V' + y2 + ' H' + x2;
            } else {
                var back = x1 + gap;
                var lane = y1 + (y2 > y1 ? 1 : -1) * 14;
                path = 'M' + x1 + ' ' + y1 + ' H' + back + ' V' + lane +
                       ' H' + (x2 - gap) + ' V' + y2 + ' H' + x2;
            }

            var line = document.createElementNS(NS, 'path');
            line.setAttribute('d', path);
            line.setAttribute('fill', 'none');
            line.setAttribute('stroke', stroke);
            line.setAttribute('stroke-width', isCritical ? '2' : '1.4');
            if (! isCritical) {
                line.setAttribute('stroke-dasharray', edge.lag ? '4 3' : '');
            }
            svg.appendChild(line);

            var head = document.createElementNS(NS, 'path');
            head.setAttribute('d', 'M' + x2 + ' ' + y2 + ' L' + (x2 - 6) + ' ' + (y2 - 3.5) + ' L' + (x2 - 6) + ' ' + (y2 + 3.5) + ' Z');
            head.setAttribute('fill', stroke);
            svg.appendChild(head);

            if (edge.lag) {
                var label = document.createElementNS(NS, 'text');
                label.setAttribute('x', (x1 + x2) / 2);
                label.setAttribute('y', (y1 + y2) / 2 - 4);
                label.setAttribute('fill', stroke);
                label.setAttribute('font-size', '10');
                label.setAttribute('font-weight', '700');
                label.setAttribute('text-anchor', 'middle');
                label.textContent = (edge.lag > 0 ? '+' : '') + edge.lag + 'd';
                svg.appendChild(label);
            }
        });

        root.appendChild(svg);
    }

    function scheduleRedraw() {
        clearTimeout(redrawTimer);
        redrawTimer = setTimeout(draw, 120);
    }

    function start() {
        var id = projectId();

        if (! document.getElementById('gantt-chart') || ! id) {
            return;
        }

        fetch('?controller=DependencyController&action=json&project_id=' + id + '&plugin=TaskManager', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                edges = data.edges || [];
                scheduleRedraw();

                /* The scale buttons empty and rebuild the chart, so watch for it. */
                var chart = document.getElementById('gantt-chart');
                new MutationObserver(scheduleRedraw).observe(chart, { childList: true, subtree: true });
                window.addEventListener('resize', scheduleRedraw);
            })
            .catch(function () { /* no dependencies feed: leave the chart alone */ });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}());
