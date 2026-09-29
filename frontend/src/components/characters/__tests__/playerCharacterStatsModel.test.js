import { describe, expect, it } from "vitest";
import {
  createPlayerCharacterStatsModel,
  EMPTY_VALUE,
} from "../playerCharacterStatsModel";

describe("playerCharacterStatsModel", () => {
  it("maps the current character data into the original WFRP characterStats layout", () => {
    const model = createPlayerCharacterStatsModel({
      id: 9,
      name: "Alaric",
      brass: 397,
      primaryCurrencyCode: "wfrp_empire",
      data: {
        details: {
          true_name: "Alaric von Bögenhafen",
          name: "Alaric",
          race: "Człowiek",
          profession_id: 12,
          history: "Historia bohatera",
        },
        attributes: {
          start: { ww: 31, zyw: 10 },
          advances: { ww: 15, zyw: 2 },
          actual: { ww: 46, zyw: 12, s: 4, wt: 3, po: 2, pp: 1 },
          skills: ["Plotkowanie"],
          talents: [{ name: "Szczęście", description: "Raz dziennie" }],
        },
      },
    });

    expect(model.mainTraits[0]).toMatchObject({
      key: "ww",
      base: "31",
      advance: "15",
      total: "46",
      rank: 3,
    });
    expect(model.secondaryTraits[0]).toMatchObject({
      key: "zyw",
      base: "10",
      advance: "2",
      total: "12",
    });
    expect(model.points).toMatchObject({
      strengthBonus: "4",
      toughnessBonus: "3",
      fatePoints: "2",
      fortunePoints: "1",
    });
    expect(model.skills[0]).toMatchObject({ name: "Plotkowanie", status: 1 });
    expect(model.talents[0]).toMatchObject({ name: "Szczęście", status: 1 });
    expect(model.history).toEqual(["Historia bohatera"]);
    expect(model.wallet).toEqual({
      system: "empire",
      crown: 1,
      shilling: 13,
      penny: 1,
    });
  });

  it("supports legacy characterStats fields without inventing missing values", () => {
    const model = createPlayerCharacterStatsModel({
      name: "Legacy hero",
      data: {
        WEAPONSKILL: { in: "28", adv: "10", cur: "38" },
        details: {},
      },
    });

    expect(model.mainTraits[0]).toMatchObject({
      base: "28",
      advance: "10",
      total: "38",
      rank: 2,
    });
    expect(model.mainTraits[1].total).toBe(EMPTY_VALUE);
    expect(model.details.race).toBe(EMPTY_VALUE);
    expect(model.history).toEqual([]);
  });

  it("uses the Bretonnian wallet conversion when selected by the API", () => {
    const model = createPlayerCharacterStatsModel({
      name: "Knight",
      brass: 120,
      primaryCurrencyCode: "wfrp_bretonnia",
    });

    expect(model.wallet).toEqual({
      system: "bretonnia",
      crown: 1,
      shilling: 60,
      penny: null,
    });
  });
});
