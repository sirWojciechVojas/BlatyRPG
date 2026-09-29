import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { compileTemplate, parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/professions/ProfessionAutocomplete.vue",
);

describe("ProfessionAutocomplete", () => {
  it("compiles the autocomplete and detailed tooltip template", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });
    const result = compileTemplate({
      id: "profession-autocomplete-test",
      filename: componentPath,
      source: descriptor.template.content,
    });

    expect(result.errors).toEqual([]);
    expect(descriptor.template.content).toContain('role="combobox"');
    expect(descriptor.template.content).toContain('role="listbox"');
    expect(descriptor.template.content).toContain('role="tooltip"');
    expect(descriptor.template.content).toContain("previewInteractive");
    expect(descriptor.template.content).toContain(
      "profession-picker-tooltip__bridge",
    );
  });

  it("prioritizes first-letter matches and emits the selected id", () => {
    const source = readFileSync(componentPath, "utf8");
    const { descriptor } = parse(source, { filename: componentPath });

    expect(descriptor.script.content).toContain("name.startsWith(query)");
    expect(descriptor.script.content).toContain("word.startsWith(query)");
    expect(descriptor.script.content).toContain(
      'this.$emit("update:modelValue", Number(profession.id))',
    );
    expect(descriptor.script.content).toContain("}, 2000)");
    expect(descriptor.script.content).toContain("schedulePreviewClose");
  });
});
