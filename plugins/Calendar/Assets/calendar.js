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

            var url = options.checkUrl;
            var params = {
                "start": view.start.format(),
                "end": view.end.format()
            };

            var customFilters = getActiveFilters();
            for (var f in customFilters) {
                params[f] = customFilters[f];
            }

            for (var key in params) {
                url += "&" + key + "=" + encodeURIComponent(params[key]);
            }

            $.getJSON(url, function(events) {
                calendar.fullCalendar('removeEvents');
                calendar.fullCalendar('addEventSource', events);
                calendar.fullCalendar('rerenderEvents');
            });
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
        $(document).on('change', '#sb-cal-project-filter, #sb-cal-status-filter, #sb-cal-priority-filter', function() {
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
