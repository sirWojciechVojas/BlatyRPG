import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { createApp, h, nextTick, ref } from "vue";
import { afterEach, describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/ui/UiConfirmDialog.vue",
);
const componentSource = readFileSync(componentPath, "utf8");
let mountedApp;

const UiButtonStub = {
  name: "UiButton",
  inheritAttrs: false,
  props: {
    type: {
      type: String,
      default: "button",
    },
    variant: {
      type: String,
      default: "default",
    },
    disabled: Boolean,
    loading: Boolean,
  },
  emits: ["click"],
  setup(props, { attrs, emit, slots }) {
    return () =>
      h(
        "button",
        {
          ...attrs,
          type: props.type,
          disabled: props.disabled || props.loading,
          "data-variant": props.variant,
          onClick: (event) => emit("click", event),
        },
        slots.default?.(),
      );
  },
};

function loadComponent() {
  const { descriptor } = parse(componentSource, { filename: componentPath });
  const executableScript = descriptor.script.content
    .replace('import UiButton from "./UiButton.vue";', "")
    .replace("export default {", "return {");
  const component = new Function("UiButton", executableScript)(UiButtonStub);
  component.template = descriptor.template.content;
  return component;
}

function mountDialog() {
  const Dialog = loadComponent();
  const visible = ref(false);
  const busy = ref(false);
  const events = { confirms: 0, cancels: [] };
  const origin = document.createElement("button");
  const host = document.createElement("div");
  origin.textContent = "Open";
  document.body.append(origin, host);

  const Harness = {
    setup() {
      return () =>
        h(Dialog, {
          modelValue: visible.value,
          "onUpdate:modelValue": (value) => {
            visible.value = value;
          },
          title: "Delete character?",
          description: "This operation cannot be undone.",
          confirmLabel: "Delete",
          cancelLabel: "Keep",
          danger: true,
          busy: busy.value,
          onConfirm: () => {
            events.confirms += 1;
          },
          onCancel: (reason) => {
            events.cancels.push(reason);
          },
        });
    },
  };

  mountedApp = createApp(Harness);
  mountedApp.mount(host);

  return { busy, events, origin, visible };
}

async function openDialog(context) {
  context.origin.focus();
  context.visible.value = true;
  await nextTick();
  await nextTick();

  return document.querySelector('[role="alertdialog"]');
}

afterEach(() => {
  mountedApp?.unmount();
  mountedApp = null;
  document.body.innerHTML = "";
});

describe("UiConfirmDialog", () => {
  it("teleports an accessible alert dialog and traps focus", async () => {
    const context = mountDialog();
    const dialog = await openDialog(context);
    const cancel = dialog.querySelector("[data-confirm-dialog-cancel]");
    const confirm = dialog.querySelector("[data-confirm-dialog-confirm]");

    expect(dialog.closest("body")).toBe(document.body);
    expect(dialog.getAttribute("aria-modal")).toBe("true");
    expect(dialog.getAttribute("aria-labelledby")).toBe(
      dialog.querySelector("h2").id,
    );
    expect(dialog.getAttribute("aria-describedby")).toBe(
      dialog.querySelector("p").id,
    );
    expect(document.activeElement).toBe(cancel);
    expect(confirm.dataset.variant).toBe("danger");

    confirm.focus();
    confirm.dispatchEvent(
      new KeyboardEvent("keydown", { key: "Tab", bubbles: true }),
    );
    expect(document.activeElement).toBe(cancel);

    cancel.dispatchEvent(
      new KeyboardEvent("keydown", {
        key: "Tab",
        shiftKey: true,
        bubbles: true,
      }),
    );
    expect(document.activeElement).toBe(confirm);

    confirm.dispatchEvent(
      new KeyboardEvent("keydown", { key: "Escape", bubbles: true }),
    );
    await nextTick();
    await nextTick();

    expect(context.events.cancels).toEqual(["escape"]);
    expect(document.querySelector('[role="alertdialog"]')).toBeNull();
    expect(document.activeElement).toBe(context.origin);
  });

  it("blocks cancel and confirm actions while busy", async () => {
    const context = mountDialog();
    const dialog = await openDialog(context);
    context.busy.value = true;
    await nextTick();

    const cancel = dialog.querySelector("[data-confirm-dialog-cancel]");
    const confirm = dialog.querySelector("[data-confirm-dialog-confirm]");
    expect(cancel.disabled).toBe(true);
    expect(confirm.disabled).toBe(true);
    expect(dialog.getAttribute("aria-busy")).toBe("true");

    confirm.click();
    dialog.dispatchEvent(
      new KeyboardEvent("keydown", { key: "Escape", bubbles: true }),
    );
    await nextTick();

    expect(context.events.confirms).toBe(0);
    expect(context.events.cancels).toEqual([]);
    expect(context.visible.value).toBe(true);

    context.busy.value = false;
    await nextTick();
    confirm.click();
    cancel.click();
    await nextTick();

    expect(context.events.confirms).toBe(1);
    expect(context.events.cancels).toEqual(["button"]);
    expect(context.visible.value).toBe(false);
  });
});
