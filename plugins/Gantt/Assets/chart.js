if (typeof $ === 'undefined' && typeof jQuery !== 'undefined') {
    var $ = jQuery;
}

var Gantt = function() {
    this.data = [];
    this.options = {
        container: "#gantt-chart",
        scale: "quarters",
        showWeekends: true,
        showToday: true,
        allowMoves: true,
        allowResizes: true,
        cellWidth: 18,
        cellHeight: 70,
        slideWidth: 1000,
        vHeaderWidth: 320
    };
};

/* ------------------------------------------------------------------ */
/*  Save after drag / resize                                           */
/* ------------------------------------------------------------------ */
Gantt.prototype.saveRecord = function(record) {
    var url = $(this.options.container).data("save-url");
    if (!url) return;
    $.ajax({
        cache: false, url: url, contentType: "application/json",
        type: "POST", processData: false,
        data: JSON.stringify(record)
    });
};

/* ------------------------------------------------------------------ */
/*  Entry point                                                        */
/* ------------------------------------------------------------------ */
Gantt.prototype.show = function() {
    var container = $(this.options.container);
    if (!container.length) return;

    var raw = container.data('records');
    if (!raw || !raw.length) {
        var attr = container.attr('data-records');
        if (attr) {
            try { raw = JSON.parse(attr); } catch(e) {}
        }
    }
    if (!raw || !raw.length) return;

    this.data = this.prepareData(raw);

    /* Scale: prefer this.options.scale if set, else read dropdown */
    var sel = document.getElementById('zg-scale-select');
    if (this.options.scale) {
        if (sel && sel.value !== this.options.scale) sel.value = this.options.scale;
    } else if (sel) {
        this.options.scale = sel.value;
    } else {
        this.options.scale = 'quarters';
    }

    /* Map scale to cellWidth */
    var cw = { years: 10, quarters: 18, months: 26, weeks: 36 };
    this.options.cellWidth = cw[this.options.scale] || 18;

    var minDays = Math.floor((this.options.slideWidth / this.options.cellWidth) + 5);
    var range = this.getDateRange(minDays);
    var startDate = range[0];
    var endDate   = range[1];

    var chart = jQuery("<div>", {"class": "ganttview"});
    chart.append(this.renderVerticalHeader());
    chart.append(this.renderSlider(startDate, endDate));

    /* The scale drives more than cell width: at 10px a day, per-day rules
       and weekend shading turn the chart into a grey haze, so the
       stylesheet switches those off by scale rather than by guessing. */
    container
        .removeClass("zgantt-scale-years zgantt-scale-quarters zgantt-scale-months zgantt-scale-weeks")
        .addClass("zgantt-scale-" + this.options.scale)
        .empty().append(chart);

    /* Layout markers after the chart is in the DOM */
    var self = this;
    window.requestAnimationFrame(function(){
        self.layoutMarkers(container);
        self.scrollToFocus(container, startDate);
    });
    window.setTimeout(function(){ self.layoutMarkers(container); }, 250);

    jQuery("div.ganttview-grid-row div.ganttview-grid-row-cell:last-child", container).addClass("last");

    if (!container.data('readonly')) {
        this.listenForBlockResize(startDate);
        this.listenForBlockMove(startDate);
    } else {
        this.options.allowResizes = false;
        this.options.allowMoves   = false;
    }
};

/* ------------------------------------------------------------------ */
/*  Prepare data (array → Date)                                        */
/* ------------------------------------------------------------------ */
Gantt.prototype.prepareData = function(data) {
    var out = [];
    for (var i = 0; i < data.length; i++) {
        var d = JSON.parse(JSON.stringify(data[i]));
        if (Array.isArray(d.start)) {
            d.start = new Date(d.start[0], d.start[1] - 1, d.start[2]);
        } else if (typeof d.start === 'string') {
            d.start = new Date(d.start);
        }
        if (Array.isArray(d.end)) {
            d.end = new Date(d.end[0], d.end[1] - 1, d.end[2]);
        } else if (typeof d.end === 'string') {
            d.end = new Date(d.end);
        }
        out.push(d);
    }
    return out;
};

