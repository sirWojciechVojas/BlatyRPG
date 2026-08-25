import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/table/TablePanelContent.vue",
);

describe("TablePanelContent", () => {
  it("forwards token creation capability to the character browser", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });

    expect(descriptor.template.content).toContain(
      ':can-create-token="canCreateToken"',
    );
    expect(descriptor.template.content).toContain(':campaign="campaign"');
  });
});
