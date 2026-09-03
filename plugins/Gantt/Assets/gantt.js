if (typeof window.$ === 'undefined' && typeof jQuery !== 'undefined') {
    window.$ = jQuery;
}

/* Cache original records across scale re-renders */
var _ganttOriginalRecords = null;

KB.on('dom.ready', function () {
    function goToLink (selector) {
        if (! KB.modal.isOpen()) {
            var element = KB.find(selector);
            if (element !== null) window.location = element.attr('href');
        }
    }

    KB.onKey('v+g', function () { goToLink('a.view-gantt'); });

    if (KB.exists('#gantt-chart')) {
        var $jq = window.jQuery || window.$;
        var $c = $jq('#gantt-chart');
        var recs = $c.data('records');
        if (!recs && $c.attr('data-records')) {
            try { recs = JSON.parse($c.attr('data-records')); } catch(e) {}
        }
        if (recs) {
            _ganttOriginalRecords = recs;
        }
        window.ganttInstance = new Gantt();
        window.ganttInstance.options.scale = 'quarters';
        window.ganttInstance.show();
    }
});

/* ------------------------------------------------------------------ */
/*  Interactive scale switcher                                         */
/* ------------------------------------------------------------------ */
function setGanttScale(scale) {
    var validScales = ['weeks', 'months', 'quarters', 'years'];
    if (validScales.indexOf(scale) === -1) scale = 'quarters';

    var sel = document.getElementById('zg-scale-select');
    if (sel && sel.value !== scale) sel.value = scale;

    var $jq = window.jQuery || window.$;
    var cc = $jq('#gantt-chart');
    if (!cc.length) return;

    if (!_ganttOriginalRecords) {
        _ganttOriginalRecords = cc.data('records');
    }
    if (!_ganttOriginalRecords && cc.attr('data-records')) {
        try { _ganttOriginalRecords = JSON.parse(cc.attr('data-records')); } catch(e) {}
    }
    if (!_ganttOriginalRecords) return;

    /* Preserve container metadata */
    var saveUrl       = cc.data('save-url') || cc.attr('data-save-url');
    var lblStart      = cc.data('label-start-date');
    var lblEnd        = cc.data('label-end-date');
    var lblAssignee   = cc.data('label-assignee');
    var lblNotDefined = cc.data('label-not-defined');
    var readonly      = cc.data('readonly');

    cc.empty();

    /* Restore fresh cloned data */
    var fresh = JSON.parse(JSON.stringify(_ganttOriginalRecords));
    cc.data('records', fresh);
    if (saveUrl) cc.data('save-url', saveUrl);
    if (lblStart) cc.data('label-start-date', lblStart);
    if (lblEnd) cc.data('label-end-date', lblEnd);
    if (lblAssignee) cc.data('label-assignee', lblAssignee);
    if (lblNotDefined) cc.data('label-not-defined', lblNotDefined);
    if (readonly) cc.data('readonly', readonly);

    window.ganttInstance = new Gantt();
    window.ganttInstance.options.scale = scale;
    window.ganttInstance.show();
}

/* ------------------------------------------------------------------ */
/*  Zoom stepper: − zooms out, + zooms in                             */
/*  Order: years (0) <-> quarters (1) <-> months (2) <-> weeks (3)    */
/* ------------------------------------------------------------------ */
function stepGanttZoom(step) {
    var scales = ['years', 'quarters', 'months', 'weeks'];
    var sel = document.getElementById('zg-scale-select');
    var cur = sel ? sel.value : (window.ganttInstance ? window.ganttInstance.options.scale : 'quarters');
    var idx = scales.indexOf(cur);
    if (idx === -1) idx = 1;

    var n = idx + step;
    if (n < 0) n = 0;
    if (n >= scales.length) n = scales.length - 1;

    var nextScale = scales[n];
    if (sel) sel.value = nextScale;
    setGanttScale(nextScale);
}

function toggleFitToWidth() {
    setGanttScale('quarters');
}

/* Expose functions on window */
window.setGanttScale = setGanttScale;
window.stepGanttZoom = stepGanttZoom;
window.toggleFitToWidth = toggleFitToWidth;

/* ------------------------------------------------------------------ */
/*  Event Listeners (attached via JS to comply with CSP)              */
/* ------------------------------------------------------------------ */
(function() {
    var $jq = window.jQuery || window.$;

    // Scale dropdown change
    $jq(document).on('change', '#zg-scale-select', function() {
        setGanttScale(this.value);
    });

    // Zoom stepper buttons (+ and -)
    $jq(document).on('click', '.zg-zoom-btn', function(e) {
        e.preventDefault();
        var step = $(this).attr('data-step') ? parseInt($(this).attr('data-step'), 10) : ($(this).find('.fa-plus').length ? 1 : -1);
        stepGanttZoom(step);
    });

    // Fit to width button
    $jq(document).on('click', '#zg-fit-btn, .sb-fit-btn', function(e) {
        e.preventDefault();
        toggleFitToWidth();
    });
})();
