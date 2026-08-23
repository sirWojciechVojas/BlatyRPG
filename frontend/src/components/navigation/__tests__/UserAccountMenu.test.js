import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { createApp, nextTick } from "vue";
import { afterEach, describe, expect, it, vi } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/navigation/UserAccountMenu.vue",
);
const source = readFileSync(componentPath, "utf8");
let app;
let host;

const loadComponent = () => {
  const { descriptor } = parse(source, { filename: componentPath });
  const executable = descriptor.script.content.replace(
    "export default {",
    "return {",
  );
  const component = new Function(executable)();
  component.template = descriptor.template.content;
  return component;
};

const mount = async (props = {}) => {
  host = document.createElement("div");
  document.body.append(host);
  app = createApp(loadComponent(), {
    session: {
      user: {
        username: "Alicja",
        email: "alicja@example.test",
        role: "admin",
      },
    },
    isAdmin: true,
    ...props,
  });
  app.component("router-link", {
    props: ["to"],
    template: "<a href='#'><slot /></a>",
  });
  app.config.globalProperties.$t = (key) => key;
  app.config.globalProperties.$route = { name: "tables" };
  const vm = app.mount(host);
  await nextTick();
  return vm;
};

afterEach(() => {
  app?.unmount();
  app = null;
  host?.remove();
  host = null;
});

describe("UserAccountMenu", () => {
  it("opens a complete account menu and exposes administrator access", async () => {
    await mount();
    host.querySelector(".account-menu__trigger").click();
    await nextTick();

    expect(host.querySelector("[role='menu']")).not.toBeNull();
    expect(host.textContent).toContain("auth.account.panel");
    expect(host.textContent).toContain("auth.account.tables");
    expect(host.textContent).toContain("auth.account.invitations");
    expect(host.textContent).toContain("auth.account.admin");
    expect(host.textContent).toContain("auth.account.logout");
  });

  it("closes on outside interaction and emits logout", async () => {
    const onLogout = vi.fn();
    await mount({ isAdmin: false, onLogout });
    host.querySelector(".account-menu__trigger").click();
    await nextTick();
    document.body.dispatchEvent(new Event("pointerdown", { bubbles: true }));
    await nextTick();
    expect(host.querySelector("[role='menu']")).toBeNull();

    host.querySelector(".account-menu__trigger").click();
    await nextTick();
    host.querySelector(".account-menu__logout").click();
    expect(onLogout).toHaveBeenCalledOnce();
    expect(host.textContent).not.toContain("auth.account.admin");
  });
});
