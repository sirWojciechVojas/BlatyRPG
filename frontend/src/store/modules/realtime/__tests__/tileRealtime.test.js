import { describe, expect, it, vi } from "vitest";
import { sceneElementChangeMessage } from "@/lib/realtime/realtimeProtocol";
import {
  createRealtimeTileActions,
  routeRealtimeTileEvent,
} from "@/store/modules/realtime/tileActions";

describe("realtime tile synchronization", () => {
  it("builds a scoped tile update message", () => {
    expect(
      sceneElementChangeMessage("tile", {
        requestId: "tile-update-1",
        operation: "update",
        sceneId: 4,
        tileId: 9,
        revision: 2,
        changes: { opacity: 0.5 },
      }),
    ).toEqual({
      v: 1,
      type: "tile.change",
      requestId: "tile-update-1",
      operation: "update",
      sceneId: 4,
      tileId: 9,
      revision: 2,
      changes: { opacity: 0.5 },
    });
  });

  it("applies authoritative tile events to the VTT store", () => {
    const context = {
      rootState: { vtt: {} },
      commit: vi.fn(),
      dispatch: vi.fn(),
    };
    routeRealtimeTileEvent(context, {
      type: "tile.updated",
      payload: {
        tile: {
          id: 9,
          sceneId: 4,
          name: "Crate",
          assetUrl: "/crate.webp",
          mediaType: "image",
          layer: "background",
          width: 100,
          height: 100,
          opacity: 0.5,
          revision: 3,
        },
      },
    });
    expect(context.commit).toHaveBeenCalledWith(
      "vtt/UPSERT_TILE",
      expect.objectContaining({ id: 9, sceneId: 4, revision: 3 }),
      { root: true },
    );
  });

  it("sends the loaded tile revision through the current session", () => {
    const changeTile = vi.fn().mockReturnValue(true);
    const actions = createRealtimeTileActions(() => ({ changeTile }));
    const sent = actions.changeTile(
      { rootState: { vtt: { selectedSceneId: 4 } } },
      {
        operation: "update",
        tile: { id: 9, sceneId: 4, revision: 2 },
        changes: { opacity: 0.5 },
      },
    );
    expect(sent).toBe(true);
    expect(changeTile).toHaveBeenCalledWith(
      expect.objectContaining({
        operation: "update",
        sceneId: 4,
        tileId: 9,
        revision: 2,
      }),
    );
  });
});
