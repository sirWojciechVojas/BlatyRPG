import { describe, expect, it } from "vitest";
import {
  displayProfessionName,
  normalizeProfessionText,
  presentProfessionText,
  professionSearchText,
} from "../professionPresentation";

describe("profession presentation", () => {
  it("searches without case, diacritics, or a special ł distinction", () => {
    expect(normalizeProfessionText("ŁOWCA nagród")).toBe("lowca nagrod");
    expect(
      professionSearchText({
        name: "Łowca nagród",
        description: "Imperium",
        details: null,
      }),
    ).toContain("lowca nagrod");
  });

  it("turns escaped database line endings into readable lines", () => {
    expect(presentProfessionText("Pierwsza\\r\\nDruga\r\nTrzecia")).toBe(
      "Pierwsza\nDruga\nTrzecia",
    );
  });

  it("changes only the display capitalization", () => {
    expect(displayProfessionName("szuler")).toBe("Szuler");
  });
});
