import { describe, expect, it } from "vitest";
import {
  TOKEN_SIZE_PRESETS,
  createTokenSettingsDraft,
  tokenSettingsPayload,
  tokenSettingsPosition,
} from "@/lib/vtt/tokenSettingsDraft";

describe("tokenSettingsDraft", () => {
  it("provides five sizes with one grid cell as the standard", () => {
    expect(TOKEN_SIZE_PRESETS).toHaveLength(5);
    expect(TOKEN_SIZE_PRESETS.map(({ cells }) => cells)).toEqual([
      0.25, 0.5, 1, 2, 3,
    ]);
    expect(TOKEN_SIZE_PRESETS.find(({ key }) => key === "standard")).toEqual({
      key: "standard",
      cells: 1,
    });
  });

  it("allows a quarter-cell token on a small grid", () => {
    const draft = createTokenSettingsDraft({ name: "Sprite" }, 4);
    draft.widthCells = 0.25;
    draft.heightCells = 0.25;

    expect(tokenSettingsPayload(draft, 4, false)).toMatchObject({
      width: 1,
      height: 1,
    });
  });
  it("edits token size in grid units and preserves permission scopes", () => {
    const draft = createTokenSettingsDraft(
      {
        name: "Guard",
        width: 200,
        height: 100,
        visibleTo: { mode: "users", userIds: [8] },
        rotationHandleEnabled: true,
        rotationFollowsFacing: true,
        movementRange: 8,
        movementSpent: 2,
        movementResetMode: "round",
        showInfoUnselected: true,
        resourceBarPosition: "top-overlap",
        resources: { bubbles: [{ enabled: true, value: 3 }] },
      },
      100,
    );
    draft.widthCells = 1.5;
    const payload = tokenSettingsPayload(draft, 100, true);

    expect(payload.width).toBe(150);
    expect(payload.height).toBe(100);
    expect(payload.visibleTo).toEqual({ mode: "users", userIds: [8] });
    expect(payload.rotationHandleEnabled).toBe(true);
    expect(payload.facingHandleEnabled).toBe(false);
    expect(payload.rotationFollowsFacing).toBe(true);
    expect(payload.movementRange).toBe(8);
    expect(payload.movementSpent).toBe(2);
    expect(payload.movementResetMode).toBe("round");
    expect(payload.showInfoUnselected).toBe(true);
    expect(payload.resourceBarPosition).toBe("top-overlap");
    expect(payload.resources.bubbles[0]).toMatchObject({
      enabled: true,
      value: 3,
    });
  });

  it("does not submit ownership fields for a non-manager editor", () => {
    const draft = createTokenSettingsDraft({ name: "Guard" }, 80);
    expect(tokenSettingsPayload(draft, 80, false)).not.toHaveProperty(
      "controlledBy",
    );
    expect(tokenSettingsPayload(draft, 80, false)).not.toHaveProperty(
      "rotationHandleEnabled",
    );
    expect(tokenSettingsPayload(draft, 80, false)).not.toHaveProperty(
      "movementRange",
    );
    expect(tokenSettingsPayload(draft, 80, false)).not.toHaveProperty(
      "showInfoUnselected",
    );
    expect(tokenSettingsPayload(draft, 80, false)).toHaveProperty(
      "resourceBarPosition",
      "below",
    );
  });

  it("derives available movement from the configured resource bar", () => {
    const draft = createTokenSettingsDraft(
      { name: "Runner", movementRange: 8, movementSpent: 2 },
      100,
    );
    draft.resources.bubbles[0].linkedBarIndex = 1;
    draft.resources.bubbles[0].value = 3;
    draft.resources.bars[1].value = 3;

    const payload = tokenSettingsPayload(draft, 100, true);

    expect(payload.movementRange).toBe(8);
    expect(payload.movementSpent).toBe(5);
    expect(payload.resources.bubbles[0].linkedBarIndex).toBe(1);
  });

  it("normalizes and submits the token vision overlay appearance", () => {
    const draft = createTokenSettingsDraft(
      {
        name: "Scout",
        vision: {
          enabled: true,
          showShape: true,
          shapeBorderColor: "#AABBCC",
          shapeBorderOpacity: 0.6,
          shapeFillColor: "invalid",
          shapeFillOpacity: 0.2,
        },
      },
      100,
    );
    expect(tokenSettingsPayload(draft, 100, true).vision).toMatchObject({
      showShape: true,
      shapeBorderColor: "#aabbcc",
      shapeBorderOpacity: 0.6,
      shapeFillColor: "#65d7ff",
      shapeFillOpacity: 0.2,
    });
  });

  it("uses facing as the single direction for directional vision", () => {
    const draft = createTokenSettingsDraft(
      {
        name: "Scout",
        rotation: 15,
        facing: 135,
        vision: { enabled: true, angle: 90, direction: 20 },
      },
      100,
    );
    expect(draft.vision.direction).toBe(135);

    draft.facing = 225;
    draft.vision.direction = 20;
    expect(tokenSettingsPayload(draft, 100, true)).toMatchObject({
      facing: 225,
      vision: { direction: 225 },
    });
  });

  it("keeps the compact panel inside the viewport", () => {
    expect(
      tokenSettingsPosition(
        { left: 950, right: 1000, top: 760 },
        { width: 1024, height: 800 },
      ),
    ).toEqual({ left: 78, top: 70 });
  });
});
