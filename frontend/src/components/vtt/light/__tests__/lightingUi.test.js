import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const component = (name) => {
  const path = resolve(process.cwd(), `src/components/vtt/light/${name}.vue`);
  return parse(readFileSync(path, "utf8"), { filename: path }).descriptor;
};

describe("scene lighting management UI", () => {
  it("keeps the list compact while exposing all quick actions", () => {
    const template = component("LightManagementPanel").template.content;
    expect(template).toContain("light.name");
    expect(template).toContain("light.lumensShort");
    expect(template).toContain("enabled: !light.enabled");
    expect(template).toContain("$emit('copy', light.id)");
    expect(template).not.toContain("light.x");
    expect(template).not.toContain("light.y");
  });

  it("shows one grouped settings section at a time", () => {
    const panel = component("LightPropertiesPanel");
    expect(panel.script.content).toContain("LIGHT_SETTING_GROUPS");
    expect(panel.template.content).toContain("activeGroup === group");
    expect(panel.template.content).toContain("activeNumberFields");
  });

  it("uses type-aware paths for selected and draft previews", () => {
    const template = component("SceneLightLayer").template.content;
    expect(template).toContain("technicalPath(display(light), false)");
    expect(template).toContain("technicalPath(creationPreview, true)");
    expect(template).toContain("isDirected(creationPreview)");
  });
});
