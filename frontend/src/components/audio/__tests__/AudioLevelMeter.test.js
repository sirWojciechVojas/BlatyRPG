import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { afterEach, describe, expect, it } from "vitest";
import { createApp } from "vue";

const componentPath = resolve(
  process.cwd(),
  "src/components/audio/AudioLevelMeter.vue",
);
const descriptor = parse(readFileSync(componentPath, "utf8"), {
  filename: componentPath,
}).descriptor;
const executable = descriptor.script.content.replace(
  "export default {",
  "return {",
);
const Meter = new Function(executable)();
Meter.template = descriptor.template.content;

let app = null;
let host = null;

const mount = (props) => {
  host = document.createElement("div");
  document.body.appendChild(host);
  app = createApp(Meter, props);
  app.mount(host);
  return host.querySelector(".audio-level-meter");
};

afterEach(() => {
  app?.unmount();
  host?.remove();
  app = null;
  host = null;
});

describe("AudioLevelMeter", () => {
  it("renders a real percentage for sources connected to Web Audio", () => {
    const element = mount({
      value: 0.62,
      label: "Signal level",
      available: true,
    });

    expect(element.querySelector('[role="meter"]')).not.toBeNull();
    expect(element.querySelector('[role="meter"]').ariaValueNow).toBe("62");
    expect(element.querySelector("output").textContent).toBe("62%");
  });

  it("shows activity without inventing an audio level for YouTube", () => {
    const element = mount({
      value: 0,
      label: "Source playback",
      available: false,
      active: true,
      unavailableLabel: "Exact level unavailable",
    });

    expect(element.classList.contains("audio-level-meter--active")).toBe(true);
    expect(element.querySelector('[role="status"]')).not.toBeNull();
    expect(
      element.querySelector('[role="status"]').hasAttribute("aria-valuenow"),
    ).toBe(false);
    expect(element.querySelector("output").textContent).toBe("▶");
  });
});
