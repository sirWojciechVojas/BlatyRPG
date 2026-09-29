import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it, vi } from "vitest";

const filename = resolve(
  process.cwd(),
  "src/components/vtt/scene/SceneSettingsPanel.vue",
);
const descriptor = parse(readFileSync(filename, "utf8"), {
  filename,
}).descriptor;
const template = descriptor.template?.content || "";
const script = descriptor.script?.content || "";
const styles = readFileSync(
  resolve(process.cwd(), "src/components/vtt/scene/styles/scene-settings.css"),
  "utf8",
);
const rangeField = parse(
  readFileSync(
    resolve(
      process.cwd(),
      "src/components/vtt/scene/SceneSettingsRangeField.vue",
    ),
    "utf8",
  ),
).descriptor;
const preview = parse(
  readFileSync(
    resolve(process.cwd(), "src/components/vtt/scene/SceneSettingsPreview.vue"),
    "utf8",
  ),
).descriptor;

describe("SceneSettingsPanel contract", () => {
  it("keeps navigation and footer fixed around the scrolling section content", () => {
    expect(template).toContain('class="scene-settings__tabs"');
    expect(template).toContain('role="tablist"');
    expect(template).toContain('class="scene-settings__content"');
    expect(template).toContain('class="scene-settings__footer"');
    expect(styles).toMatch(
      /\.scene-settings__form\s*\{[^}]*grid-template-rows:\s*minmax\(0, 1fr\) auto/s,
    );
    expect(styles).toMatch(
      /\.scene-settings__content\s*\{[^}]*overflow:\s*auto/s,
    );
  });

  it("provides all five sections and one persistent preview", () => {
    for (const section of ["basic", "map", "grid", "lighting"]) {
      expect(template).toContain(`activeSection === '${section}'`);
    }
    expect(template).toContain(":id=\"id('panel-fog')\"");
    expect(template).not.toContain("previewOpen");
    expect(template.match(/<SceneSettingsPreview/g)).toHaveLength(1);
    expect(template).toContain('class="scene-settings-preview--persistent"');
    expect(styles).toContain('"preview content"');
    expect(styles).toContain("@container scene-settings-window");
  });

  it("uses explicit local save, restoration, validation and confirmed fog clearing", () => {
    expect(script).toContain("sceneDraftFingerprint(this.form)");
    expect(script).toContain(
      "sceneDraftChanges(this.form, this.baselineDraft)",
    );
    expect(script).toContain("validateSceneDraft");
    expect(script).toContain("uploadPendingFile");
    expect(script).toContain("handleSaveShortcut");
    expect(template).toContain('@click="restore"');
    expect(template).toContain("<UiConfirmDialog");
    expect(template).toContain(':disabled="!form.fogEnabled"');
    expect(script).toContain('$emit("clear-fog"');
  });

  it("allows every percentage slider value to be entered numerically", () => {
    const rangeTemplate = rangeField.template?.content || "";
    const rangeScript = rangeField.script?.content || "";

    expect(template.match(/<RangeField/g)).toHaveLength(7);
    expect(template.match(/\spercent(?:\s|\n)/g)).toHaveLength(6);
    expect(rangeTemplate).toContain('type="number"');
    expect(rangeTemplate).toContain('@input="updatePercent"');
    expect(rangeScript).toContain(
      'this.$emit("update:modelValue", clamped / 100)',
    );
    expect(styles).toContain(
      "--scene-settings-range-control-height: var(--ui-control-height, 1.875rem)",
    );

    const component = new Function(
      rangeScript.replace("export default {", "return {"),
    )();
    const emit = vi.fn();
    const target = { valueAsNumber: 57, value: "57" };
    component.methods.updatePercent.call(
      { percentMinimum: 0, percentMaximum: 100, $emit: emit },
      { target },
    );

    expect(emit).toHaveBeenCalledWith("update:modelValue", 0.57);
  });

  it("uses a representative settings sample instead of the real map", () => {
    const previewTemplate = preview.template?.content || "";
    const previewScript = preview.script?.content || "";

    expect(previewTemplate).toContain(
      'class="scene-settings-preview__terrain"',
    );
    expect(previewTemplate).toContain('class="scene-settings-preview__road"');
    expect(previewTemplate).toContain('class="scene-settings-preview__token');
    expect(previewTemplate).not.toContain("SceneBackgroundImage");
    expect(previewTemplate).not.toContain("scene-settings-preview__zoom");
    expect(previewScript).toContain("previewWidth: 600");
    expect(previewScript).toContain("buildGridPattern(this.scene)");
    expect(template).not.toContain(':source="previewUrl"');
  });
});
