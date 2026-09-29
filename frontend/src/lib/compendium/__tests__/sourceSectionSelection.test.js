import { describe, expect, it } from "vitest";
import { selectSourceSections } from "../sourceSectionSelection";

const sourceHtml =
  '<p>Introduction.</p><h2 id="habitat">Habitat</h2><p>Forest lore.</p>' +
  '<h2 id="combat">Combat</h2><p>Combat lore.</p>';

describe("sourceSectionSelection", () => {
  it("keeps only the source sections selected for heroes", () => {
    const selected = selectSourceSections(sourceHtml, ["lead", "habitat"]);

    expect(selected).toContain("Introduction.");
    expect(selected).toContain("Forest lore.");
    expect(selected).not.toContain("Combat lore.");
  });

  it("returns no player content when the GM selected no sections", () => {
    expect(selectSourceSections(sourceHtml, [])).toBe("");
  });
});
