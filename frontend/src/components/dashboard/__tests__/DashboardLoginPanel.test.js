import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { createApp, nextTick } from "vue";
import { afterEach, describe, expect, it, vi } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/dashboard/DashboardLoginPanel.vue",
);
const componentSource = readFileSync(componentPath, "utf8");
let app;
let host;

function loadComponent() {
  const { descriptor } = parse(componentSource, { filename: componentPath });
  const executable = descriptor.script.content.replace(
    "export default {",
    "return {",
  );
  const component = new Function(executable)();
  component.template = descriptor.template.content;
  return component;
}

async function mount(props = {}) {
  const onSubmit = vi.fn();
  host = document.createElement("div");
  document.body.append(host);
  app = createApp(loadComponent(), {
    busy: false,
    error: "",
    logo: "/logo.png",
    onSubmit,
    ...props,
  });
  app.config.globalProperties.$t = (key) => key;
  app.component("RouterLink", {
    props: ["to"],
    template: "<a href='#'><slot /></a>",
  });
  const vm = app.mount(host);
  await nextTick();
  return { onSubmit, vm };
}

function inputValue(input, value) {
  input.value = value;
  input.dispatchEvent(new Event("input", { bubbles: true }));
}

afterEach(() => {
  app?.unmount();
  app = null;
  host?.remove();
  host = null;
});

describe("DashboardLoginPanel", () => {
  it("keeps one shared navbar on the login route", () => {
    const loginView = readFileSync(
      resolve(process.cwd(), "src/views/LoginView.vue"),
      "utf8",
    );

    expect(loginView).not.toContain("dashboard-topbar");
    expect(loginView).toContain("<DashboardLoginPanel");
  });

  it("submits a trimmed identifier without changing the password", async () => {
    const { onSubmit } = await mount();
    const identifier = host.querySelector("#login-identifier");
    const password = host.querySelector("#login-password");

    expect(identifier.autocomplete).toBe("username");
    expect(password.autocomplete).toBe("current-password");
    expect(identifier.required).toBe(true);
    expect(password.required).toBe(true);

    inputValue(identifier, "  game-master  ");
    inputValue(password, "  secret phrase  ");
    host
      .querySelector("form")
      .dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));
    await nextTick();

    expect(onSubmit).toHaveBeenCalledWith({
      login: "game-master",
      password: "  secret phrase  ",
    });
  });

  it("exposes password visibility, busy and error states", async () => {
    await mount();
    const password = host.querySelector("#login-password");
    const toggle = host.querySelector(".login-password-toggle");

    expect(password.type).toBe("password");
    toggle.click();
    await nextTick();
    expect(password.type).toBe("text");
    expect(toggle.getAttribute("aria-pressed")).toBe("true");

    app.unmount();
    app = null;
    host.remove();
    host = null;
    await mount({ busy: true, error: "Invalid credentials" });

    expect(host.querySelector("section").getAttribute("aria-busy")).toBe(
      "true",
    );
    expect(host.querySelector("form").getAttribute("aria-describedby")).toBe(
      "login-error",
    );
    expect(host.querySelector('[role="alert"]').textContent).toContain(
      "Invalid credentials",
    );
    expect(host.querySelectorAll("input:disabled")).toHaveLength(2);
    expect(host.querySelector(".login-submit__spinner")).not.toBeNull();
  });
});
