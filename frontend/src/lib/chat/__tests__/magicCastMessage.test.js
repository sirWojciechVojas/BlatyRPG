import { describe, expect, it } from "vitest";
import {
  createMagicCastMessage,
  MAGIC_CAST_PREFIX,
  parseMagicCastMessage,
} from "../magicCastMessage";

const resolvedCast = () => ({
  id: 17,
  spell: {
    name: "Ognista kula",
    tradition: "Tradycja Ognia",
    magicType: "tajemna",
  },
  targets: [{ label: "Kultysta" }],
  result: {
    spell: { id: 4, name: "Ognista kula" },
    powerDice: [6, 6, 4],
    chaosDice: [6],
    modifier: 3,
    powerTotal: 19,
    castingNumber: 12,
    spellSucceeded: true,
    automaticFailure: false,
    willpowerTestRequired: false,
    manifestations: [{ face: 6, matchingDice: 3, severity: "major" }],
    channel: { attempted: true, succeeded: true, roll: 28, bonus: 1 },
    ingredient: { name: "Bryłka siarki", bonus: 2, consumed: true },
    targetDefense: { message: "Cel może wykonać test Zręczności." },
    effect: { message: "Dwa pociski o Sile 3." },
  },
});

describe("spell cast chat messages", () => {
  it("round-trips the exact authoritative dice and resolution", () => {
    const body = createMagicCastMessage({
      characterName: "Elsa",
      cast: resolvedCast(),
    });

    expect(body.startsWith(MAGIC_CAST_PREFIX)).toBe(true);
    expect(body.length).toBeLessThanOrEqual(2000);
    expect(parseMagicCastMessage(body)).toMatchObject({
      castId: 17,
      character: "Elsa",
      spell: "Ognista kula",
      castingNumber: 12,
      powerDice: [6, 6, 4],
      chaosDice: [6],
      modifier: 3,
      powerTotal: 19,
      succeeded: true,
      manifestations: [{ face: 6, matchingDice: 3, severity: "major" }],
      ingredient: { name: "Bryłka siarki", bonus: 2, consumed: true },
      targets: ["Kultysta"],
      defense: "Cel może wykonać test Zręczności.",
      effect: "Dwa pociski o Sile 3.",
    });
  });

  it("rejects ordinary, malformed and incomplete messages", () => {
    expect(parseMagicCastMessage("Zwykła wiadomość")).toBeNull();
    expect(parseMagicCastMessage(`${MAGIC_CAST_PREFIX}{`)).toBeNull();
    expect(
      parseMagicCastMessage(`${MAGIC_CAST_PREFIX}{"spell":"Test"}`),
    ).toBeNull();
    expect(createMagicCastMessage({ cast: {} })).toBe("");
  });

  it("keeps a maximum-size description inside the chat limit", () => {
    const cast = resolvedCast();
    cast.result.effect.message = "A".repeat(4000);
    cast.result.targetDefense.message = "B".repeat(4000);
    cast.targets = Array.from({ length: 12 }, (_value, index) => ({
      label: `Cel ${index} ${"C".repeat(160)}`,
    }));

    const body = createMagicCastMessage({
      characterName: "D".repeat(400),
      cast,
    });

    expect(body.length).toBeLessThanOrEqual(2000);
    expect(parseMagicCastMessage(body)?.effect).toHaveLength(420);
  });
});
