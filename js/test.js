/**
 * @file
 */

(function ($, Drupal, drupalSettings,once) {
  Drupal.behaviors.test = {
    attach: function (context, settings) {
      console.log("Test behavior attached.");
    }
  };
  navByDay = function (timeline, dayIndex) {
    var range = timeline.initialRange;
    var dayStart = moment(range.start).startOf('day').add(dayIndex, 'days').valueOf();
    var dayEnd = moment(dayStart).endOf('day').valueOf();
    timeline.setWindow(dayStart, dayEnd);
  };
}(jQuery, Drupal, drupalSettings,once)); 