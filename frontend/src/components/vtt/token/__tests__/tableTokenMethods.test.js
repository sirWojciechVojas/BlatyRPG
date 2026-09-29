import { describe, expect, it, vi } from "vitest";

const clients = vi.hoisted(() => ({
  instantiate: vi.fn(),
  createCharacter: vi.fn(),
}));

vi.mock("@/lib/vtt/tokenTemplateApiClient", () => ({
  tokenTemplateApiClient: { instantiate: clients.instantiate },
}));

vi.mock("@/lib/character/characterApiClient", () => ({
  characterApiClient: { create: clients.createCharacter },
}));

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

    expect(result).toEqual({
      ...payload.token,
      resources: payload.changes.resources,
    });
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

    expect(dispatch).toHaveBeenCalledWith("vtt/loadTokens", {
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

  it("places a template at the current view center and selects the instance", async () => {
    const token = { id: 81, sceneId: 4, revision: 1 };
    clients.instantiate.mockResolvedValueOnce(token);
    const commit = vi.fn();
    const vm = {
      currentCampaignId: 7,
      selectedScene: { id: 4, width: 1200, height: 800 },
      sceneViewCenter: { x: 360, y: 270 },
      canCreateToken: true,
      tokenTemplateBusyId: null,
      $store: { commit },
    };

    const created = await tableTokenMethods.placeTokenTemplate.call(vm, {
      id: 19,
    });

    expect(clients.instantiate).toHaveBeenCalledWith(7, 4, 19, 360, 270);
    expect(commit).toHaveBeenCalledWith("vtt/UPSERT_TOKEN", token);
    expect(commit).toHaveBeenCalledWith("vtt/SELECT_TOKEN", 81);
    expect(created).toBe(token);
  });

  it("assigns and detaches characters through the token update channel", async () => {
    const token = { id: 81, sceneId: 4, revision: 1 };
    const updateToken = vi
      .fn()
      .mockResolvedValue({ ...token, characterId: 51 });
    const dispatch = vi.fn().mockResolvedValue({});
    const vm = {
      tokenCharacterAssignmentToken: token,
      tokenCharacterAssignmentBusy: false,
      tokenCharacterAssignmentError: "",
      tokenCharacterAssignmentCreatedId: null,
      updateToken,
      $store: { dispatch },
      $t: (key) => key,
    };

    await tableTokenMethods.assignTokenCharacter.call(vm, 51);
    expect(updateToken).toHaveBeenCalledWith({
      token,
      changes: { characterId: 51 },
    });
    expect(dispatch).toHaveBeenCalledWith("vtt/loadTokenSync");

    vm.tokenCharacterAssignmentToken = token;
    updateToken.mockResolvedValueOnce({ ...token, characterId: null });
    await tableTokenMethods.assignTokenCharacter.call(vm, null);
    expect(updateToken).toHaveBeenLastCalledWith({
      token,
      changes: { characterId: null },
    });
  });

  it("keeps a newly created character available when token linking fails", async () => {
    clients.createCharacter.mockResolvedValueOnce({ id: 51, name: "Ogre" });
    const token = {
      id: 81,
      sceneId: 4,
      revision: 1,
      name: "Ogre",
      imageUrl: "/api/campaigns/7/token-template-assets/4/file",
    };
    const updateToken = vi.fn().mockResolvedValue(null);
    const dispatch = vi.fn().mockResolvedValue({});
    const vm = {
      currentCampaignId: 7,
      campaign: { systemId: 2, universeId: 3 },
      tokenCharacterAssignmentToken: token,
      tokenCharacterAssignmentBusy: false,
      tokenCharacterAssignmentError: "",
      tokenCharacterAssignmentCreatedId: null,
      updateToken,
      $store: { dispatch },
      $t: (key) => key,
    };
    vm.assignTokenCharacter = (id) =>
      tableTokenMethods.assignTokenCharacter.call(vm, id);

    expect(
      await tableTokenMethods.createAndAssignTokenCharacter.call(vm, "Ogre"),
    ).toBeNull();
    expect(clients.createCharacter).toHaveBeenCalledWith(7, {
      systemId: 2,
      universeId: 3,
      name: "Ogre",
      data: {},
      avatarUrl: "/api/campaigns/7/token-template-assets/4/file",
    });
    expect(dispatch).toHaveBeenCalledWith("campaignContext/refresh");
    expect(vm.tokenCharacterAssignmentCreatedId).toBe(51);
    expect(vm.tokenCharacterAssignmentToken).toBe(token);
    expect(vm.tokenCharacterAssignmentError).toBe(
      "vtt.token.assignment.linkError",
    );
  });
});
