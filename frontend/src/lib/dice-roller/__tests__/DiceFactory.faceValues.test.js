import { describe, expect, it } from "vitest";
import { resolveDiceFaceResult } from "../includes/DiceFactory";

const d10Labels = (values) => ["", ...values.map(String)];

describe("DiceFactory face values", () => {
  it("keeps d10 face labels aligned with their numeric values", () => {
    const die = {
      values: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
      labels: d10Labels([0, 1, 2, 3, 4, 5, 6, 7, 8, 9]),
    };

    expect(resolveDiceFaceResult(die, "d10", 1)).toMatchObject({
      value: 0,
      label: "0",
    });
    expect(resolveDiceFaceResult(die, "d10", 6)).toMatchObject({
      value: 5,
      label: "5",
    });
  });

  it("keeps percentile tens labels aligned with their numeric values", () => {
    const values = [0, 10, 20, 30, 40, 50, 60, 70, 80, 90];
    const die = {
      values,
      labels: d10Labels(values.map((value) => String(value).padStart(2, "0"))),
    };

    expect(resolveDiceFaceResult(die, "d10", 1)).toMatchObject({
      value: 0,
      label: "00",
    });
    expect(resolveDiceFaceResult(die, "d10", 4)).toMatchObject({
      value: 30,
      label: "30",
    });
  });

  it("preserves the two-slot material padding used by regular dice", () => {
    const die = {
      values: [1, 2, 3, 4, 5, 6],
      labels: ["", "", "1", "2", "3", "4", "5", "6"],
    };

    expect(resolveDiceFaceResult(die, "d6", 2)).toMatchObject({
      value: 1,
      label: "1",
    });
    expect(resolveDiceFaceResult(die, "d6", 7)).toMatchObject({
      value: 6,
      label: "6",
    });
  });
});
