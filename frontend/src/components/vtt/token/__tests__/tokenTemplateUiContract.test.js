import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const component = (name) => {
  const path = resolve(process.cwd(), `src/components/vtt/token/${name}.vue`);
  return parse(readFileSync(path, "utf8"), { filename: path }).descriptor;
};

describe("token template UI contracts", () => {
  it("provides search, drag and center placement in the library", () => {
    const descriptor = component("TokenTemplatePanel");
    const template = descriptor.template.content;
    const script = descriptor.script.content;

    expect(template).toContain('type="search"');
    expect(template).toContain('draggable="true"');
    expect(template).toContain('@dragstart="startDrag($event, template)"');
    expect(template).toContain("$emit('place', template)");
    expect(script).toContain("tokenTemplateApiClient.list");
    expect(script).toContain("beginTokenTemplateDrag");
  });

  it("supports existing, new and detached character assignment", () => {
    const descriptor = component("TokenCharacterAssignmentDialog");
    const template = descriptor.template.content;
    const script = descriptor.script.content;

    expect(template).toContain("mode === 'existing'");
    expect(template).toContain("mode === 'new'");
    expect(template).toContain("$emit('detach')");
    expect(script).toContain('this.$emit("assign"');
    expect(script).toContain('this.$emit("create"');
    expect(template).toContain("createdCharacterId");
    expect(
      descriptor.styles.some((style) => style.content.includes("@media")),
    ).toBe(true);
  });

  it("uses authenticated images for library assets and character portraits", () => {
    expect(component("TokenTemplatePanel").template.content).toContain(
      "<AuthenticatedImage",
    );
    expect(
      component("TokenCharacterAssignmentDialog").template.content,
    ).toContain("<AuthenticatedImage");
  });

  it("offers character assignment from both the HUD and token settings", () => {
    expect(component("TokenHud").template.content).toContain(
      "$emit('assign-character')",
    );
    expect(component("TokenSettingsPanel").template.content).toContain(
      "$emit('assign-character')",
    );
  });
});