/* ------------------------------------------------------------------ */
/*  Date range (aligned by scale)                                      */
/* ------------------------------------------------------------------ */
Gantt.prototype.getDateRange = function(minDays) {
    var min = new Date(), max = new Date();
    for (var i = 0; i < this.data.length; i++) {
        var s = this.data[i].start, e = this.data[i].end;
        if (i === 0) { min = new Date(s); max = new Date(e); }
        if (s < min) min = new Date(s);
        if (e > max) max = new Date(e);
    }

    /* Today belongs on the chart whatever the tasks say - a plan that has
       drifted into the past is exactly when you need to see where today is. */
    var today = new Date(); today.setHours(0,0,0,0);
    if (today < min) { min = new Date(today); }
    if (today > max) { max = new Date(today); }

    if (this.options.scale === 'years') {
        // Encompass full year(s) Jan 1 to Dec 31
        min = new Date(min.getFullYear(), 0, 1);
        max = new Date(max.getFullYear(), 11, 31);
    } else if (this.options.scale === 'quarters') {
        /* A quarter on its own reads as a wall of days with no before or
           after. One quarter of padding either side is what makes it a
           timeline: you can see what ran up to this work and what follows. */
        var startQ = Math.floor(min.getMonth() / 3);
        min = new Date(min.getFullYear(), (startQ - 1) * 3, 1);
        var endQ = Math.floor(max.getMonth() / 3);
        max = new Date(max.getFullYear(), (endQ + 2) * 3, 0);
        if (this.daysBetween(min, max) < minDays) {
            max = this.addDays(new Date(min), minDays);
        }
    } else if (this.options.scale === 'months') {
        // Align to the 1st, and keep a month of context on each side.
        min = new Date(min.getFullYear(), min.getMonth() - 1, 1);
        max = new Date(max.getFullYear(), max.getMonth() + 2, 0);
        if (this.daysBetween(min, max) < minDays) {
            max = this.addDays(new Date(min), minDays);
        }
    } else { // weeks
        // Align to Monday of start week
        var dow = min.getDay();
        var diffToMon = (dow === 0 ? -6 : 1 - dow);
        min = this.addDays(new Date(min), diffToMon - 7);
        var edow = max.getDay();
        max = this.addDays(new Date(max), (edow === 0 ? 0 : 7 - edow) + 7);
        if (this.daysBetween(min, max) < minDays) {
            max = this.addDays(new Date(min), minDays);
        }
    }
    return [min, max];
};

/* ------------------------------------------------------------------ */
/*  Left pane: ID + TASK NAME                                          */
/* ------------------------------------------------------------------ */
Gantt.prototype.renderVerticalHeader = function() {
    var hdr = jQuery("<div>", {"class": "ganttview-vtheader"});
    var isProject = this.data.length && this.data[0].type !== 'task';
    var head = jQuery("<div>", {"class": "zgantt-vthead"})
        .append(jQuery("<span>", {"class": "col-id"}).text("ID"))
        .append(jQuery("<span>", {"class": "col-name"}).text(isProject ? "PROJECT NAME" : "TASK NAME"));
    hdr.append(head);

    var series = jQuery("<div>", {"class": "ganttview-vtheader-item"})
        .append(jQuery("<div>", {"class": "ganttview-vtheader-series"}));

    for (var i = 0; i < this.data.length; i++) {
        var rec = this.data[i];
        var code = rec.code || (isProject ? 'P' + rec.id : '#' + rec.id);
        var panel = rec.panel_url || rec.link;

        var row = jQuery("<div>", {"class": "ganttview-vtheader-series-name"});
        row.append(jQuery("<span>", {"class": "row-id-cell"}).text(code));

        var link = jQuery("<a>", {
            "class": "row-task-title", "href": rec.link,
            "data-zp-open": panel, "title": rec.title
        }).text(rec.title);

        if (rec.priority === 1) {
            link.append(jQuery("<span>", {"class": "badge-critical-tag"}).html('<i class="fa fa-fire"></i> P1'));
        }
        row.append(link);
        series.find(".ganttview-vtheader-series").append(row);
    }

    hdr.append(series);
    return hdr;
};

