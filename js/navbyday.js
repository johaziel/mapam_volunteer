/**
 * @file
 * ADD to Vis timeline nav by day functionnality.
 */

(function ($, Drupal, luxon, once) {
  const { DateTime } = luxon;

  Drupal.behaviors.navByDay = {
    attach: function (context, settings) {

      // ── Listen for vis.Timeline instances becoming ready ──────────────────
      once('navByDay', 'body', context).forEach(function () {

        // Event fired by the parent module's JS after new vis.Timeline().
        $(document).on('visTimelineReady.arrows', function (event, timelineId, timeline) {

          if (!timeline) return;
          var range = timeline.getWindow();
          var visibleItems = timeline.itemsData.get();

          if (visibleItems.length > 0) {
            range.start = visibleItems[0].start;
            range.end = visibleItems[visibleItems.length - 1].end;
          }

          // Calculate total different days in the visible range.
          var startDate = DateTime.fromJSDate(range.start).startOf('day');
          var endDate = DateTime.fromJSDate(range.end).startOf('day');
          var totalDays = Math.ceil(endDate.diff(startDate, 'days').days) + 1;

          // Only show nav by day if the range is 15 days or less to avoid overwhelming the UI.
          if (totalDays <= 15) {
            var navDaysContainer = document.getElementById("navDaysContainer");

            if (!navDaysContainer) {
              navDaysContainer = document.createElement("div");
              navDaysContainer.id = "navDaysContainer";
              timeline.dom.container.parentNode.insertBefore(navDaysContainer, timeline.dom.container);
            }

            navDaysContainer.innerHTML = "";

            for (let i = 0; i < totalDays; i++) {
              let button = document.createElement("button");
              button.id = "navDay" + (i + 1);

              let dayDate = i === 0
                ? DateTime.fromJSDate(range.start)
                : DateTime.fromJSDate(range.start).startOf('day').plus({ days: i });

              button.innerText = dayDate.toLocaleString(DateTime.DATE_MED);
              button.onclick = function () {
                navByDay(timeline, dayDate);
              };

              navDaysContainer.appendChild(button);
            }
          }
        });
      });
    }
  };

  function navByDay(timeline, dayDate) {
    var startOfDay = dayDate.set({ hour: 7, minute: 0, second: 0, millisecond: 0 }).toJSDate();
    var endOfDay = dayDate.set({ hour: 23, minute: 59, second: 59, millisecond: 999 }).toJSDate();
    timeline.setWindow({
      start: startOfDay,
      end: endOfDay,
    });
  }

}(jQuery, Drupal, luxon, once)); 
