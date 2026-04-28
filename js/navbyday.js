/**
 * @file
 * ADD to Vis timeline nav by day functionnality.
 */

(function ($, Drupal, luxon, once) {
  const { DateTime } = luxon;

  Drupal.behaviors.navByDay = {
    attach: function (context, settings) {

      // once() garantit que ce code ne s'exécute qu'une seule fois par élément
      once('navByDay', '.vis-timeline', context).forEach(function (container) {

        if (!container.timeline) return;

        var range = container.timeline.getWindow();
        var visibleItems = container.timeline.itemsData.get();

        if (visibleItems.length > 0) {
          range.start = visibleItems[0].start;
          range.end = visibleItems[visibleItems.length - 1].end;
        }

        var totalDays = Math.round((range.end - range.start) / (24 * 60 * 60 * 1000)) + 1;

        if (totalDays <= 15) {
          var navDaysContainer = document.getElementById("navDaysContainer");

          if (!navDaysContainer) {
            navDaysContainer = document.createElement("div");
            navDaysContainer.id = "navDaysContainer";
            container.parentNode.insertBefore(navDaysContainer, container);
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
              navByDay(container.timeline, dayDate);
            };

            navDaysContainer.appendChild(button);
          }
        }
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