/* ------------------------------------------------------------------ */
/*  Slider: header + grid + bars + markers                             */
/* ------------------------------------------------------------------ */
Gantt.prototype.renderSlider = function(startDate, endDate) {
    var slide = jQuery("<div>", {"class": "ganttview-slide-container", "css": {"position":"relative"}});
    var dates = this.getDates(startDate, endDate);

    slide.append(this.renderHorizontalHeader(dates));
    slide.append(this.renderGrid(dates));
    slide.append(this.addBlockContainers());
    this.addBlocks(slide, startDate);

    /* Today + Est Completion markers — ONLY these two lines */
    var today = new Date(); today.setHours(0,0,0,0);
    var start = new Date(startDate); start.setHours(0,0,0,0);
    var end   = new Date(endDate);   end.setHours(0,0,0,0);
    var self = this;
    var mkr = function(date, cls, text) {
        if (!date || date < start || date > end) return;
        var days = Math.round((date - start) / 86400000);
        var left = days * self.options.cellWidth + Math.floor(self.options.cellWidth / 2);
        slide.append(jQuery("<div>",{"class":"zg-marker "+cls,"css":{"left":left+"px"}})
            .append(jQuery("<span>",{"class":"zg-marker-label"}).text(text)));
    };
    var maxEnd = null;
    for (var i = 0; i < this.data.length; i++) {
        var d = this.data[i].end;
        if (d && (!maxEnd || d > maxEnd)) maxEnd = new Date(d);
    }
    if (maxEnd && maxEnd.getTime() === today.getTime()) {
        mkr(today, "is-today is-est", "Today · Est. completion");
    } else {
        mkr(today, "is-today", "Today");
        if (maxEnd) {
            mkr(maxEnd, "is-est", "Estimated completion");
        }
    }

    return slide;
};

