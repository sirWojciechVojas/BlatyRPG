import { describe, expect, it } from "vitest";
import {
  createItemMechanic,
  normalizeItemMechanics,
  resolveItemMechanics,
} from "@/lib/trade/itemMechanics";

describe("item mechanics", () => {
  it("normalizes mechanics with executable checks, costs, and effects", () => {
    const result = normalizeItemMechanics([
      {
        code: "drink_elixir",
        trigger: "consume",
        handler: "dice_test",
        check: { enabled: true, formula: "1d100", targetKey: "T" },
        cost: { quantity: 1 },
        effects: [
          {
            when: "success",
            type: "heal",
            target: "self",
            value: "1d10",
          },
        ],
      },
    ]);

    expect(result[0]).toMatchObject({
      code: "DRINK_ELIXIR",
      trigger: "CONSUME",
      handler: "DICE_TEST",
      check: { enabled: true, formula: "1d100", targetKey: "T" },
      cost: { quantity: 1 },
      effects: [
        {
          when: "SUCCESS",
          type: "HEAL",
          target: "SELF",
          value: "1d10",
        },
      ],
    });
  });

  it("merges class, item type, and template mechanics by code", () => {
    const dictionaries = {
      classes: [
        {
          code: "WEAPON",
          mechanics: [
            createItemMechanic("ATTACK", { labelPl: "Atak klasowy" }),
          ],
        },
      ],
      genres: [
        {
          code: "RANGED",
          mechanics: [
            createItemMechanic("ATTACK", { labelPl: "Atak dystansowy" }),
            createItemMechanic("AIM", { labelPl: "Celowanie" }),
          ],
        },
      ],
    };

    const inherited = resolveItemMechanics({
      dictionaries,
      itemClass: "WEAPON",
      itemGenre: "RANGED",
      mode: "INHERIT",
    });
    expect(inherited.map(({ code, source }) => [code, source])).toEqual([
      ["ATTACK", "GENRE"],
      ["AIM", "GENRE"],
    ]);

    const extended = resolveItemMechanics({
      dictionaries,
      itemClass: "WEAPON",
      itemGenre: "RANGED",
      templateMechanics: [
        createItemMechanic("ATTACK", { labelPl: "Atak kuszą" }),
      ],
      mode: "EXTEND",
    });
    expect(extended.map(({ code, source }) => [code, source])).toEqual([
      ["ATTACK", "TEMPLATE"],
      ["AIM", "GENRE"],
    ]);
  });
});
