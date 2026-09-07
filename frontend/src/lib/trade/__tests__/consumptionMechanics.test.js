import { describe, expect, it } from "vitest";
import {
  consumptionProfileMechanics,
  syncConsumptionProfileMechanics,
} from "@/lib/trade/consumptionMechanics";

describe("consumptionProfileMechanics", () => {
  it("exposes a selected catalogue profile as a read-only consume mechanic", () => {
    const mechanics = consumptionProfileMechanics({
      id: "FOOD-596",
      name: "Sercojad w czerwonym winie",
      effectId: "E31",
      effect: "Test Odp -10%.",
      effectWindow: "Natychmiast",
      risk: "Śmiertelne",
      negativeTest: "Odp -10%",
      failureConsequence: "Porażka ma poważne skutki.",
      consumeTime: "1 minuta",
      usableInCombat: true,
      description: "Opis katalogowy.",
      satietyHours: 0,
      hydrationHours: 1,
      actionLabel: "Wypij",
    });

    expect(mechanics).toHaveLength(1);
    expect(mechanics[0]).toMatchObject({
      code: "CATALOG_CONSUME_FOOD_596",
      source: "CONSUMPTION",
      trigger: "CONSUME",
      handler: "CONSUME",
      cost: { quantity: 1 },
      parameters: { consumptionProfileId: "FOOD-596", generated: true },
    });
    expect(mechanics[0].effects.map((effect) => effect.description)).toEqual(
      expect.arrayContaining([
        "Nawodnienie: +1 h.",
        "Test Odp -10%.",
        "Ryzyko: Śmiertelne. Porażka ma poważne skutki.",
      ]),
    );
  });

  it("does not create a mechanic without a selected profile", () => {
    expect(consumptionProfileMechanics(null)).toEqual([]);
  });

  it("replaces only the prior generated mechanic in a template", () => {
    const manual = { code: "MANUAL", handlerKey: "custom" };
    const staleGenerated = {
      code: "CATALOG_CONSUME_FOOD_001",
      handlerKey: "consumption.catalog",
      parameters: { generated: true },
    };

    const result = syncConsumptionProfileMechanics([manual, staleGenerated], {
      id: "FOOD-002",
      name: "Nowy profil",
      effect: "Efekt.",
      consumeTime: "1 minuta",
    });

    expect(result[0]).toBe(manual);
    expect(result).toHaveLength(2);
    expect(result[1].code).toBe("CATALOG_CONSUME_FOOD_002");
  });
});
