/**
 * @file
 * ADD to Vis timeline nav by day functionnality.
 */

(function ($, Drupal, drupalSettings,once) {

  Drupal.behaviors.navByDay = {
    attach: function (context, settings) {
      // DOM element where the Timeline will be attached
      var allcontainers = document.getElementsByClassName ('vis-timeline');

      var container = null;
      // loop trought children if there timeline is initialized
      for (let i = 0; i < allcontainers.length; i++) {
        const container = allcontainers[i];
        if (container.timeline) {

          // Dynamically create nav buttons for each day if the range is 15 days or less
          var range = container.timeline.initialRange
          var totalDays = Math.floor((range.end - range.start) / (24 * 60 * 60 * 1000)) + 1;

          if (totalDays <= 15) {
            // Add the container before vis-timeline-programmation_index-page_1 if not already present for the nav buttons
            var navDaysContainer = document.getElementById("navDaysContainer");
            if (!navDaysContainer) {
              navDaysContainer = document.createElement("div");
              navDaysContainer.id = "navDaysContainer";
              // Insert the navDaysContainer before the vis-timeline element
              container.parentNode.insertBefore(navDaysContainer, container);
            }

            navDaysContainer.innerHTML = ""; // Clear existing buttons
            for (let i = 0; i < totalDays; i++) {
              let button = document.createElement("button");
              button.id = "navDay" + (i + 1);
              button.innerText = moment(range.start).startOf('day').add(i, 'days').format('ddd D');
              //button.innerText = "Zoom Day " + (i + 1);
              button.onclick = function () {
                navByDay(container.timeline, i);
              };
              navDaysContainer.appendChild(button);
            }
          }
        }
      }
    }
  };

  function navByDay(timeline, dayIndex) {
    var initialRange = timeline.initialRange || timeline.getWindow();
    var startOfDay = moment(initialRange.start).startOf('day').add(dayIndex, 'days').add(7, 'hours').toDate();
    var endOfDay = moment(startOfDay).add(19, 'hours').toDate();
    timeline.setWindow({
      start: startOfDay,
      end: endOfDay,
    });
  }

}(jQuery, Drupal, drupalSettings,once)); 
