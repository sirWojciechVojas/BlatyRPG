import { describe, expect, it } from "vitest";
import {
  campaignSettingsDraft,
  systemsFromGames,
  worldsForSystem,
} from "@/lib/campaign/campaignSettingsDraft";

describe("campaign settings draft", () => {
  const games = [
    { systemId: 2, systemName: "Zeta", universeId: 8, universeName: "Beta" },
    { systemId: 1, systemName: "Alpha", universeId: 4, universeName: "Gamma" },
    { systemId: 1, systemName: "Alpha", universeId: 3, universeName: "Delta" },
  ];

  it("merges persisted settings onto safe table defaults", () => {
    const draft = campaignSettingsDraft({
      systemId: 1,
      universeId: 3,
      settings: { allowPlayerDrawing: true, defaultGridSize: 64 },
    });

    expect(draft.allowPlayerDrawing).toBeUndefined();
    expect(draft.settings.allowPlayerDrawing).toBe(true);
    expect(draft.settings.defaultGridSize).toBe(64);
    expect(draft.settings.tableVisibility).toBe("invite_only");
  });

  it("derives unique systems and worlds from the shared catalog", () => {
    expect(systemsFromGames(games).map((system) => system.id)).toEqual([1, 2]);
    expect(worldsForSystem(games, 1).map((world) => world.id)).toEqual([3, 4]);
  });
});
