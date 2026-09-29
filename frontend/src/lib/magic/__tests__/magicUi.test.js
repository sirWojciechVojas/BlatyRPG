import { describe, expect, it } from "vitest";
import { matchingSpellIngredients, spellMatchesFilters } from "../magicUi";

describe("magic spellbook UI rules", () => {
  it("only offers the matching inventory ingredient", () => {
    const spell = { ingredient: { name: "Bryłka siarki", bonus: 2 } };
    expect(
      matchingSpellIngredients(spell, [
        { id: 1, name: "Bryłka siarki" },
        { id: 2, name: "Pochodnia" },
      ]).map((item) => item.id),
    ).toEqual([1]);
  });

  it("filters known spells by search, target and favorites", () => {
    const spell = {
      name: "Ognista kula",
      effect: "Magiczne pociski",
      targetType: "missiles",
      favorite: true,
    };
    expect(
      spellMatchesFilters(spell, {
        search: "ognista",
        kind: "missiles",
        favorites: true,
      }),
    ).toBe(true);
    expect(spellMatchesFilters(spell, { kind: "self" })).toBe(false);
  });
});
