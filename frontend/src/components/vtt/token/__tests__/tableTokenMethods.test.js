import { describe, expect, it, vi } from "vitest";
import { tableTokenMethods } from "../tableTokenMethods";

describe("tableTokenMethods resources", () => {
  it("clears the active token when the canvas is selected", () => {
    const commit = vi.fn();
    tableTokenMethods.selectToken.call(
      { $store: { commit } },
      {
        tokenId: null,
        additive: false,
      },
    );

    expect(commit).toHaveBeenCalledWith("vtt/SELECT_TOKEN", { tokenId: null });
  });

  it("commits an area token selection as one batch", () => {
    const commit = vi.fn();
    tableTokenMethods.selectToken.call(
      { $store: { commit } },
      { tokenIds: [7, 8], additive: true },
    );
    expect(commit).toHaveBeenCalledWith("vtt/SELECT_TOKENS", {
      tokenIds: [7, 8],
      additive: true,
    });
  });

  it("focuses a linked character card from the token", () => {
    const openUtilityWindow = vi.fn();
    const vm = { focusedCharacterId: null, openUtilityWindow };

    tableTokenMethods.openActor.call(vm, 42);

    expect(vm.focusedCharacterId).toBe(42);
    expect(openUtilityWindow).toHaveBeenCalledWith("characters");
  });

  it("sends token resource updates through realtime without refreshing the scene", async () => {
    const dispatch = vi.fn().mockResolvedValue(true);
    const commit = vi.fn();
    const vm = { $store: { dispatch, commit } };
    const payload = {
      token: { id: 7, sceneId: 4, revision: 2 },
      changes: { resources: { bars: [], bubbles: [] } },
    };

    const result = await tableTokenMethods.updateToken.call(vm, payload);

    expect(result).toBe(payload.token);
    expect(dispatch).toHaveBeenCalledWith("realtime/changeToken", payload);
    expect(dispatch).not.toHaveBeenCalledWith("vtt/updateToken", payload);
    expect(dispatch).not.toHaveBeenCalledWith("campaignContext/refresh");
    expect(commit).toHaveBeenCalledWith("vtt/PATCH_TOKEN", {
      id: 7,
      sceneId: 4,
      resources: payload.changes.resources,
    });
  });

  it("reloads tokens after an optimistic revision conflict", async () => {
    const conflict = Object.assign(new Error("conflict"), { status: 409 });
    const dispatch = vi
      .fn()
      .mockResolvedValueOnce(false)
      .mockRejectedValueOnce(conflict)
      .mockResolvedValueOnce(null);
    const vm = { $store: { dispatch } };

    await tableTokenMethods.updateToken.call(vm, {
      token: { id: 7 },
      changes: { resources: {} },
    });

    expect(dispatch).toHaveBeenLastCalledWith("vtt/loadTokens", {
      silent: true,
    });
  });

  it("sends token changes through realtime instead of REST", async () => {
    const dispatch = vi.fn().mockResolvedValue(true);
    const vm = { $store: { dispatch } };
    const payload = {
      token: { id: 7, sceneId: 4, revision: 2 },
      changes: { facing: 135 },
    };

    await tableTokenMethods.updateToken.call(vm, payload);

    expect(dispatch).toHaveBeenCalledWith("realtime/changeToken", payload);
    expect(dispatch).not.toHaveBeenCalledWith("vtt/updateToken", payload);
  });

  it("keeps REST as the fallback for a movement update", async () => {
    const updated = { id: 7, revision: 3, x: 120, y: 240 };
    const dispatch = vi.fn().mockResolvedValue(updated);
    const vm = { $store: { dispatch } };
    const payload = {
      token: { id: 7, sceneId: 4, revision: 2 },
      changes: { x: 120, y: 240, waypoints: [] },
    };

    expect(await tableTokenMethods.updateToken.call(vm, payload)).toBe(updated);
    expect(dispatch).toHaveBeenCalledWith("vtt/updateToken", payload);
    expect(dispatch).not.toHaveBeenCalledWith("realtime/changeToken", payload);
  });

  it("falls back to REST when realtime angle delivery is unavailable", async () => {
    const updated = { id: 7, revision: 3, rotation: 90 };
    const dispatch = vi
      .fn()
      .mockResolvedValueOnce(false)
      .mockResolvedValueOnce(updated);
    const vm = { $store: { dispatch } };
    const payload = {
      token: { id: 7, sceneId: 4, revision: 2 },
      changes: { rotation: 90 },
    };

    expect(await tableTokenMethods.updateToken.call(vm, payload)).toBe(updated);
    expect(dispatch).toHaveBeenNthCalledWith(
      1,
      "realtime/changeToken",
      payload,
    );
    expect(dispatch).toHaveBeenNthCalledWith(2, "vtt/updateToken", payload);
  });

  it("shows and dismisses the depleted movement notice", () => {
    const commit = vi.fn();
    const vm = { $store: { commit } };

    tableTokenMethods.blockDepletedTokenMovement.call(vm);
    expect(commit).toHaveBeenCalledWith(
      "vtt/SHOW_NOTICE",
      expect.objectContaining({ code: "movement_points_depleted" }),
    );

    tableTokenMethods.dismissSceneNotice.call(vm);
    expect(commit).toHaveBeenLastCalledWith("vtt/CLEAR_ERROR");
  });

  it("sends the complete group through one realtime action", async () => {
    const dispatch = vi.fn().mockResolvedValue(true);
    const moves = [
      { token: { id: 1 }, x: 100, y: 0 },
      { token: { id: 2 }, x: 200, y: 0 },
    ];

    await tableTokenMethods.moveTokenGroup.call(
      { $store: { dispatch, commit: vi.fn() } },
      { moves },
    );

    expect(dispatch).toHaveBeenCalledWith("realtime/moveTokenGroup", {
      moves,
    });
  });

  it("names group members that block an all-or-nothing move", () => {
    const commit = vi.fn();

    tableTokenMethods.blockDepletedTokenMovement.call(
      { $store: { commit } },
      { group: true, tokens: [{ name: "Jürgen" }, { name: "Bruder" }] },
    );

    expect(commit).toHaveBeenCalledWith("vtt/SHOW_NOTICE", {
      code: "movement_group_blocked",
      status: 422,
      network: false,
      details: { names: "Jürgen, Bruder" },
    });
  });
});
