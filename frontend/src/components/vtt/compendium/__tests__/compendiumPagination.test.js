import { describe, expect, it } from "vitest";
import {
  nextCompendiumPage,
  shouldLoadNextCompendiumPage,
} from "../compendiumPagination";

describe("compendium automatic pagination", () => {
  it("requests another page when the visible list is near its end", () => {
    expect(
      shouldLoadNextCompendiumPage({
        clientHeight: 300,
        scrollHeight: 900,
        scrollTop: 450,
      }),
    ).toBe(true);

    expect(
      shouldLoadNextCompendiumPage({
        clientHeight: 300,
        scrollHeight: 900,
        scrollTop: 100,
      }),
    ).toBe(false);
  });

  it("does not paginate a hidden list", () => {
    expect(
      shouldLoadNextCompendiumPage({
        clientHeight: 0,
        scrollHeight: 900,
        scrollTop: 900,
      }),
    ).toBe(false);
  });

  it("derives the next page without mutating the current one", () => {
    expect(nextCompendiumPage(2, true)).toBe(3);
    expect(nextCompendiumPage(8, false)).toBe(1);
  });
});
