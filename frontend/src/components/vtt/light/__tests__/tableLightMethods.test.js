import { describe, expect, it, vi } from "vitest";
import { tableLightMethods } from "../tableLightMethods";

const deferred = () => {
  let resolve;
  const promise = new Promise((done) => {
    resolve = done;
  });
  return { promise, resolve };
};

describe("authoritative light writes", () => {
  it("serializes edits and reads the newest revision before each send", async () => {
    const first = deferred();
    const state = {
      vtt: {
        lightPhase: "ready",
        lightsByScene: { 4: [{ id: 8, sceneId: 4, revision: 2 }] },
      },
    };
    const dispatch = vi
      .fn()
      .mockImplementationOnce(() => first.promise)
      .mockResolvedValueOnce(true);
    const component = {
      $store: {
        state,
        dispatch,
        commit: vi.fn((type, value) => {
          if (type === "vtt/SET_LIGHT_PHASE") state.vtt.lightPhase = value;
        }),
      },
    };
    const original = state.vtt.lightsByScene[4][0];

    const color = tableLightMethods.updateLight.call(component, {
      light: original,
      changes: { color: "#FFFFFF" },
    });
    const power = tableLightMethods.updateLight.call(component, {
      light: original,
      changes: { lumens: 2200 },
    });
    await Promise.resolve();
    await Promise.resolve();
    expect(dispatch).toHaveBeenCalledTimes(1);

    state.vtt.lightsByScene[4][0] = { ...original, revision: 3 };
    first.resolve(true);
    await color;
    await power;

    expect(dispatch).toHaveBeenCalledTimes(2);
    expect(dispatch.mock.calls[1][1].light.revision).toBe(3);
  });

  it("updates the permanent global light through the scene action", async () => {
    const state = { vtt: { lightPhase: "ready" } };
    const dispatch = vi.fn().mockResolvedValue({ id: 4 });
    const component = {
      selectedScene: { id: 4 },
      canManageLights: true,
      $store: {
        state,
        dispatch,
        commit: vi.fn((type, value) => {
          if (type === "vtt/SET_LIGHT_PHASE") state.vtt.lightPhase = value;
        }),
      },
    };

    await tableLightMethods.updateGlobalLight.call(component, 0.65);

    expect(dispatch).toHaveBeenCalledWith("vtt/updateSelectedScene", {
      globalLightLevel: 0.65,
    });
  });
});
