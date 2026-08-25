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
    expect(TOKEN_SIZE_PRESETS.find(({ key }) => key === "standard")).toEqual({
      key: "standard",
      cells: 1,
    });
  });
  it("edits token size in grid units and preserves permission scopes", () => {
    const draft = createTokenSettingsDraft(
      {
        name: "Guard",
        width: 200,
        height: 100,
        visibleTo: { mode: "users", userIds: [8] },
      },
      100,
    );
    draft.widthCells = 1.5;
    const payload = tokenSettingsPayload(draft, 100, true);

    expect(payload.width).toBe(150);
    expect(payload.height).toBe(100);
    expect(payload.visibleTo).toEqual({ mode: "users", userIds: [8] });
  });

  it("does not submit ownership fields for a non-manager editor", () => {
    const draft = createTokenSettingsDraft({ name: "Guard" }, 80);
    expect(tokenSettingsPayload(draft, 80, false)).not.toHaveProperty(
      "controlledBy",
    );
  });

  it("keeps the compact panel inside the viewport", () => {
    expect(
      tokenSettingsPosition(
        { left: 950, right: 1000, top: 760 },
        { width: 1024, height: 800 },
      ),
    ).toEqual({ left: 518, top: 90 });
  });
});
