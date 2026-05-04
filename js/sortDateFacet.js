(function ($, Drupal) {
  Drupal.behaviors.sortFacetSelect = {
    attach: function (context, settings) {
      
      const $select = $('select[name="field_smart_date"]', context);

      if (!$select.length) return;

      const mois = {
        'janvier': 1, 'février': 2, 'mars': 3, 'avril': 4,
        'mai': 5, 'juin': 6, 'juillet': 7, 'août': 8,
        'septembre': 9, 'octobre': 10, 'novembre': 11, 'décembre': 12
      };

      function parseDate(label) {
        // "06 juin 2026" → jour=06, mois=juin, année=2026
        const parts = label.trim().toLowerCase().split(/\s+/);
        const jour = parseInt(parts[0], 10);
        const moisNum = mois[parts[1]] || 0;
        const annee = parseInt(parts[2], 10);
        return new Date(annee, moisNum - 1, jour);
      }

      const selected = $select.val();
      const $empty = $select.find('option').filter('[value=""], [value="All"]');
      const $rest  = $select.find('option').not('[value=""], [value="All"]');

      const sorted = $rest.toArray().sort((a, b) => parseDate(a.text) - parseDate(b.text));

      $select.empty().append($empty).append(sorted);
      $select.val(selected);
    }
  };
})(jQuery, Drupal);