/* SUPERBEE — shell behaviour.
 *
 * Kanboard sends Content-Security-Policy: default-src 'self', which blocks
 * every inline <script> and every inline onclick/onchange attribute: the
 * browser parses the attribute but never compiles it, so the handler silently
 * does nothing. Anything interactive therefore has to be bound from an
 * external file like this one.
 *
 * Purely visual hover and focus effects are not bound here at all - they live
 * in CSS, where they belong and where no policy can switch them off.
 */
(function () {
    'use strict';

    function on(event, selector, handler) {
        document.addEventListener(event, function (e) {
            var el = e.target && e.target.closest ? e.target.closest(selector) : null;

            if (el) {
                handler.call(el, e);
            }
        });
    }

    /* Cancel / Close buttons inside a Kanboard modal. */
    on('click', '[data-sb-modal-close]', function (e) {
        e.preventDefault();

        if (window.KB && window.KB.modal && typeof window.KB.modal.close === 'function') {
            window.KB.modal.close();
        }
    });

    /* ------------------------------------------------------------------
       No backdating.

       Dates are refused in the past by the models, which is what actually
       enforces the rule - this is only so the user finds out while they are
       still filling the form rather than after a failed save.

       Two things are needed. Kanboard binds a jQuery UI datepicker to every
       .form-date input, so the calendar has to be told today is its floor;
       and because the underlying control is a plain text input, someone can
       still type an old date past the calendar, so typed values get checked
       on the way out of the field.

       Kanboard re-runs its own initialisation whenever a modal loads, which
       replaces the datepicker and discards our option, so this re-applies on
       an interval and after AJAX rather than once at load.
    ------------------------------------------------------------------ */

    function todayAtMidnight() {
        var d = new Date();
        d.setHours(0, 0, 0, 0);
        return d;
    }

    function applyMinDate() {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.datepicker) {
            return;
        }

        window.jQuery('.form-date, .form-datetime').each(function () {
            var $el = window.jQuery(this);

            try {
                // Throws if no datepicker is attached to this element yet.
                if ($el.data('datepicker') && $el.datepicker('option', 'minDate') !== 0) {
                    $el.datepicker('option', 'minDate', 0);
                }
            } catch (err) {
                /* nothing attached yet; the next pass will pick it up */
            }
        });
    }

    function markInvalid(input, isBad) {
        input.classList.toggle('sb-date-past', isBad);
        input.setAttribute('aria-invalid', isBad ? 'true' : 'false');

        var next = input.nextElementSibling;
        var hasNote = next && next.classList && next.classList.contains('sb-date-past-note');

        if (isBad && !hasNote) {
            var note = document.createElement('div');
            note.className = 'sb-date-past-note';
            note.textContent = 'Dates cannot be in the past.';
            input.parentNode.insertBefore(note, input.nextSibling);
        } else if (!isBad && hasNote) {
            next.parentNode.removeChild(next);
        }
    }

    /* The field carries whatever format the user's profile is set to, so let
       the browser parse it rather than guessing at the order of day and
       month. An unparseable string is left alone - the server decides. */
    function checkTypedDate(input) {
        var raw = (input.value || '').trim();

        if (raw === '') {
            markInvalid(input, false);
            return;
        }

        var parsed = new Date(raw.replace(/-/g, '/'));

        if (isNaN(parsed.getTime())) {
            markInvalid(input, false);
            return;
        }

        parsed.setHours(0, 0, 0, 0);
        markInvalid(input, parsed < todayAtMidnight());
    }

    on('change', '.form-date, .form-datetime', function () {
        checkTypedDate(this);
    });

    on('blur', '.form-date, .form-datetime', function () {
        checkTypedDate(this);
    });

    applyMinDate();
    setInterval(applyMinDate, 700);

    if (window.jQuery) {
        window.jQuery(document).ajaxComplete(applyMinDate);
    }

    /* ------------------------------------------------------------------
       Click-to-rename.

       The project name in the breadcrumb behaves like a filename: click it,
       it becomes an input, Enter commits and Escape abandons. Blur commits
       too, because clicking away after typing means the change was intended.

       The element is only rendered with data-sb-inline-edit for someone
       allowed to rename it. The endpoint checks permission again regardless -
       this is convenience, not the control.
    ------------------------------------------------------------------ */

    function inlineEditActive(el) {
        return el.querySelector('input.sb-inline-input') !== null;
    }

    function startInlineEdit(el) {
        if (inlineEditActive(el)) {
            return;
        }

        var original = el.textContent.trim();
        var url = el.getAttribute('data-sb-url');
        var field = el.getAttribute('data-sb-inline-edit');

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'sb-inline-input';
        input.value = original;
        input.setAttribute('aria-label', 'Project name');

        el.textContent = '';
        el.appendChild(input);
        el.classList.add('is-editing');

        input.focus();
        input.select();

        var settled = false;

        function restore(text) {
            el.classList.remove('is-editing', 'is-saving');
            el.textContent = text;
        }

        function cancel() {
            if (settled) { return; }
            settled = true;
            restore(original);
        }

        function commit() {
            if (settled) { return; }

            var next = input.value.trim();

            if (next === '' || next === original) {
                cancel();
                return;
            }

            settled = true;
            el.classList.add('is-saving');

            var body = new URLSearchParams();
            body.append('field', field);
            body.append('value', next);

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(function (r) {
                return r.json().catch(function () { return { ok: false }; });
            }).then(function (data) {
                if (data && data.ok) {
                    restore(next);
                    document.title = document.title.replace(original, next);
                } else {
                    restore(original);
                    window.alert((data && data.message) || 'Could not rename this project.');
                }
            }).catch(function () {
                restore(original);
                window.alert('Could not reach the server. The name was not changed.');
            });
        }

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                commit();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                cancel();
            }
            e.stopPropagation();
        });

        input.addEventListener('blur', commit);

        // Keep a click inside the input from re-triggering the opener.
        input.addEventListener('click', function (e) { e.stopPropagation(); });
    }

    on('click', '[data-sb-inline-edit]', function (e) {
        if (inlineEditActive(this)) {
            return;
        }
        e.preventDefault();
        startInlineEdit(this);
    });

    on('keydown', '[data-sb-inline-edit]', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && !inlineEditActive(this)) {
            e.preventDefault();
            startInlineEdit(this);
        }
    });

    /* ------------------------------------------------------------------
       Subtask tree in the task grid.

       Clicking a task's id unfolds its subtasks as rows directly beneath it.
       They live in the same table, so every column stays aligned with the
       task above rather than drifting in a nested table of its own.

       Which tasks are open is remembered per project for the session, so
       sorting a column or changing a filter does not collapse everything the
       user had just opened.
    ------------------------------------------------------------------ */

    var SUBTREE_KEY = 'sb-subtree-open';

    function subtreeStore() {
        try {
            return JSON.parse(sessionStorage.getItem(SUBTREE_KEY) || '{}');
        } catch (err) {
            return {};
        }
    }

    function rememberSubtree(id, open) {
        try {
            var state = subtreeStore();
            if (open) { state[id] = 1; } else { delete state[id]; }
            sessionStorage.setItem(SUBTREE_KEY, JSON.stringify(state));
        } catch (err) {
            /* private browsing, or storage disabled - the tree still works,
               it just forgets between page loads */
        }
    }

    function setSubtree(button, open) {
        var id = button.getAttribute('data-zg-subtree');
        var rows = document.querySelectorAll('[data-zg-subtree-of="' + id + '"]');

        for (var i = 0; i < rows.length; i++) {
            rows[i].hidden = !open;
        }

        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        button.classList.toggle('is-open', open);
        button.setAttribute('title', open ? 'Hide subtasks' : 'Show subtasks');
        rememberSubtree(id, open);
    }

    on('click', '[data-zg-subtree]', function (e) {
        e.preventDefault();
        setSubtree(this, this.getAttribute('aria-expanded') !== 'true');
    });

    function restoreSubtrees() {
        var state = subtreeStore();
        var buttons = document.querySelectorAll('[data-zg-subtree]');

        for (var i = 0; i < buttons.length; i++) {
            if (state[buttons[i].getAttribute('data-zg-subtree')]) {
                setSubtree(buttons[i], true);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', restoreSubtrees);
    } else {
        restoreSubtrees();
    }

    /* ------------------------------------------------------------------
       Task panel overflow menu, and Copy link.

       The clipboard API only exists in a secure context. On localhost that
       is satisfied, but the moment this is served over plain HTTP on the
       LAN, navigator.clipboard is undefined - so there is a fallback, and
       if both fail the URL is shown for the user to copy by hand rather
       than the button silently doing nothing.
    ------------------------------------------------------------------ */

    function closePanelMenus(except) {
        var lists = document.querySelectorAll('.zp-menu-list');

        for (var i = 0; i < lists.length; i++) {
            if (lists[i] !== except) {
                lists[i].hidden = true;
                var toggle = lists[i].parentNode.querySelector('[data-zp-menu]');
                if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
            }
        }
    }

    on('click', '[data-zp-menu]', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var list = this.parentNode.querySelector('.zp-menu-list');
        if (!list) { return; }

        var opening = list.hidden;
        closePanelMenus(list);
        list.hidden = !opening;
        this.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest || !e.target.closest('.zp-menu')) {
            closePanelMenus(null);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { closePanelMenus(null); }
    });

    function flashCopied(button, text) {
        var original = button.innerHTML;
        button.innerHTML = '<i class="fa fa-check" aria-hidden="true"></i> ' + text;
        setTimeout(function () { button.innerHTML = original; }, 1400);
    }

    function legacyCopy(value) {
        var field = document.createElement('textarea');
        field.value = value;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();

        var ok = false;
        try { ok = document.execCommand('copy'); } catch (err) { ok = false; }

        document.body.removeChild(field);
        return ok;
    }

    on('click', '[data-zp-copy-link]', function (e) {
        e.preventDefault();

        var button = this;
        var href = this.getAttribute('data-zp-copy-link');
        // The template emits a relative path; the origin comes from the browser
        // so the link is correct on localhost, on the LAN, and behind any port.
        var url = href.charAt(0) === '/'
            ? window.location.origin + href
            : href;

        function failed() {
            // Never pretend it worked: show the URL so it can be copied by hand.
            window.prompt('Copy this link:', url);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(url).then(function () {
                flashCopied(button, 'Copied');
            }).catch(function () {
                if (legacyCopy(url)) { flashCopied(button, 'Copied'); } else { failed(); }
            });
        } else if (legacyCopy(url)) {
            flashCopied(button, 'Copied');
        } else {
            failed();
        }

        closePanelMenus(null);
    });

    /* ------------------------------------------------------------------
       Filter panel.

       Builds a Kanboard search string from the panel and navigates to it.
       Nothing is applied until Find, so a filter can be assembled in steps
       without the list moving underneath.

       "All of these" is simply how Kanboard's search already behaves - terms
       are ANDed. "Any of these" has no equivalent in that language, so it is
       honoured only within a single field (several statuses, several owners),
       which is where it actually matters. Fields remain ANDed together.
    ------------------------------------------------------------------ */

    function zfPanel()   { return document.querySelector('[data-zf-panel]'); }
    function zfOverlay() { return document.querySelector('[data-zf-overlay]'); }

    function zfOpen(open) {
        var panel = zfPanel(), overlay = zfOverlay();
        if (!panel) { return; }
        panel.hidden = !open;
        if (overlay) { overlay.hidden = !open; }
        document.body.classList.toggle('zf-open', open);
    }

    function quote(value) {
        return /[\s"]/.test(value) ? '"' + value.replace(/"/g, '') + '"' : value;
    }

    /* Kanboard dates want yyyy-mm-dd, which is what <input type=date> gives. */
    function buildQuery() {
        var rows = document.querySelectorAll('[data-zf-field]');
        var anyMode = false;
        var matchRadio = document.querySelector('[data-zf-match]:checked');
        if (matchRadio) { anyMode = matchRadio.value === 'any'; }

        var terms = [];

        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var field = row.getAttribute('data-zf-field');

            if (row.getAttribute('data-zf-kind') === 'range') {
                var from = row.querySelector('[data-zf-from]');
                var to = row.querySelector('[data-zf-to]');
                if (from && from.value) { terms.push(field + ':>=' + from.value); }
                if (to && to.value)     { terms.push(field + ':<=' + to.value); }
                continue;
            }

            var text = row.querySelector('input.zf-text[data-zf-value]');
            if (text) {
                if (text.value.trim() !== '') { terms.push(field + ':' + quote(text.value.trim())); }
                continue;
            }

            var boxes = row.querySelectorAll('input[type=checkbox][data-zf-value]:checked');
            var picked = [];
            for (var j = 0; j < boxes.length; j++) { picked.push(boxes[j].value); }

            if (picked.length === 0) { continue; }

            /* Several values for one field read as alternatives either way -
               that is what picking two statuses means. Kanboard accepts a
               comma-separated list for exactly this. */
            if (picked.length === 1 || anyMode || true) {
                terms.push(field + ':' + picked.map(quote).join(','));
            }
        }

        return terms.join(' ');
    }

    function currentUrlWith(search) {
        var url = new URL(window.location.href);
        url.searchParams.set('search', search);
        url.searchParams.delete('page');
        return url.toString();
    }

    on('click', '[data-zf-open]', function (e) {
        e.preventDefault();
        zfOpen(true);
    });

    on('click', '[data-zf-cancel]', function (e) { e.preventDefault(); zfOpen(false); });
    on('click', '[data-zf-overlay]', function () { zfOpen(false); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && zfPanel() && !zfPanel().hidden) { zfOpen(false); }
    });

    on('click', '[data-zf-toggle]', function (e) {
        e.preventDefault();
        var body = this.parentNode.querySelector('.zf-rowbody');
        if (!body) { return; }
        body.hidden = !body.hidden;
        this.parentNode.classList.toggle('is-open', !body.hidden);
    });

    on('click', '[data-zf-find]', function (e) {
        e.preventDefault();
        window.location.href = currentUrlWith(buildQuery());
    });

    on('click', '[data-zf-reset]', function (e) {
        e.preventDefault();
        var panel = zfPanel();
        if (!panel) { return; }

        var inputs = panel.querySelectorAll('input');
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].type === 'checkbox') { inputs[i].checked = false; }
            else if (inputs[i].type === 'radio') { inputs[i].checked = inputs[i].value === 'all'; }
            else { inputs[i].value = ''; }
        }

        var bodies = panel.querySelectorAll('.zf-rowbody');
        for (var k = 0; k < bodies.length; k++) {
            bodies[k].hidden = true;
            bodies[k].parentNode.classList.remove('is-open');
        }
    });

    /* Narrow the list of fields, the way the Filter Search box does. */
    on('input', '[data-zf-fieldsearch]', function () {
        var needle = this.value.toLowerCase().trim();
        var rows = document.querySelectorAll('[data-zf-field]');

        for (var i = 0; i < rows.length; i++) {
            var label = (rows[i].getAttribute('data-zf-label') || '').toLowerCase();
            rows[i].hidden = needle !== '' && label.indexOf(needle) === -1;
        }
    });

}());
