import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/admin/AdminProfessionsTab.vue",
);
const pickerPath = resolve(
  process.cwd(),
  "src/components/admin/AdminProfessionRequirementPicker.vue",
);
const adminViewPath = resolve(process.cwd(), "src/views/AdminView.vue");
const adminOptionsPath = resolve(
  process.cwd(),
  "src/views/options/AdminView.options.js",
);
const descriptor = parse(readFileSync(componentPath, "utf8"), {
  filename: componentPath,
}).descriptor;

describe("AdminProfessionsTab", () => {
  it("provides an article-style editor for every persisted catalog field", () => {
    const template = descriptor.template.content;

    expect(template).toContain("admin-profession-editor__article");
    expect(template).toContain("admin-profession-editor__inspector");
    expect(template).toContain('v-model.trim="query"');
    expect(template).toContain('v-model="systemFilter"');
    expect(template).toContain('v-model="typeFilter"');
    expect(template).toContain('v-model="draft.name"');
    expect(template).toContain('v-model="draft.description"');
    expect(template).toContain('v-model="draft.details"');
    expect(template).toContain('v-model="draft.isAdvanced"');
    expect(template).toContain('v-model="draft.isMain"');
    expect(template).toContain("<AdminProfessionRequirementPicker");
    expect(template).toContain('v-model="draft.skillItems"');
    expect(template).toContain('v-model="draft.talentItems"');
    expect(template).toContain("<AuthenticatedImage");
    expect(template).toContain('accept="image/png,image/jpeg,image/webp"');
  });

  it("uses protected CRUD, stale-write protection and safe deletion metadata", () => {
    const template = descriptor.template.content;
    const script = descriptor.script.content;

    expect(template).toContain("original?.canDelete");
    expect(script).toContain("adminApiClient.professions()");
    expect(script).toContain("adminApiClient.createProfession(");
    expect(script).toContain("adminApiClient.updateProfession(");
    expect(script).toContain("adminApiClient.deleteProfession(");
    expect(script).toContain("result.requirementOptions");
    expect(script).toContain("serializeRequirements(this.draft.skillItems)");
    expect(script).toContain("adminApiClient.uploadProfessionImage(");
    expect(script).toContain("adminApiClient.deleteProfessionImage(");
    expect(script).toContain("payload.updatedAt = this.original.updatedAt");
    expect(script).toContain(
      'this.$store.commit("professions/SET_CONTEXT", null)',
    );
  });

  it("adds and removes decoded requirements from first-letter suggestions", () => {
    const picker = parse(readFileSync(pickerPath, "utf8"), {
      filename: pickerPath,
    }).descriptor;

    expect(picker.template.content).toContain('role="combobox"');
    expect(picker.template.content).toContain('role="listbox"');
    expect(picker.template.content).toContain("choose(option)");
    expect(picker.template.content).toContain("remove(index)");
    expect(picker.script.content).toContain("word.startsWith(query)");
    expect(picker.script.content).toContain('this.$emit("update:modelValue"');
  });

  it("is registered as a contained administrator tab", () => {
    const view = readFileSync(adminViewPath, "utf8");
    const options = readFileSync(adminOptionsPath, "utf8");

    expect(view).toContain("<AdminProfessionsTab");
    expect(view).toContain("activeTab === 'professions'");
    expect(options).toContain('id: "professions"');
    expect(options).toContain("AdminProfessionsTab");
  });
});