/* ------------------------------------------------------------------ */
/*  Header: 4 scales (Years / Quarters / Months / Weeks)               */
/* ------------------------------------------------------------------ */
Gantt.prototype.renderHorizontalHeader = function(dates) {
    var self  = this;
    var hdr   = jQuery("<div>",{"class":"ganttview-hzheader"});
    var scale = this.options.scale || 'quarters';
    var cw    = this.options.cellWidth;
    var totalW = 0;

    if (scale === 'years') {
        /* Top = Year (e.g. 2026), Sub = Q1, Q2, Q3, Q4 */
        var topR = jQuery("<div>",{"class":"ganttview-hzheader-top"});
        var subR = jQuery("<div>",{"class":"ganttview-hzheader-sub"});
        for (var y in dates) {
            var yDays = 0;
            for (var m in dates[y]) yDays += dates[y][m].length;
            var yw = yDays * cw;
            totalW += yw;
            topR.append(jQuery("<div>",{"class":"hz-cell-top","css":{"width":yw+"px"}}).append(self.hzLabel(y)));

            /* Quarters within this year */
            var qDays = {1:0, 2:0, 3:0, 4:0};
            for (var m in dates[y]) {
                var qi = Math.floor(parseInt(m, 10)/3) + 1;
                qDays[qi] = (qDays[qi]||0) + dates[y][m].length;
            }
            for (var qi = 1; qi <= 4; qi++) {
                if (qDays[qi] > 0) {
                    var qw = qDays[qi] * cw;
                    subR.append(jQuery("<div>",{"class":"hz-cell-sub is-wide","css":{"width":qw+"px"}}).append(self.hzLabel("Q"+qi)));
                }
            }
        }
        topR.css("width", totalW+"px");
        subR.css("width", totalW+"px");
        hdr.append(topR).append(subR);
    } else if (scale === 'quarters') {
        /* Top = Q3 '2026, Sub = JAN FEB MAR */
        var topR = jQuery("<div>",{"class":"ganttview-hzheader-top"});
        var subR = jQuery("<div>",{"class":"ganttview-hzheader-sub"});
        var SM = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
        for (var y in dates) {
            var qMap = {};
            for (var m in dates[y]) {
                var qi = Math.floor(parseInt(m, 10)/3) + 1;
                if (!qMap[qi]) qMap[qi] = [];
                qMap[qi].push({m: parseInt(m, 10), days: dates[y][m].length});
            }
            for (var qi in qMap) {
                var qw = 0;
                for (var mi = 0; mi < qMap[qi].length; mi++) {
                    var mw = qMap[qi][mi].days * cw;
                    qw += mw;
                    subR.append(jQuery("<div>",{"class":"hz-cell-sub is-wide","css":{"width":mw+"px"}}).append(self.hzLabel(SM[qMap[qi][mi].m])));
                }
                totalW += qw;
                topR.append(jQuery("<div>",{"class":"hz-cell-top","css":{"width":qw+"px"}}).append(self.hzLabel("Q"+qi+" '"+y)));
            }
        }
        topR.css("width", totalW+"px");
        subR.css("width", totalW+"px");
        hdr.append(topR).append(subR);
    } else if (scale === 'months') {
        /* Top = Month Year (e.g. September 2026), Sub = day numbers 1, 2, 3 ... */
        var topR = jQuery("<div>",{"class":"ganttview-hzheader-top"});
        var subR = jQuery("<div>",{"class":"ganttview-hzheader-sub"});
        var FM = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        for (var y in dates) {
            for (var m in dates[y]) {
                var mw = dates[y][m].length * cw;
                totalW += mw;
                topR.append(jQuery("<div>",{"class":"hz-cell-top","css":{"width":mw+"px"}}).append(self.hzLabel(FM[m]+' '+y)));
                for (var d in dates[y][m]) {
                    var dObj = dates[y][m][d];
                    var cls = "hz-cell-sub";
                    if (dObj.getDate() === 1) { cls += " is-monthstart"; }
                    if (self.isWeekend(dObj)) { cls += " is-weekend"; }
                    if (self.isToday(dObj))   { cls += " is-today"; }
                    subR.append(jQuery("<div>",{"class":cls,"css":{"width":cw+"px"}}).text(dObj.getDate()));
                }
            }
        }
        topR.css("width", totalW+"px");
        subR.css("width", totalW+"px");
        hdr.append(topR).append(subR);
    } else { /* weeks */
        var topR = jQuery("<div>",{"class":"ganttview-hzheader-top"});
        var subR = jQuery("<div>",{"class":"ganttview-hzheader-sub"});
        var WSM = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        var DN = ['Su','Mo','Tu','We','Th','Fr','Sa'];
        var allDates = [];
        for (var y in dates) for (var m in dates[y]) for (var d in dates[y][m]) allDates.push(dates[y][m][d]);
        var bands = [], cur = null;
        for (var i = 0; i < allDates.length; i++) {
            var dow = allDates[i].getDay(), iso = (dow === 0 ? 7 : dow);
            if (!cur || iso === 1) {
                cur = {from: allDates[i], to: allDates[i], count: 0};
                bands.push(cur);
            }
            cur.to = allDates[i]; cur.count++;
        }
        for (var b = 0; b < bands.length; b++) {
            var bw = bands[b].count * cw;
            totalW += bw;
            var wn = this.isoWeek(bands[b].from);
            var lbl = 'W' + wn + ' · ' + bands[b].from.getDate() + ' ' + WSM[bands[b].from.getMonth()] + ' - ' + bands[b].to.getDate() + ' ' + WSM[bands[b].to.getMonth()] + ', ' + bands[b].to.getFullYear();
            topR.append(jQuery("<div>",{"class":"hz-cell-top","css":{"width":bw+"px"}}).append(self.hzLabel(lbl)));
        }
        for (var i = 0; i < allDates.length; i++) {
            var dObj = allDates[i];
            var dayTxt = DN[dObj.getDay()] + ' ' + dObj.getDate();
            var cls = "hz-cell-sub";
            if (dObj.getDate() === 1) { cls += " is-monthstart"; }
            if (self.isWeekend(dObj)) { cls += " is-weekend"; }
            if (self.isToday(dObj))   { cls += " is-today"; }
            subR.append(jQuery("<div>",{"class":cls,"css":{"width":cw+"px"}}).text(dayTxt));
        }
        topR.css("width", totalW+"px");
        subR.css("width", totalW+"px");
        hdr.append(topR).append(subR);
    }

    hdr.css("width", totalW+"px");
    return hdr;
};

