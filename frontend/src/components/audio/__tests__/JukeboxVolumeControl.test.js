import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { afterEach, describe, expect, it, vi } from "vitest";
import { createApp, nextTick } from "vue";

const componentPath = resolve(
  process.cwd(),
  "src/components/audio/JukeboxVolumeControl.vue",
);
const descriptor = parse(readFileSync(componentPath, "utf8"), {
  filename: componentPath,
}).descriptor;
const executable = descriptor.script.content.replace(
  "export default {",
  "return {",
);
const VolumeControl = new Function(executable)();
VolumeControl.template = descriptor.template.content;

let app = null;
let host = null;

const mount = (props) => {
  host = document.createElement("div");
  document.body.appendChild(host);
  app = createApp(VolumeControl, props);
  app.mount(host);
  return host.querySelector(".jukebox-volume-control");
};

afterEach(() => {
  app?.unmount();
  host?.remove();
  app = null;
  host = null;
});

describe("JukeboxVolumeControl", () => {
  it("opens a vertical one-percent volume control", async () => {
    const preview = vi.fn();
    const commit = vi.fn();
    const element = mount({
      controlId: "master",
      value: 1,
      maximum: 1.4,
      label: "Whole jukebox volume",
      onPreview: preview,
      onCommit: commit,
    });

    element.querySelector(".jukebox-volume-control__toggle").click();
    await nextTick();
    const slider = element.querySelector('input[type="range"]');
    expect(slider.getAttribute("aria-orientation")).toBe("vertical");
    expect(slider.step).toBe("0.01");
    expect(slider.max).toBe("1.4");

    slider.value = "0.876";
    slider.dispatchEvent(new Event("input", { bubbles: true }));
    slider.dispatchEvent(new Event("change", { bubbles: true }));
    expect(preview).toHaveBeenCalledWith(0.88);
    expect(commit).toHaveBeenCalledWith(0.88);
  });

  it("offers mute and closes when the user clicks outside", async () => {
    const toggleMute = vi.fn();
    const element = mount({
      controlId: "master",
      value: 1,
      maximum: 1.4,
      label: "Whole jukebox volume",
      allowMute: true,
      muteLabel: "Mute",
      unmuteLabel: "Unmute",
      onToggleMute: toggleMute,
    });

    element.querySelector(".jukebox-volume-control__toggle").click();
    await nextTick();
    element.querySelector(".jukebox-volume-control__mute").click();
    expect(toggleMute).toHaveBeenCalledWith(true);

    document.body.dispatchEvent(new Event("pointerdown", { bubbles: true }));
    await nextTick();
    expect(
      element.querySelector(".jukebox-volume-control__popover"),
    ).toBeNull();
  });
});
