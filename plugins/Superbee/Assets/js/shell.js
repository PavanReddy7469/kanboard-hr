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
}());
