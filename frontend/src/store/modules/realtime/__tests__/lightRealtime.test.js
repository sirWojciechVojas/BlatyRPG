import { describe, expect, it, vi } from "vitest";
import { sceneElementChangeMessage } from "@/lib/realtime/realtimeProtocol";
import {
  createRealtimeLightActions,
  routeRealtimeLightEvent,
} from "@/store/modules/realtime/lightActions";

describe("realtime light synchronization", () => {
  it("builds a scoped light update message", () => {
    expect(
      sceneElementChangeMessage("light", {
        requestId: "light-update-1",
        operation: "update",
        sceneId: 4,
        lightId: 8,
        revision: 2,
        changes: { intensity: 0.5 },
      }),
    ).toEqual({
      v: 1,
      type: "light.change",
      requestId: "light-update-1",
      operation: "update",
      sceneId: 4,
      lightId: 8,
      revision: 2,
      changes: { intensity: 0.5 },
    });
  });

  it("applies authoritative light events to the VTT store", () => {
    const context = {
      rootState: { vtt: {} },
      commit: vi.fn(),
      dispatch: vi.fn(),
    };
    routeRealtimeLightEvent(context, {
      type: "light.updated",
      payload: {
        light: {
          id: 8,
          sceneId: 4,
          x: 100,
          y: 150,
          brightRadius: 200,
          dimRadius: 400,
          color: "#FFD27A",
          intensity: 0.5,
          enabled: true,
          revision: 3,
        },
      },
    });
    expect(context.commit).toHaveBeenCalledWith(
      "vtt/UPSERT_LIGHT",
      expect.objectContaining({ id: 8, sceneId: 4, revision: 3 }),
      { root: true },
    );
  });

  it("applies synchronized global illumination without polling", () => {
    const context = {
      rootState: { vtt: {} },
      commit: vi.fn(),
      dispatch: vi.fn(),
    };
    routeRealtimeLightEvent(context, {
      type: "scene.updated",
      payload: {
        scene: { id: 4, global_light_level: "0.2", revision: 3 },
      },
    });
    expect(context.commit).toHaveBeenCalledWith(
      "vtt/UPSERT_SCENE",
      expect.objectContaining({ id: 4, globalLightLevel: 0.2, revision: 3 }),
      { root: true },
    );
  });

  it("sends the loaded light revision through the current session", () => {
    const changeLight = vi.fn().mockReturnValue(true);
    const actions = createRealtimeLightActions(() => ({ changeLight }));
    const sent = actions.changeLight(
      { rootState: { vtt: { selectedSceneId: 4 } } },
      {
        operation: "update",
        light: { id: 8, sceneId: 4, revision: 2 },
        changes: { intensity: 0.5 },
      },
    );
    expect(sent).toBe(true);
    expect(changeLight).toHaveBeenCalledWith(
      expect.objectContaining({
        operation: "update",
        sceneId: 4,
        lightId: 8,
        revision: 2,
      }),
    );
  });
});
