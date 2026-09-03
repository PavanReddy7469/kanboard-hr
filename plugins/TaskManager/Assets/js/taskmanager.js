/* SUPERBEE TaskManager — collapsible project tree.
 *
 * Every row carries a class for each of its ancestors (milestone, task list,
 * parent task). A row is visible only while none of its ancestors is
 * collapsed, so nesting behaves correctly however you fold things up.
 */
(function () {
    'use strict';

    var collapsed = {};

    function isHidden(row) {
        for (var i = 0; i < row.classList.length; i++) {
            if (collapsed[row.classList[i]]) {
                return true;
            }
        }

        return false;
    }

    function refresh() {
        var rows = document.getElementsByClassName('tree-row');

        for (var i = 0; i < rows.length; i++) {
            rows[i].style.display = isHidden(rows[i]) ? 'none' : '';
        }

        var toggles = document.getElementsByClassName('tree-toggle');

        for (var j = 0; j < toggles.length; j++) {
            var key = toggles[j].getAttribute('data-tree-key');
            var icon = toggles[j].querySelector('.tree-icon');

            if (key && icon) {
                icon.className = collapsed[key] ? 'fa fa-plus-square-o tree-icon' : 'fa fa-minus-square-o tree-icon';
            }
        }
    }

    window.toggleTreeRow = function (key, element) {
        if (element && ! element.getAttribute('data-tree-key')) {
            element.setAttribute('data-tree-key', key);
        }

        collapsed[key] = ! collapsed[key];
        refresh();
    };

    /* Kept so older markup that calls toggleSubtasks() keeps working. */
    window.toggleSubtasks = function (key, element) {
        window.toggleTreeRow(key, element);
    };

    document.addEventListener('DOMContentLoaded', function () {
        var toggles = document.getElementsByClassName('tree-toggle');

        for (var i = 0; i < toggles.length; i++) {
            var onclick = toggles[i].getAttribute('onclick') || '';
            var match = onclick.match(/toggle(?:TreeRow|Subtasks)\('([^']+)'/);

            if (match) {
                toggles[i].setAttribute('data-tree-key', match[1]);
            }
        }
    });
}());
