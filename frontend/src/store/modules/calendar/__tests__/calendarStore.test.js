import { describe, expect, it, vi } from "vitest";
import {
  calendarMutations,
  calendarState,
  createCalendarActions,
} from "../index";

const readyState = () => ({
  ...calendarState(),
  campaignId: 12,
  definition: { daysPerYear: 400 },
  worldState: { year: 2522, dayOfYear: 2, revision: 7 },
  loadedRange: { fromYear: 2522, fromDay: 1, toYear: 2522, toDay: 400 },
  phase: "ready",
});

const actionContext = (state) => {
  const commit = vi.fn((type, payload) =>
    calendarMutations[type](state, payload),
  );
  return { state, commit, dispatch: vi.fn(() => Promise.resolve([])) };
};

describe("calendar store synchronization", () => {
  it("ignores a stale state notification and accepts a newer server state", async () => {
    const state = readyState();
    const context = actionContext(state);
    const actions = createCalendarActions({});

    await actions.applyRealtimeEvent(context, {
      type: "calendar.state.updated",
      campaignId: 12,
      payload: {
        revision: 6,
        state: { year: 2522, dayOfYear: 9, revision: 6 },
      },
    });
    expect(state.worldState.dayOfYear).toBe(2);

    await actions.applyRealtimeEvent(context, {
      type: "calendar.state.updated",
      campaignId: 12,
      payload: {
        revision: 8,
        state: { year: 2522, dayOfYear: 3, revision: 8 },
      },
    });
    expect(state.worldState).toMatchObject({ dayOfYear: 3, revision: 8 });
  });

  it("reloads the visible range after a newer event notification", async () => {
    const state = readyState();
    const context = actionContext(state);
    const actions = createCalendarActions({});

    const applied = await actions.applyRealtimeEvent(context, {
      type: "calendar.event.updated",
      campaignId: 12,
      payload: { revision: 9, eventId: 44 },
    });

    expect(applied).toBe(true);
    expect(state.worldState.revision).toBe(9);
    expect(context.dispatch).toHaveBeenCalledWith("loadEvents", { year: 2522 });
  });

  it("sends only editable fields plus both revisions when updating an event", async () => {
    const state = readyState();
    const api = {
      updateEvent: vi.fn(() =>
        Promise.resolve({
          event: { id: 4, revision: 3, title: "Zmienione" },
          revision: 8,
        }),
      ),
    };
    const context = actionContext(state);

    await createCalendarActions(api).updateEvent(context, {
      id: 4,
      revision: 2,
      title: "Zmienione",
      allDay: true,
    });

    expect(api.updateEvent).toHaveBeenCalledWith(12, 4, {
      title: "Zmienione",
      allDay: true,
      expectedRevision: 7,
      eventRevision: 2,
    });
  });

  it("persists optional world-time visibility with the authoritative date", async () => {
    const state = readyState();
    const api = {
      setState: vi.fn(() =>
        Promise.resolve({
          state: {
            year: 2522,
            dayOfYear: 18,
            showTime: false,
            revision: 8,
          },
        }),
      ),
    };
    const context = actionContext(state);

    await createCalendarActions(api).setState(context, {
      year: 2522,
      dayOfYear: 18,
      time: "08:00",
      showTime: false,
    });

    expect(api.setState).toHaveBeenCalledWith(12, {
      year: 2522,
      dayOfYear: 18,
      time: "08:00",
      showTime: false,
      expectedRevision: 7,
    });
    expect(state.worldState).toMatchObject({
      dayOfYear: 18,
      showTime: false,
      revision: 8,
    });
  });
});
