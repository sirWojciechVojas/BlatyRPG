import { describe, expect, it, vi } from "vitest";
import { wallChangeMessage } from "@/lib/realtime/realtimeProtocol";
import {
  createRealtimeWallActions,
  routeRealtimeWallEvent,
} from "@/store/modules/realtime/wallActions";

describe("realtime wall synchronization", () => {
  it("builds a scoped wall update message", () => {
    expect(
      wallChangeMessage({
        requestId: "wall-update-1",
        operation: "update",
        sceneId: 4,
        wallId: 8,
        revision: 2,
        changes: { doorState: "open" },
      }),
    ).toEqual({
      v: 1,
      type: "wall.change",
      requestId: "wall-update-1",
      operation: "update",
      sceneId: 4,
      wallId: 8,
      revision: 2,
      changes: { doorState: "open" },
    });
  });

  it("applies authoritative wall events to the VTT store", () => {
    const context = {
      rootState: { vtt: {} },
      commit: vi.fn(),
      dispatch: vi.fn(),
    };
    routeRealtimeWallEvent(context, {
      type: "wall.updated",
      payload: {
        wall: {
          id: 8,
          sceneId: 4,
          type: "door",
          x1: 0,
          y1: 50,
          x2: 100,
          y2: 50,
          revision: 3,
          doorState: "open",
        },
      },
    });
    expect(context.commit).toHaveBeenCalledWith(
      "vtt/UPSERT_WALL",
      expect.objectContaining({ id: 8, sceneId: 4, revision: 3 }),
      { root: true },
    );
  });

  it("sends the loaded wall revision through the current session", () => {
    const changeWall = vi.fn().mockReturnValue(true);
    const actions = createRealtimeWallActions(() => ({ changeWall }));
    const sent = actions.changeWall(
      { rootState: { vtt: { selectedSceneId: 4 } } },
      {
        operation: "update",
        wall: { id: 8, sceneId: 4, revision: 2 },
        changes: { doorState: "open" },
      },
    );
    expect(sent).toBe(true);
    expect(changeWall).toHaveBeenCalledWith(
      expect.objectContaining({
        operation: "update",
        sceneId: 4,
        wallId: 8,
        revision: 2,
      }),
    );
  });
});
