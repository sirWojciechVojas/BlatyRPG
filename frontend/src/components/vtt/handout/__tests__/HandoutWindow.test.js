import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/handout/HandoutWindow.vue",
);

describe("HandoutWindow", () => {
  it("opens existing handouts in reading mode and exposes an explicit edit action", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const template = descriptor.template.content;
    const script = descriptor.script.content;

    expect(template).toContain('v-if="canEdit && !editing"');
    expect(template).toContain("vtt.table.handouts.edit");
    expect(template).toContain('@click="beginEdit"');
    expect(template).toContain('v-if="editing"');
    expect(script).toContain("editing: false");
    expect(script).toContain("this.startEditing && this.canEdit");
  });

  it("keeps save, cancel and read-only document modes separate", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const template = descriptor.template.content;

    expect(template).toContain('@click="save"');
    expect(template).toContain('@click="cancelEdit"');
    expect(template).toContain("<HandoutEditor");
    expect(template).toContain("<HandoutDocument");
  });
});
