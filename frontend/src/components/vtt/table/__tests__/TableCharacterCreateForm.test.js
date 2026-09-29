import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/table/TableCharacterCreateForm.vue",
);

describe("TableCharacterCreateForm", () => {
  it("stays inline and does not ask for a game selection", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const template = descriptor.template.content;

    expect(template).toContain('<form class="table-character-create"');
    expect(template).not.toContain('role="dialog"');
    expect(template).not.toContain("<select");
    expect(template).toContain("characters.create.campaignContext");
  });
});
