import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { afterEach, describe, expect, it } from "vitest";
import { createApp, nextTick } from "vue";

const componentPath = resolve(
  process.cwd(),
  "src/components/audio/JukeboxMarqueeText.vue",
);
const descriptor = parse(readFileSync(componentPath, "utf8"), {
  filename: componentPath,
}).descriptor;
const executable = descriptor.script.content.replace(
  "export default {",
  "return {",
);
const Marquee = new Function(executable)();
Marquee.template = descriptor.template.content;

let app = null;
let host = null;

const mount = (text) => {
  host = document.createElement("div");
  document.body.appendChild(host);
  app = createApp(Marquee, { text });
  const component = app.mount(host);
  return {
    component,
    element: host.querySelector(".jukebox-track-marquee"),
  };
};

afterEach(() => {
  app?.unmount();
  host?.remove();
  app = null;
  host = null;
});

describe("JukeboxMarqueeText", () => {
  it("starts marquee only when the title does not fit", async () => {
    const { component, element } = mount("A very long adventure soundtrack");
    const primary = element.querySelector(
      ".jukebox-track-marquee__track > span",
    );
    Object.defineProperty(element, "clientWidth", {
      configurable: true,
      value: 120,
    });
    Object.defineProperty(primary, "scrollWidth", {
      configurable: true,
      value: 280,
    });

    component.measure();
    await nextTick();
    expect(
      element.classList.contains("jukebox-track-marquee--overflowing"),
    ).toBe(true);
    expect(
      element.querySelectorAll(".jukebox-track-marquee__track > span"),
    ).toHaveLength(2);

    Object.defineProperty(element, "clientWidth", {
      configurable: true,
      value: 320,
    });
    component.measure();
    await nextTick();
    expect(
      element.classList.contains("jukebox-track-marquee--overflowing"),
    ).toBe(false);
  });
});
