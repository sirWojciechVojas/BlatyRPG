import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

describe("IconPickerDialog", () => {
  const source = readFileSync(
    resolve(process.cwd(), "src/components/shop/common/IconPickerDialog.vue"),
    "utf8",
  );

  it("keeps choosing an assignment separate from editing the icon itself", () => {
    expect(source).toContain("panelMode === 'select'");
    expect(source).toContain("currentIcon");
    expect(source).toContain("newIcon");
    expect(source).toContain("icon-change-preview__arrow");
    expect(source).toContain("panelMode === 'edit'");
    expect(source).toContain("replaceIconImages");
  });

  it("shows Polish controlled labels and exposes English accessibly", () => {
    expect(source).toContain("{{ entry.labelPl }}");
    expect(source).toContain(':title="entry.labelEn"');
    expect(source).toContain(':aria-label="metadataAriaLabel');
    expect(source).toContain("ShopHelpTooltip");
    expect(source).toContain("classificationSectionHelp");
    expect(source).toContain("matchingSectionHelp");
  });

  it("always exposes all categories and narrows only dependent fields", () => {
    expect(source).toContain(
      "const metadataCategoryOptions = computed(() => metadataCategoryEntries.value)",
    );
    expect(source).toContain(
      "entry.categoryCode === metadataDraft.value.typeKey",
    );
    expect(source).toContain(':disabled="!canEdit || !metadataDraft.typeKey"');
  });

  it("uses one category and dependent subcategory filter without quick tabs", () => {
    expect(source).toContain('v-model="typeFilter"');
    expect(source).toContain('v-model="subtypeFilter"');
    expect(source).toContain(':disabled="!typeFilter"');
    expect(source).not.toContain("icon-picker-quick-tabs");
  });
});
