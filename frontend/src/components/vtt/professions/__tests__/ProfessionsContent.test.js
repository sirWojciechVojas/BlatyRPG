import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const sourcePath = resolve(
  process.cwd(),
  "src/components/vtt/professions/ProfessionsContent.vue",
);
const listPath = resolve(
  process.cwd(),
  "src/components/vtt/professions/ProfessionList.vue",
);
const detailPath = resolve(
  process.cwd(),
  "src/components/vtt/professions/ProfessionDetails.vue",
);
const characterPath = resolve(
  process.cwd(),
  "src/components/vtt/professions/CharacterProfessions.vue",
);
const autocompletePath = resolve(
  process.cwd(),
  "src/components/vtt/professions/ProfessionAutocomplete.vue",
);
const stylesPath = resolve(
  process.cwd(),
  "src/components/vtt/professions/professions.css",
);
const windowStylesPath = resolve(
  process.cwd(),
  "src/components/vtt/scene/styles/table-windows.css",
);

describe("ProfessionsContent", () => {
  it("uses one store-backed catalog in both the drawer and window", () => {
    const source = readFileSync(sourcePath, "utf8");
    const { descriptor } = parse(source, { filename: sourcePath });

    expect(descriptor.template.content).toContain("compact");
    expect(descriptor.template.content).toContain("<ProfessionList");
    expect(descriptor.template.content).toContain("<ProfessionDetails");
    expect(descriptor.script.content).toContain(
      "this.$store.state.professions",
    );
    expect(descriptor.script.content).toContain("professions/loadCatalog");
  });

  it("keeps filtering client-side over the complete result and preserves ids", () => {
    const source = readFileSync(listPath, "utf8");
    const { descriptor } = parse(source, { filename: listPath });

    expect(descriptor.script.content).toContain("professionSearchText");
    expect(descriptor.script.content).toContain("professionCollator.compare");
    expect(descriptor.template.content).toContain("profession.id");
    expect(descriptor.template.content).toContain("duplicateCount");
    expect(descriptor.template.content).toContain(
      '@dblclick="open(profession.id)"',
    );
  });

  it("marks unverified mechanics and never exposes an advancement action", () => {
    const source = readFileSync(detailPath, "utf8");
    const { descriptor } = parse(source, { filename: detailPath });

    expect(descriptor.template.content).toContain("developmentDisclaimer");
    expect(descriptor.script.content).toContain("developmentUnverified");
    expect(descriptor.template.content).not.toContain("purchase");
    expect(descriptor.template.content).not.toContain("spend");
  });

  it("combines description and development in a single Info tab", () => {
    const source = readFileSync(detailPath, "utf8");
    const { descriptor } = parse(source, { filename: detailPath });

    expect(descriptor.script.content).toContain(
      '{ id: "info", labelKey: "vtt.table.professions.info" }',
    );
    expect(descriptor.script.content).not.toContain('{ id: "description"');
    expect(descriptor.script.content).not.toContain('{ id: "development"');
    expect(
      descriptor.template.content.match(/activeSection === 'info'/g),
    ).toHaveLength(2);
  });

  it("decodes requirement labels and presents the two-row advance scheme", () => {
    const source = readFileSync(detailPath, "utf8");
    const styles = readFileSync(stylesPath, "utf8");
    const { descriptor } = parse(source, { filename: detailPath });

    expect(descriptor.script.content).toContain("item.display || item.raw");
    expect(descriptor.template.content).toContain(
      "profession-development__scheme",
    );
    expect(descriptor.script.content).toContain('group("primary"');
    expect(descriptor.script.content).toContain('group("secondary"');
    expect(descriptor.script.content).toContain('percent ? "%" : ""');
    expect(descriptor.template.content).toContain("<caption>");
    expect(descriptor.template.content).toContain(
      "displayName(profession.name)",
    );
    expect(styles).toContain("aspect-ratio: 535 / 240");
    expect(styles).toContain("background: #000");
    expect(styles).toContain("caption-side: top");
  });

  it("shows readable profession paths and opens every resolved catalog item", () => {
    const source = readFileSync(detailPath, "utf8");
    const { descriptor } = parse(source, { filename: detailPath });

    expect(descriptor.template.content).toContain("development.paths.entries");
    expect(descriptor.template.content).toContain("development.paths.exits");
    expect(descriptor.template.content).toContain(
      'v-if="item.linked !== false && item.professionId"',
    );
    expect(descriptor.template.content).toContain(
      '@click="selectRelated(item.professionId)"',
    );
    expect(descriptor.template.content).toContain(
      "profession-development__path-unlinked",
    );
  });

  it("lets the GM set a catalog profession as the character's current one", () => {
    const source = readFileSync(detailPath, "utf8");
    const { descriptor } = parse(source, { filename: detailPath });

    expect(descriptor.template.content).toContain("canSetCurrentProfession");
    expect(descriptor.template.content).toContain(
      '@click="setAsCurrentProfession"',
    );
    expect(descriptor.script.content).toContain(
      '"professions/changeCharacterProfession"',
    );
    expect(descriptor.script.content).toContain(
      "professionId: this.profession.id",
    );
  });

  it("lets only the GM change, reorder, and remove character professions", () => {
    const source = readFileSync(characterPath, "utf8");
    const { descriptor } = parse(source, { filename: characterPath });

    expect(descriptor.template.content).toContain('v-if="canManageProfession"');
    expect(descriptor.template.content).toContain(
      '@submit.prevent="saveProfession"',
    );
    expect(descriptor.template.content).toContain("zeroXp");
    expect(descriptor.template.content).toContain("<ProfessionAutocomplete");
    expect(descriptor.template.content).not.toContain("<select");
    expect(descriptor.template.content).toContain(
      '@click="moveHistory(index, -1)"',
    );
    expect(descriptor.template.content).toContain(
      '@click="deleteHistory(item)"',
    );
    expect(descriptor.template.content).toContain(
      '@click="activateHistory(item)"',
    );
    expect(descriptor.script.content).toContain(
      '"professions/changeCharacterProfession"',
    );
    expect(descriptor.script.content).toContain(
      '"professions/reorderCharacterProfessions"',
    );
    expect(descriptor.script.content).toContain(
      '"professions/deleteCharacterProfession"',
    );
    expect(descriptor.script.content).toContain(
      '"professions/activateCharacterProfession"',
    );
  });

  it("searches profession names and exposes a detailed suggestion tooltip", () => {
    const source = readFileSync(autocompletePath, "utf8");
    const { descriptor } = parse(source, { filename: autocompletePath });

    expect(descriptor.template.content).toContain('role="combobox"');
    expect(descriptor.template.content).toContain('role="listbox"');
    expect(descriptor.template.content).toContain('role="tooltip"');
    expect(descriptor.template.content).toContain("previewSections");
    expect(descriptor.script.content).toContain("name.startsWith(query)");
    expect(descriptor.script.content).toContain("development.skills");
    expect(descriptor.script.content).toContain("development.talents");
    expect(descriptor.script.content).toContain("development.paths?.exits");
  });

  it("shows a switchable random figure in the sidebar and both in the window", () => {
    const source = readFileSync(detailPath, "utf8");
    const { descriptor } = parse(source, { filename: detailPath });

    expect(descriptor.template.content).toContain("<AuthenticatedImage");
    expect(descriptor.template.content).toContain("availableImages.length > 1");
    expect(descriptor.template.content).toContain(
      "compact ? [] : availableImages",
    );
    expect(descriptor.script.content).toContain("Math.random()");
    expect(descriptor.script.content).toContain('this.$emit("open-window")');
  });

  it("uses the compact sidebar visual language instead of the HUD skin", () => {
    const source = readFileSync(sourcePath, "utf8");
    const styles = readFileSync(stylesPath, "utf8");
    const windowStyles = readFileSync(windowStylesPath, "utf8");

    expect(source).toContain("professions-content__toolbar");
    expect(source).not.toContain("professions-content__open-window");
    expect(source).not.toContain("professions-content__titlebar");
    expect(source).not.toContain("professions-content__footer");
    expect(styles).toContain("var(--ui-font-sans)");
    expect(styles).toContain("var(--ui-color-surface)");
    expect(styles).not.toMatch(
      /parchment|titleBar-center|Georgia|Times New Roman/,
    );
    expect(windowStyles).not.toContain(
      ".table-floating-window--professions .table-floating-window__header",
    );
  });
});
