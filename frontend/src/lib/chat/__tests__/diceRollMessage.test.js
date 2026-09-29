import { describe, expect, it } from "vitest";
import {
  createDiceRollMessage,
  DICE_ROLL_PREFIX,
  groupDiceRollResults,
  parseDiceRollMessage,
} from "../diceRollMessage";

describe("dice roll chat messages", () => {
  it("round-trips a percentile roll without a four-byte emoji", () => {
    const body = createDiceRollMessage({
      formula: "1d100 + 1d10",
      dice: [
        { type: "d100", display: "70" },
        { type: "d10", display: "6" },
      ],
      total: 76,
    });

    expect(body.startsWith(DICE_ROLL_PREFIX)).toBe(true);
    expect(body).not.toContain("🎲");
    expect(parseDiceRollMessage(body)).toEqual({
      formula: "1d100 + 1d10",
      dice: [
        { type: "d100", value: "70" },
        { type: "d10", value: "6" },
      ],
      total: "76",
    });
  });

  it("rejects ordinary chat and malformed roll payloads", () => {
    expect(parseDiceRollMessage("Hello")).toBeNull();
    expect(parseDiceRollMessage(`${DICE_ROLL_PREFIX}{`)).toBeNull();
  });

  it("repairs totals in percentile cards created with shifted face labels", () => {
    const body = createDiceRollMessage({
      formula: "1d100 + 1d10",
      dice: [
        { type: "d100", display: "30" },
        { type: "d10", display: "5" },
      ],
      total: 24,
    });

    expect(parseDiceRollMessage(body)?.total).toBe("35");
  });

  it("groups dice into card sections with subtotals and maximum results", () => {
    expect(
      groupDiceRollResults([
        { type: "d10", value: "9" },
        { type: "d10", value: "4" },
        { type: "d6", value: "6" },
      ]),
    ).toEqual([
      {
        type: "d10",
        notation: "2d10",
        total: "13",
        dice: [
          { type: "d10", value: "9", isMaximum: true },
          { type: "d10", value: "4", isMaximum: false },
        ],
      },
      {
        type: "d6",
        notation: "1d6",
        total: "6",
        dice: [{ type: "d6", value: "6", isMaximum: true }],
      },
    ]);
  });
});
