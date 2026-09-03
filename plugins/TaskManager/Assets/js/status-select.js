/* SUPERBEE — the inline status control.
 *
 * A pill that opens a searchable list, as Zoho does. Choosing an option calls
 * StatusChangeController, which moves the task through Kanboard's own
 * TaskPositionModel; this file only updates the pill once the server confirms.
 * Nothing is written optimistically, so the pill can never show a status the
 * database does not have.
 *
 * The menu is moved to <body> and positioned with position:fixed while open.
 * Left inside the grid it was clipped by the table's overflow container and
 * scrolled away with the rows.
 */
(function () {
    'use strict';

    var open = null;   /* the menu currently on <body> */

    function ownerOf(menu) {
        return menu && menu.zsOwner ? menu.zsOwner : null;
    }

    function place(menu, pill) {
        var box = pill.getBoundingClientRect();
        var width = menu.offsetWidth || 230;
        var height = menu.offsetHeight || 260;
        var margin = 6;

        var left = box.left;
        var top = box.bottom + 4;

        /* Flip above the pill when there is no room below, and keep the whole
           menu inside the viewport horizontally. */
        if (top + height > window.innerHeight - margin && box.top - height - 4 > margin) {
            top = box.top - height - 4;
        }

        if (left + width > window.innerWidth - margin) {
            left = Math.max(margin, window.innerWidth - width - margin);
        }

        menu.style.left = Math.round(left) + 'px';
        menu.style.top = Math.round(top) + 'px';
    }

    function closeMenu() {
        if (! open) {
            return;
        }

        var owner = ownerOf(open);
        open.hidden = true;
        open.style.left = '';
        open.style.top = '';

        if (owner) {
            owner.appendChild(open);
        }

        open = null;
    }

    function openMenu(root) {
        var menu = root.querySelector('.zs-menu');
        var pill = root.querySelector('.zs-pill');

        if (! menu || ! pill) {
            return;
        }

        closeMenu();

        menu.zsOwner = root;
        document.body.appendChild(menu);
        menu.hidden = false;
        place(menu, pill);
        open = menu;

        var search = menu.querySelector('.zs-search');

        if (search) {
            search.value = '';
            filter(menu, '');
            search.focus();
        }
    }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest ? e.target.closest('[data-zs-toggle]') : null;

        if (toggle) {
            e.preventDefault();
            e.stopPropagation();

            var root = toggle.parentNode;

            if (open && ownerOf(open) === root) {
                closeMenu();
            } else {
                openMenu(root);
            }

            return;
        }

        var option = e.target.closest ? e.target.closest('.zs-option') : null;

        if (option) {
            e.preventDefault();
            e.stopPropagation();
            choose(option);
            return;
        }

        if (! (e.target.closest && e.target.closest('.zs-menu'))) {
            closeMenu();
        }
    }, true);

    /* 'input' rather than 'keyup': it fires for every way text can change -
       typing, paste, and the insertText path automation and some IMEs use,
       which never emits a keyup at all. */
    document.addEventListener('input', function (e) {
        var search = e.target.closest ? e.target.closest('.zs-search') : null;

        if (search) {
            filter(search.closest('.zs-menu'), search.value);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeMenu();
        }
    });

    /* Any scroll anywhere - the page, or the grid's own overflow container -
       would leave a fixed menu stranded beside the wrong row. */
    window.addEventListener('scroll', closeMenu, true);
    window.addEventListener('resize', closeMenu);

    function filter(menu, term) {
        if (! menu) {
            return;
        }

        var needle = term.toLowerCase();
        var options = menu.querySelectorAll('.zs-option');

        for (var i = 0; i < options.length; i++) {
            var label = (options[i].getAttribute('data-zs-label') || '').toLowerCase();
            options[i].hidden = needle !== '' && label.indexOf(needle) === -1;
        }
    }

    function choose(option) {
        var menu = option.closest('.zs-menu');
        var root = ownerOf(menu) || option.closest('.zs');

        if (! root) {
            return;
        }

        var pill = root.querySelector('.zs-pill');
        var url = option.getAttribute('data-zs-url');

        root.classList.add('is-saving');

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) {
                if (! r.ok) {
                    throw new Error('status');
                }
                return r.json();
            })
            .then(function (data) {
                if (! data || ! data.ok) {
                    throw new Error('status');
                }

                pill.querySelector('.zs-label').textContent = data.label;
                pill.className = 'zs-pill ' + data.class;

                var options = menu.querySelectorAll('.zs-option');

                for (var i = 0; i < options.length; i++) {
                    options[i].classList.toggle('is-current', options[i] === option);
                }

                closeMenu();
                root.classList.remove('is-saving');
                root.classList.add('is-saved');
                setTimeout(function () { root.classList.remove('is-saved'); }, 900);
            })
            .catch(function () {
                /* Say so rather than leaving a pill showing something untrue. */
                closeMenu();
                root.classList.remove('is-saving');
                root.classList.add('is-failed');
                setTimeout(function () { root.classList.remove('is-failed'); }, 2000);
            });
    }
}());
