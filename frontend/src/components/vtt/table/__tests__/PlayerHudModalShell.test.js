import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { createApp, h, nextTick, reactive } from "vue";
import { afterEach, describe, expect, it, vi } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/table/PlayerHudModalShell.vue",
);
const stylesPath = resolve(
  process.cwd(),
  "src/components/vtt/table/player-hud-modal-shell.css",
);
const componentSource = readFileSync(componentPath, "utf8");
const styles = readFileSync(stylesPath, "utf8");
const descriptor = parse(componentSource, {
  filename: componentPath,
}).descriptor;

const loadComponent = () => {
  const executable = descriptor.script.content.replace(
    "export default {",
    "return {",
  );
  const component = new Function(executable)();
  component.template = descriptor.template.content;
  return component;
};

let app;
let host;
let trigger;

const mountShell = async () => {
  const state = reactive({ open: true });
  const requests = vi.fn((reason) => {
    state.open = false;
    return reason;
  });
  trigger = document.createElement("button");
  trigger.textContent = "open";
  document.body.append(trigger);
  trigger.focus();
  host = document.createElement("div");
  document.body.append(host);
  const Shell = loadComponent();
  const Harness = {
    render() {
      return h(
        Shell,
        {
          modelValue: state.open,
          title: "Karta postaci",
          width: 1600,
          height: 842,
          contentClass: "player-hud-modal-shell__body--character",
          closeLabel: "Zamknij",
          onRequestClose: requests,
        },
        { default: () => h("button", { id: "inside-action" }, "Akcja") },
      );
    },
  };
  app = createApp(Harness);
  app.mount(host);
  await nextTick();
  await nextTick();
  return { requests, state };
};

afterEach(() => {
  app?.unmount();
  app = null;
  host?.remove();
  host = null;
  trigger?.remove();
  trigger = null;
  document.querySelector("[data-player-hud-modal-backdrop]")?.remove();
  document.body.style.overflow = "";
});

describe("PlayerHudModalShell", () => {
  it("owns the single wooden title bar and red close control", () => {
    expect(descriptor.template.content).toContain(
      "player-hud-modal-shell__header",
    );
    expect(descriptor.template.content).toContain(
      "data-player-hud-modal-close",
    );
    expect(styles).toContain("character-stats/titleBar-center.png");
    expect(styles).toContain("character-stats/gfx/buttonClose.png");
    expect(styles).toContain("calc(100vw - 24px)");
    expect(styles).toContain("calc(100dvh - 24px");
  });

  it("traps focus and restores it after an accepted close request", async () => {
    const { requests } = await mountShell();
    const close = document.querySelector("[data-player-hud-modal-close]");
    const inside = document.querySelector("#inside-action");

    expect(document.activeElement).toBe(close);
    close.dispatchEvent(
      new KeyboardEvent("keydown", {
        key: "Tab",
        shiftKey: true,
        bubbles: true,
        cancelable: true,
      }),
    );
    expect(document.activeElement).toBe(inside);

    close.click();
    await nextTick();
    await nextTick();
    expect(requests).toHaveBeenCalledWith("button");
    expect(document.activeElement).toBe(trigger);
  });

  it("reports backdrop and Escape dismissal through the same guardable event", async () => {
    const first = await mountShell();
    document.querySelector("[data-player-hud-modal-backdrop]").click();
    await nextTick();
    expect(first.requests).toHaveBeenCalledWith("backdrop");

    app.unmount();
    app = null;
    host.remove();
    host = null;
    trigger.remove();
    trigger = null;

    const second = await mountShell();
    document.dispatchEvent(
      new KeyboardEvent("keydown", {
        key: "Escape",
        bubbles: true,
        cancelable: true,
      }),
    );
    await nextTick();
    expect(second.requests).toHaveBeenCalledWith("escape");
  });
});
