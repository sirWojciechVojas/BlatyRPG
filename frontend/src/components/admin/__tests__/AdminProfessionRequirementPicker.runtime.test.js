import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { createApp, nextTick } from "vue";
import { afterEach, describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/admin/AdminProfessionRequirementPicker.vue",
);
const descriptor = parse(readFileSync(componentPath, "utf8"), {
  filename: componentPath,
}).descriptor;
const executable = descriptor.script.content.replace(
  "export default {",
  "return {",
);
const AdminProfessionRequirementPicker = new Function(executable)();
AdminProfessionRequirementPicker.template = descriptor.template.content;

let app = null;

const mountPicker = () => {
  const host = document.createElement("div");
  document.body.append(host);
  app = createApp({
    components: { AdminProfessionRequirementPicker },
    data: () => ({
      selected: [],
      options: [
        {
          raw: "46(6)",
          display: "Znajomość języka (kislevski)",
        },
        {
          raw: "46(9)",
          display: "Znajomość języka (staroświatowy — Reikspiel)",
        },
        { raw: "14(2)", display: "Nauka (astronomia)" },
      ],
    }),
    template: `
      <AdminProfessionRequirementPicker
        v-model="selected"
        :options="options"
        label="Umiejętności"
        placeholder="Wpisz pierwsze litery"
        hint="Wybierz podpowiedź"
      />
    `,
  });
  app.config.globalProperties.$t = (key, values = {}) =>
    values.name ? `${key}:${values.name}` : key;
  app.mount(host);
  return host;
};

afterEach(() => {
  app?.unmount();
  app = null;
  document.body.innerHTML = "";
});

describe("AdminProfessionRequirementPicker runtime", () => {
  it("suggests by first letters, adds an ID and removes it", async () => {
    const host = mountPicker();
    const input = host.querySelector('[role="combobox"]');

    input.dispatchEvent(new FocusEvent("focus"));
    await nextTick();
    expect(host.querySelector('[role="listbox"]')).toBeNull();

    input.value = "kis";
    input.dispatchEvent(new Event("input", { bubbles: true }));
    await nextTick();

    const suggestions = host.querySelectorAll('[role="option"]');
    expect(suggestions).toHaveLength(1);
    expect(suggestions[0].textContent).toContain("kislevski");
    expect(suggestions[0].textContent).toContain("46(6)");

    suggestions[0].dispatchEvent(new MouseEvent("click", { bubbles: true }));
    await nextTick();

    const selected = host.querySelector(".admin-requirement-picker__selected");
    expect(selected.textContent).toContain("Znajomość języka (kislevski)");
    expect(selected.textContent).toContain("46(6)");

    selected
      .querySelector("button")
      .dispatchEvent(new MouseEvent("click", { bubbles: true }));
    await nextTick();
    expect(
      host.querySelector(".admin-requirement-picker__selected"),
    ).toBeNull();
  });

  it("matches words with Polish diacritics and supports keyboard selection", async () => {
    const host = mountPicker();
    const input = host.querySelector('[role="combobox"]');

    input.value = "nau";
    input.dispatchEvent(new Event("input", { bubbles: true }));
    await nextTick();
    expect(host.querySelector('[role="option"]').textContent).toContain(
      "Nauka (astronomia)",
    );

    input.dispatchEvent(
      new KeyboardEvent("keydown", { key: "Enter", bubbles: true }),
    );
    await nextTick();
    expect(host.textContent).toContain("ID: 14(2)");
  });
});
