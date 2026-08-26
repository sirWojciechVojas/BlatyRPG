import { describe, expect, it } from "vitest";
import {
  exportTokenSettings,
  importTokenSettings,
} from "@/lib/vtt/tokenSettingsTransfer";

const draft = {
  name: "Goblin",
  imageUrl: "goblin.webp",
  widthCells: 2,
  heightCells: 2,
  rotation: 90,
  facing: 45,
  movementRange: 8,
  movementSpent: 5,
  movementResetMode: "round",
  resourceBarPosition: "above",
  resources: { bars: [], bubbles: [] },
  visibleTo: { mode: "everyone", userIds: [] },
};

describe("tokenSettingsTransfer", () => {
  it("moves configuration without identity or spent movement", () => {
    const target = {
      ...draft,
      name: "Knight",
      imageUrl: "knight.webp",
      movementSpent: 2,
      widthCells: 1,
    };
    const imported = importTokenSettings(exportTokenSettings(draft), target);

    expect(imported).toMatchObject({
      name: "Knight",
      imageUrl: "knight.webp",
      movementSpent: 2,
      widthCells: 2,
      movementRange: 8,
      movementResetMode: "round",
    });
  });

  it("rejects arbitrary JSON", () => {
    expect(() => importTokenSettings('{"widthCells": 3}', draft)).toThrow(
      "token_settings_format_unsupported",
    );
  });
});
