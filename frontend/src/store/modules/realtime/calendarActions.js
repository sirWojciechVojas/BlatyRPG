export const CALENDAR_REALTIME_TYPES = Object.freeze([
  "calendar.state.updated",
  "calendar.event.created",
  "calendar.event.updated",
  "calendar.event.deleted",
  "calendar.moon.updated",
]);

export const routeRealtimeCalendarEvent = (context, event) => {
  if (!CALENDAR_REALTIME_TYPES.includes(event.type)) return false;
  context
    .dispatch("calendar/applyRealtimeEvent", event, { root: true })
    .catch(() => {});
  return true;
};