Gantt.prototype.isoWeek = function(d) {
    var t = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    t.setDate(t.getDate() + 4 - (t.getDay()||7));
    var y1 = new Date(t.getFullYear(),0,1);
    return Math.ceil((((t-y1)/86400000)+1)/7);
};

/* ------------------------------------------------------------------ */
/*  Header label that survives scrolling                               */
/* ------------------------------------------------------------------ */
/* A year cell is 3650px wide and a quarter cell 900px. Centring text in
   one of those puts the label thousands of pixels from wherever you are
   looking, which is why the year row read as blank. The label is its own
   sticky element instead, so "2026" stays on screen for as long as 2026
   does. */
Gantt.prototype.hzLabel = function(text) {
    return jQuery("<span>", {"class": "hz-cell-label"}).text(text);
};

/* ------------------------------------------------------------------ */
/*  Scroll to where the work actually is                               */
/* ------------------------------------------------------------------ */
/* At the year scale the chart is 3650px wide and the bars sit in
   September - around x=2900. Left at its default scroll position the
   viewport shows January, which is empty, and the chart looks broken.
   This puts today (or the first bar, if today is off the chart) a little
   in from the left edge. */
Gantt.prototype.scrollToFocus = function(container, startDate) {
    /* The element that actually scrolls is the slide container - the
       outer #gantt-chart never overflows, so scrolling it does nothing. */
    var scroller = container.find(".ganttview-slide-container").first();
    if (!scroller.length) { scroller = container; }

    var el = scroller.get(0);
    if (!el || el.scrollWidth <= el.clientWidth) { return; }

    var focus = new Date(); focus.setHours(0, 0, 0, 0);
    var start = new Date(startDate); start.setHours(0, 0, 0, 0);

    var earliest = null;
    for (var i = 0; i < this.data.length; i++) {
        var d = this.data[i].start;
        if (d && (!earliest || d < earliest)) { earliest = new Date(d); }
    }
    if (earliest && earliest < focus) { focus = earliest; }

    var days = this.daysBetween(start, focus);
    if (days <= 0) { return; }

    /* A margin of a few cells so the focus is not jammed against the
       vertical header. */
    var left = (days * this.options.cellWidth) - (this.options.cellWidth * 3);
    el.scrollLeft = Math.max(0, left);
};

/* ------------------------------------------------------------------ */
/*  Grid                                                               */
/* ------------------------------------------------------------------ */
Gantt.prototype.renderGrid = function(dates) {
    var grid = jQuery("<div>", {"class":"ganttview-grid"});
    var row  = jQuery("<div>", {"class":"ganttview-grid-row"});
    var cw   = this.options.cellWidth;
    for (var y in dates) for (var m in dates[y]) for (var d in dates[y][m]) {
        var cell = jQuery("<div>",{"class":"ganttview-grid-row-cell","css":{"width":cw+"px"}});
        if (dates[y][m][d].getDate() === 1) cell.addClass("is-monthstart");
        if (this.options.showWeekends && this.isWeekend(dates[y][m][d])) cell.addClass("ganttview-weekend");
        if (this.options.showToday && this.isToday(dates[y][m][d])) cell.addClass("ganttview-today");
        row.append(cell);
    }
    var w = jQuery("div.ganttview-grid-row-cell", row).length * cw;
    row.css("width", w+"px");
    grid.css("width", w+"px");
    for (var i = 0; i < this.data.length; i++) grid.append(row.clone());
    return grid;
};

