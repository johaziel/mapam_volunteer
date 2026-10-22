/**
 * @file
 * Adds a lock toggle and a navigation by day to the Vis timelines.
 *
 * Uses the events of Views Vis Timeline: "visTimeline:ready" (the timeline is
 * created, the instance is event.detail) and "visTimeline:itemsUpdated" (items
 * were added or changed, the instance is event.detail.instance).
 */

((Drupal, once) => {
  // Editing starts locked, to avoid moving items by accident.
  const START_LOCKED = true;

  // The navigation by day is only shown up to this number of days.
  const MAX_DAYS = 15;

  const LOCKED = {
    add: false,
    updateTime: false,
    updateGroup: false,
    remove: false,
    // Item level "editable" values computed from entity access would win
    // otherwise.
    overrideItems: true,
  };

  /**
   * Whether the current user may change something on the timeline.
   */
  function canEdit(config) {
    const editable = config.options.editable || {};
    return Boolean(
      editable.add ||
        editable.updateTime ||
        editable.updateGroup ||
        editable.remove ||
        config.options.groupEditable,
    );
  }

  /**
   * Locks or unlocks the editing of a timeline.
   */
  function setLocked(instance, button, locked) {
    const { options } = instance.config;
    instance.timeline.setOptions({
      editable: locked ? LOCKED : options.editable,
      groupEditable: locked ? false : options.groupEditable || false,
    });
    instance.element.classList.toggle('is-locked', locked);
    button.setAttribute('aria-pressed', String(locked));
    button.title = locked
      ? Drupal.t('Unlock the timeline to edit it')
      : Drupal.t('Lock the timeline');
    button.innerHTML = locked
      ? '<i class="bi bi-lock-fill" aria-hidden="true"></i>'
      : '<i class="bi bi-unlock-fill" aria-hidden="true"></i>';
    button.setAttribute('aria-label', button.title);
  }

  /**
   * Adds the lock toggle before the timeline.
   */
  function addLockToggle(instance) {
    if (!canEdit(instance.config)) {
      return;
    }
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'timeline-toggle-editable';
    let locked = START_LOCKED;
    setLocked(instance, button, locked);
    button.addEventListener('click', () => {
      locked = !locked;
      setLocked(instance, button, locked);
    });
    instance.element.parentNode.insertBefore(button, instance.element);
  }

  /**
   * Returns the local midnight of a date.
   */
  function startOfDay(date) {
    const day = new Date(date);
    day.setHours(0, 0, 0, 0);
    return day;
  }

  /**
   * Builds or rebuilds the day buttons of a timeline, from its items.
   */
  function buildDayNavigation(instance) {
    let container = instance.element.parentNode.querySelector(
      ':scope > .nav-days',
    );
    const items = instance.items.get();
    if (!items.length) {
      container?.remove();
      return;
    }

    const starts = items.map((item) => new Date(item.start).getTime());
    const ends = items.map((item) => new Date(item.end || item.start).getTime());
    const first = startOfDay(Math.min(...starts));
    const last = startOfDay(Math.max(...ends));
    const totalDays = Math.round((last - first) / 86400000) + 1;
    if (totalDays > MAX_DAYS) {
      container?.remove();
      return;
    }

    if (!container) {
      container = document.createElement('div');
      container.className = 'nav-days';
      container.setAttribute('role', 'group');
      container.setAttribute('aria-label', Drupal.t('Go to a day'));
      instance.element.parentNode.insertBefore(container, instance.element);
    }
    container.textContent = '';

    // Without the year to save space, and without the month beyond a week.
    const format = new Intl.DateTimeFormat(
      document.documentElement.lang || undefined,
      totalDays <= 7
        ? { weekday: 'short', day: '2-digit', month: 'short' }
        : { weekday: 'short', day: '2-digit' },
    );
    for (let i = 0; i < totalDays; i++) {
      const day = new Date(first);
      day.setDate(first.getDate() + i);
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = format.format(day);
      button.addEventListener('click', () => {
        const start = new Date(day);
        start.setHours(7, 0, 0, 0);
        const end = new Date(day);
        end.setHours(23, 59, 59, 999);
        instance.timeline.setWindow(start, end);
      });
      container.appendChild(button);
    }
  }

  document.addEventListener('visTimeline:ready', (event) => {
    const instance = event.detail;
    if (!instance || !once('navByDay', instance.element).length) {
      return;
    }
    addLockToggle(instance);
    buildDayNavigation(instance);
  });

  document.addEventListener('visTimeline:itemsUpdated', (event) => {
    if (event.detail?.instance) {
      buildDayNavigation(event.detail.instance);
    }
  });
})(Drupal, once);
