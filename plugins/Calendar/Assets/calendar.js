KB.component('calendar', function (containerElement, options) {
    var modeMapping = {
        month: 'month',
        week: 'agendaWeek',
        day: 'agendaDay'
    };

    this.render = function () {
        var calendar = $(containerElement);
        var mode = 'month';
        if (window.location.hash) { // Check if hash contains mode
            var hashMode = window.location.hash.substr(1);
            mode = modeMapping[hashMode] || mode;
        }

        function getActiveFilters() {
            var filters = {};

            /* Whose tasks. Sent on every request, including "0" for
               everyone, because checkUrl carries the signed-in user's id
               and leaving this out would silently fall back to it. */
            var assignee = $('#sb-cal-assignee-filter');
            if (assignee.length) { filters.user_id = assignee.val(); }

            var proj = $('#sb-cal-project-filter').val();
            if (proj && proj !== '0') filters.project_id = proj;

            var status = $('#sb-cal-status-filter').val();
            if (status) filters.status = status;

            var priority = $('#sb-cal-priority-filter').val();
            if (priority && priority !== '0') filters.priority = priority;

            var search = $('#sb-cal-search-filter').val();
            if (search && search.trim() !== '') filters.search = search.trim();

            return filters;
        }

        function fetchCalendarEvents() {
            var view = calendar.fullCalendar('getView');
            if (!view || !view.start) return;

            /* checkUrl has the signed-in user's id baked into it. Drop it
               rather than appending a second one and relying on the server
               to prefer the last. */
            var url = options.checkUrl.replace(/([?&])user_id=[^&]*(&|$)/, function (m, before, after) {
                return after === '&' ? before : (before === '?' ? '?' : '');
            }).replace(/[?&]$/, '');

            var params = {
                "start": view.start.format(),
                "end": view.end.format()
            };

            var customFilters = getActiveFilters();
            for (var f in customFilters) {
                params[f] = customFilters[f];
            }

            /* The separator has to depend on what is already in the URL.
               Kanboard builds links as a query string by default, but with
               URL rewriting switched on it builds a clean path with no "?"
               at all - and appending "&start=..." to that would hand the
               endpoint no range whatsoever. */
            var separator = url.indexOf('?') === -1 ? '?' : '&';

            for (var key in params) {
                url += separator + key + "=" + encodeURIComponent(params[key]);
                separator = '&';
            }

            $.getJSON(url, function(events) {
                calendar.fullCalendar('removeEvents');
                calendar.fullCalendar('addEventSource', events);
                calendar.fullCalendar('rerenderEvents');
                describeEvents(events);
            });
        }

        /* Say how much is in view, and when the first of it falls.

           A month whose work all sits in its last week looks exactly like a
           month with no work in it: the empty weeks fill the screen and the
           ones holding tasks are below the fold. This is the cheap way to
           tell "nothing here" apart from "keep scrolling". */
        function describeEvents(events) {
            var label = $('#sb-cal-count');
            if (!label.length) { return; }

            if (!events || events.length === 0) {
                label.text(label.attr('data-empty') || 'No tasks in this view');
                return;
            }

            var earliest = null;
            for (var i = 0; i < events.length; i++) {
                var start = moment(events[i].start);
                if (earliest === null || start.isBefore(earliest)) { earliest = start; }
            }

            label.text(events.length + (events.length === 1 ? ' task' : ' tasks') + ', from ' + earliest.format('D MMM'));
        }

        calendar.fullCalendar({
            locale: $("html").attr('lang'),
            editable: true,
            eventLimit: true,
            firstDay: 1, // Start every week on Monday (0=Sun, 1=Mon)
            defaultView: mode,
            header: {
                left: 'prev,next today',
                center: 'title',
                right: 'month,agendaWeek,agendaDay'
            },
            eventClick: function(calEvent, jsEvent, view) {
                if (calEvent.id) {
                    var projectId = calEvent.project_id || '';
                    var drawerUrl = '?controller=TaskPanelController&action=show&plugin=TaskManager&task_id=' + calEvent.id + '&project_id=' + projectId;
                    var taskLink = $('<a></a>')
                        .attr('href', '?controller=TaskViewController&action=show&task_id=' + calEvent.id + '&project_id=' + projectId)
                        .attr('data-zp-open', drawerUrl)
                        .css('display', 'none')
                        .appendTo('body');

                    taskLink[0].click();
                    taskLink.remove();
                    return false;
                }
            },
            eventDrop: function(event) {
                $.ajax({
                    cache: false,
                    url: options.saveUrl,
                    contentType: "application/json",
                    type: "POST",
                    processData: false,
                    data: JSON.stringify({
                        "task_id": event.id,
                        "date_due": event.start.format()
                    })
                });
            },
            viewRender: function(view) {
                for (var id in modeMapping) {
                    if (modeMapping[id] === view.name) {
                        window.location.hash = id;
                        break;
                    }
                }
                fetchCalendarEvents();
            }
        });

        // Bind filter change events
        $(document).on('change', '#sb-cal-assignee-filter, #sb-cal-project-filter, #sb-cal-status-filter, #sb-cal-priority-filter', function() {
            fetchCalendarEvents();
        });

        var searchTimeout = null;
        $(document).on('input', '#sb-cal-search-filter', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                fetchCalendarEvents();
            }, 300);
        });

        $(document).on('click', '#sb-cal-filter-reset', function(e) {
            e.preventDefault();
            var assignee = $('#sb-cal-assignee-filter');
            assignee.val(assignee.attr('data-me'));
            $('#sb-cal-project-filter').val('0');
            $('#sb-cal-status-filter').val('open');
            $('#sb-cal-priority-filter').val('0');
            $('#sb-cal-search-filter').val('');
            fetchCalendarEvents();
        });
    };
});

KB.on('dom.ready', function () {
    function goToLink (selector) {
        if (! KB.modal.isOpen()) {
            var element = KB.find(selector);

            if (element !== null) {
                window.location = element.attr('href');
            }
        }
    }

    KB.onKey('v+c', function () {
        goToLink('a.view-calendar');
    });
});
