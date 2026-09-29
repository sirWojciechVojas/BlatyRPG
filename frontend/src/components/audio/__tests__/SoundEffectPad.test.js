import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { afterEach, describe, expect, it, vi } from "vitest";
import { createApp } from "vue";

const componentPath = resolve(
  process.cwd(),
  "src/components/audio/SoundEffectPad.vue",
);
const descriptor = parse(readFileSync(componentPath, "utf8"), {
  filename: componentPath,
}).descriptor;
const executable = descriptor.script.content
  .replace(/^import[^;]+;\s*$/gmu, "")
  .replace("export default {", "return {");
const Pad = new Function(executable)();
Pad.template = descriptor.template.content;

let app;
let host;

afterEach(() => {
  app?.unmount();
  host?.remove();
  app = null;
  host = null;
});

const mount = () => {
  const trigger = vi.fn();
  host = document.createElement("div");
  document.body.appendChild(host);
  app = createApp({
    components: { SoundEffectPad: Pad },
    data: () => ({
      pad: {
        id: 4,
        name: "Thunder",
        icon: "weather",
        color: "#cc8844",
        shortcut: "SHIFT+1",
        audio: { duration: 3.4, available: true },
      },
    }),
    methods: { trigger },
    template:
      '<SoundEffectPad :position="2" :pad="pad" :text-lines="3" @trigger="trigger" />',
  });
  app.config.globalProperties.$t = (key) => key;
  app.mount(host);
  return { button: host.querySelector("button"), trigger };
};

describe("SoundEffectPad input behavior", () => {
  it("triggers playback from one click", async () => {
    const { button, trigger } = mount();
    button.dispatchEvent(new MouseEvent("click", { bubbles: true }));
    await Promise.resolve();
    expect(trigger).toHaveBeenCalledWith(2);
  });

  it("does not expose or emit the sidebar full-window action on double click", async () => {
    const { button, trigger } = mount();
    button.dispatchEvent(new MouseEvent("dblclick", { bubbles: true }));
    await Promise.resolve();
    expect(trigger).not.toHaveBeenCalled();
    expect(Pad.emits).toEqual(["trigger"]);
  });

  it("applies the remembered number of title lines", () => {
    const { button } = mount();
    expect(button.style.getPropertyValue("--sound-effect-text-lines")).toBe(
      "3",
    );
  });
});