/* ------------------------------------------------------------------ */
/*  Bar containers + bars                                              */
/* ------------------------------------------------------------------ */
Gantt.prototype.addBlockContainers = function() {
    var div = jQuery("<div>",{"class":"ganttview-blocks"});
    for (var i = 0; i < this.data.length; i++)
        div.append(jQuery("<div>",{"class":"ganttview-block-container"}));
    return div;
};

Gantt.prototype.addBlocks = function(slider, start) {
    var rows = jQuery("div.ganttview-blocks div.ganttview-block-container", slider);
    var cw = this.options.cellWidth;

    for (var i = 0; i < this.data.length; i++) {
        var s = this.data[i];
        var size   = this.daysBetween(s.start, s.end) + 1;
        var offset = this.daysBetween(start, s.start);

        var barW = Math.max(cw, (size * cw) - 4);
        var textDiv = jQuery("<div>",{"class":"ganttview-block-text",
            "css":{"width":(barW - 8)+"px"}});

        var blockCls = "ganttview-block" + (this.options.allowMoves ? " ganttview-block-movable" : "");
        if (s.priority === 1) blockCls += " is-critical-path";

        var panelUrl = s.panel_url || s.link;

        var block = jQuery("<div>",{
            "class": blockCls,
            "data-zp-open": panelUrl,
            "css":{
                "width":  barW + "px",
                "margin-left": (offset * cw) + "px"
            }
        }).append(textDiv);

        /* The name goes ON the bar when the bar is wide enough to hold it,
           and just after its right edge when it is not. It used to hang
           below the bar, which at a 70px row pitch put it level with the
           NEXT row's task - so every caption named the wrong row. */
        var nameFits = barW >= 90;

        if (s.type === 'task' && nameFits) {
            textDiv.text(s.progress + ' · ' + s.title);
        } else if (s.type === 'task' && size >= 2) {
            textDiv.text(s.progress);
        }

        /* Meta line above the bar: date range | assignee */
        var dRange = this.fmtDate(s.start) + ' - ' + this.fmtDate(s.end);
        if (s.assignee) dRange += ' | ' + s.assignee;
        block.append(jQuery("<div>",{"class":"zg-bar-floating-label"}).text(dRange));

        if (s.type === 'task' && ! nameFits) {
            block.append(jQuery("<div>",{"class":"zg-bar-sub-name"}).text(s.title));
        }

        /* Progress fill */
        if (s.progress && s.progress !== '0%') {
            block.append(jQuery("<div>",{"class":"ganttview-progress-bar","css":{"width": s.progress}}));
        }

        block.data("record", s);
        this.setBarColor(block, s);
        jQuery(rows[i]).append(block);
    }
};

Gantt.prototype.setBarColor = function(block, rec) {
    if (rec.priority === 1) return;
    if (rec.color && rec.color.background) {
        block.css({"background-color": rec.color.background, "border-color": rec.color.border || rec.color.background});
        block.find(".ganttview-block-text").css("color", "#333");
    }
};

/* ------------------------------------------------------------------ */
/*  Marker layout (Today / Estimated completion only)                  */
/* ------------------------------------------------------------------ */
Gantt.prototype.layoutMarkers = function(container) {
    var markers = jQuery("div.zg-marker", container);
    if (!markers.length) return;
    var slide = jQuery("div.ganttview-slide-container", container).first();
    var grid  = jQuery("div.ganttview-grid", container).first();
    var hH = 54;
    if (slide.length && grid.length) {
        var diff = Math.round(grid.offset().top - slide.offset().top);
        if (diff > 0) hH = diff;
    }
    var totalHeight = grid.length ? grid.outerHeight() : 300;

    markers.each(function(){
        var m = jQuery(this);
        m.css({
            "top": hH + "px",
            "height": totalHeight + "px"
        });
    });
};

