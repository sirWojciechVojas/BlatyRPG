import { describe, expect, it, vi } from "vitest";
import { tableWallMethods } from "@/components/vtt/wall/tableWallMethods";

const wall = {
  id: 8,
  sceneId: 4,
  revision: 2,
  doorType: "door",
  doorState: "closed",
};

const component = () => {
  const dispatch = vi.fn().mockResolvedValue(true);
  return {
    dispatch,
    instance: {
      $store: {
        state: {
          vtt: {
            wallPhase: "ready",
            wallsByScene: { 4: [wall] },
          },
        },
        commit: vi.fn(),
        dispatch,
      },
    },
  };
};

describe("table wall portal interaction", () => {
  it("does not add silent=false to a normal right-click interaction", async () => {
    const { instance, dispatch } = component();

    await tableWallMethods.interactWall.call(instance, {
      wall,
      doorState: "open",
      actingTokenIds: [12],
    });

    expect(dispatch).toHaveBeenCalledWith("realtime/changeWall", {
      operation: "interact",
      wall,
      changes: { doorState: "open", actingTokenIds: [12] },
    });
  });

  it("sends silent only for an explicit silent secret-door action", async () => {
    const { instance, dispatch } = component();

    await tableWallMethods.interactWall.call(instance, {
      wall,
      doorState: "open",
      silent: true,
    });

    expect(dispatch).toHaveBeenCalledWith(
      "realtime/changeWall",
      expect.objectContaining({
        changes: {
          doorState: "open",
          actingTokenIds: [],
          silent: true,
        },
      }),
    );
  });
});
