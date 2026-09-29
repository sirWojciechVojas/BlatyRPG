import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/bestiary/BestiaryContent.vue",
);
const stylesPath = resolve(
  process.cwd(),
  "src/components/vtt/bestiary/bestiary.css",
);

describe("BestiaryContent", () => {
  it("keeps full-knowledge and remaining creatures in separate groups", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const template = descriptor.template.content;

    expect(template).toContain("filteredEncountered");
    expect(template).toContain("filteredRemaining");
    expect(template).toContain("bestiary-entry-link--locked");
    expect(template).toContain("item.level === 'unknown'");
  });

  it("only sends unlocked entries to the compendium reader", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });

    expect(descriptor.template.content).toContain("lockedSelection");
    expect(descriptor.template.content).toContain("<CompendiumEntryView");
    expect(descriptor.script.content).toContain(
      "await bestiaryApiClient.entry(",
    );
    expect(descriptor.script.content).toContain("selectLocked(item)");
    expect(descriptor.script.content).toContain("selectSummary(item)");
    expect(descriptor.template.content).toContain("summarySelection.excerpt");
  });

  it("supports parchment sidebar and wood character-HUD variants", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });

    expect(descriptor.template.content).toContain(
      "character-bestiary--${resolvedVariant}",
    );
    expect(descriptor.script.content).toContain(
      '["parchment", "wood"].includes(value)',
    );
  });

  it("keeps the wood HUD variant compact and information dense", () => {
    const source = readFileSync(componentPath, "utf8");
    const styles = readFileSync(stylesPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });

    expect(descriptor.template.content).toContain(
      "character-bestiary__metrics",
    );
    expect(descriptor.template.content).toContain("counts.summary");
    expect(descriptor.template.content).toContain("counts.unknown");
    expect(styles).toContain(
      "grid-template-columns: clamp(220px, 20vw, 280px) minmax(0, 1fr)",
    );
    expect(styles).toContain(
      ".character-bestiary--wood .compendium-entry__fields",
    );
    expect(styles).toContain("border: 5px solid transparent");
  });
});