/* ------------------------------------------------------------------ */
/*  Resize & Drag (jQuery-UI)                                          */
/* ------------------------------------------------------------------ */
Gantt.prototype.listenForBlockResize = function(startDate) {
    var self = this;
    jQuery("div.ganttview-block", this.options.container).resizable({
        grid: this.options.cellWidth, handles: "e,w", delay: 300,
        stop: function(){ var b = jQuery(this); self.updateDataAndPosition(b, startDate); self.saveRecord(b.data("record")); }
    });
};

Gantt.prototype.listenForBlockMove = function(startDate) {
    var self = this;
    jQuery("div.ganttview-block", this.options.container).draggable({
        axis: "x", delay: 300, grid: [this.options.cellWidth, this.options.cellWidth],
        stop: function(){ var b = jQuery(this); self.updateDataAndPosition(b, startDate); self.saveRecord(b.data("record")); }
    });
};

Gantt.prototype.updateDataAndPosition = function(block, startDate) {
    var container = jQuery("div.ganttview-slide-container", this.options.container);
    var scroll = container.scrollLeft();
    var offset = block.offset().left - container.offset().left - 1 + scroll;
    var record = block.data("record");
    record.not_defined = false;
    this.setBarColor(block, record);
    var dfs = Math.round(offset / this.options.cellWidth);
    record.start = this.addDays(new Date(startDate), dfs);
    var width = block.outerWidth();
    var nd = Math.round(width / this.options.cellWidth) - 1;
    record.end = this.addDays(new Date(record.start), nd);
    block.data("record", record);
    block.css({"top":"","left":"","position":"relative","margin-left":offset+"px"});
};

/* ------------------------------------------------------------------ */
/*  Utilities                                                          */
/* ------------------------------------------------------------------ */
Gantt.prototype.getDates = function(start, end) {
    var dates = [];
    dates[start.getFullYear()] = [];
    dates[start.getFullYear()][start.getMonth()] = [start];
    var last = start;
    while (this.compareDate(last, end) === -1) {
        var next = this.addDays(new Date(last), 1);
        if (!dates[next.getFullYear()]) dates[next.getFullYear()] = [];
        if (!dates[next.getFullYear()][next.getMonth()]) dates[next.getFullYear()][next.getMonth()] = [];
        dates[next.getFullYear()][next.getMonth()].push(next);
        last = next;
    }
    return dates;
};

Gantt.prototype.daysBetween = function(s, e) {
    if (!s || !e) return 0;
    var d1 = new Date(s.getFullYear(), s.getMonth(), s.getDate());
    var d2 = new Date(e.getFullYear(), e.getMonth(), e.getDate());
    return Math.round((d2 - d1) / 86400000);
};

Gantt.prototype.fmtDate = function(d) {
    if (!d) return '';
    return d.getFullYear() + '-' + (d.getMonth() + 1 < 10 ? '0' : '') + (d.getMonth() + 1) + '-' + (d.getDate() < 10 ? '0' : '') + d.getDate();
};

Gantt.prototype.isWeekend = function(d) { return d.getDay()%6 === 0; };
Gantt.prototype.isToday   = function(d) { return (new Date()).toDateString() === d.toDateString(); };
Gantt.prototype.addDays   = function(d, n) { d.setDate(d.getDate()+n); return d; };
Gantt.prototype.compareDate = function(a, b) {
    if (isNaN(a) || isNaN(b)) throw new Error(a+" - "+b);
    if (a instanceof Date && b instanceof Date) return a < b ? -1 : a > b ? 1 : 0;
    throw new TypeError(a+" - "+b);
};
