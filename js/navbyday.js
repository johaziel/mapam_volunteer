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
        console.log('navByDay behavior attached, listening for visTimelineReady event');
        // Event fired by the parent module's JS after new vis.Timeline().
        $(document).on('visTimelineReady', function (event, timelineId, timeline) {
          console.log('Received visTimelineReady event for timelineId:', timelineId);
          if (!timeline) return;

          // Add a toggle editable: true/false button to the timeline's DOM for testing.
          var toggleButton = document.createElement("div");
          let isEditable = timeline.options.editable ?? false;

          // Normalisation (au cas où editable était undefined au départ)
          timeline.setOptions({
            editable: {
              add: isEditable,
              updateTime: isEditable,
              updateGroup: isEditable,
              remove: isEditable
            }
          });

          toggleButton.innerHTML = isEditable ? '<i class="bi bi-unlock-fill"></i>' : '<i class="bi bi-lock-fill"></i>';
          toggleButton.title = isEditable ? 'Click to lock timeline (disable editing)' : 'Click to unlock timeline (enable editing)';
          toggleButton.style.cursor = "pointer";
          toggleButton.className = "timeline-toggle-editable";

          toggleButton.onclick = function () {
            isEditable = !isEditable;
            timeline.setOptions({
              editable: {
                add: isEditable,
                updateTime: isEditable,
                updateGroup: isEditable,
                remove: isEditable
              }
            });
            toggleButton.innerHTML = isEditable ? '<i class="bi bi-unlock-fill"></i>' : '<i class="bi bi-lock-fill"></i>';
          };
          timeline.dom.container.parentNode.insertBefore(toggleButton, timeline.dom.container);

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

              // Format the button label to a custom format, e.g. "Lun. 24 Jan". witout the year to save space.
              if (totalDays <= 7) {
                button.textContent  = dayDate.toLocaleString({ weekday: 'short', day: '2-digit', month: 'short' });
              }
              else {
                button.textContent  = dayDate.toLocaleString({ weekday: 'short', day: '2-digit' });
              }
              
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
