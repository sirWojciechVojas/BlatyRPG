import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { afterEach, describe, expect, it, vi } from "vitest";
import { createApp, nextTick } from "vue";
import {
  SOUND_EFFECT_SCREEN_LIMITS,
  normalizeSoundEffectScreenLayout,
} from "@/lib/audio/soundEffectScreenLayout";

const componentPath = resolve(
  process.cwd(),
  "src/components/audio/SoundEffectScreenSettingsDialog.vue",
);
const descriptor = parse(readFileSync(componentPath, "utf8"), {
  filename: componentPath,
}).descriptor;
const executable = descriptor.script.content
  .replace(
    /import\s+\{[\s\S]*?\}\s+from\s+"@\/lib\/audio\/soundEffectScreenLayout";\s*/u,
    "",
  )
  .replace("export default {", "return {");
const Dialog = new Function(
  "SOUND_EFFECT_SCREEN_LIMITS",
  "normalizeSoundEffectScreenLayout",
  executable,
)(SOUND_EFFECT_SCREEN_LIMITS, normalizeSoundEffectScreenLayout);
Dialog.template = descriptor.template.content;

let app;
let host;

afterEach(() => {
  app?.unmount();
  host?.remove();
  app = null;
  host = null;
});

const mount = () => {
  const save = vi.fn();
  const cancel = vi.fn();
  host = document.createElement("div");
  document.body.appendChild(host);
  app = createApp(Dialog, {
    open: true,
    screen: {
      id: 12,
      label: "B",
      columns: 3,
      rows: 4,
      textLines: 1,
      padStyle: "square",
      slots: [{ position: 11 }, { position: 30 }],
    },
    onSave: save,
    onCancel: cancel,
  });
  app.config.globalProperties.$t = (key, params = {}) =>
    `${key}:${params.count || ""}`;
  app.mount(host);
  return { save, cancel };
};

describe("SoundEffectScreenSettingsDialog", () => {
  it("loads and emits the persistent format for the selected screen", async () => {
    const { save } = mount();
    await nextTick();
    const dialog = document.querySelector(".sound-effect-screen-settings");
    const inputs = dialog.querySelectorAll('input[type="number"]');
    const select = dialog.querySelector("select");

    ["8", "6", "3"].forEach((value, index) => {
      inputs[index].value = value;
      inputs[index].dispatchEvent(new Event("input", { bubbles: true }));
    });
    select.value = "compact";
    select.dispatchEvent(new Event("change", { bubbles: true }));
    dialog.dispatchEvent(
      new Event("submit", { bubbles: true, cancelable: true }),
    );
    await nextTick();

    expect(save).toHaveBeenCalledWith({
      columns: 8,
      rows: 6,
      textLines: 3,
      padStyle: "compact",
    });
  });

  it("closes on Escape without saving", async () => {
    const { save, cancel } = mount();
    await nextTick();
    document
      .querySelector(".sound-effect-screen-settings")
      .dispatchEvent(
        new KeyboardEvent("keydown", { key: "Escape", bubbles: true }),
      );

    expect(cancel).toHaveBeenCalledOnce();
    expect(save).not.toHaveBeenCalled();
  });
});
