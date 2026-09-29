import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/handout/HandoutWorkspace.vue",
);

describe("HandoutWorkspace", () => {
  it("can work as a list-only navigator for independent windows", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const template = descriptor.template.content;

    expect(template).toContain("'handout-workspace--compact': compact");
    expect(template).toContain("'handout-workspace--navigator': openInWindows");
    expect(template).toContain('v-if="!openInWindows"');
    expect(descriptor.script.content).toContain("openInWindows");
    expect(descriptor.script.content).toContain('$emit("open-handout"');
  });

  it("uses library, campaign and icon-only trash tabs", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const template = descriptor.template.content;

    expect(template).toContain("activateTab('library')");
    expect(template).toContain("activateTab('campaign')");
    expect(template).toContain("activateTab('trash')");
    expect(template).toContain('class="handout-workspace__trash-tab"');
    expect(template).toContain("vtt.table.handouts.trashTab");
  });

  it("aligns the three create actions and omits the library items heading", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const template = descriptor.template.content;

    expect(template.match(/handout-workspace__primary-action/g)).toHaveLength(
      4,
    );
    expect(template.match(/<span aria-hidden="true">\+<\/span>/g)).toHaveLength(
      3,
    );
    expect(template).not.toContain("vtt.table.handouts.libraryItems");
  });
});
