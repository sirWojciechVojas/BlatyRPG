import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { createApp, nextTick } from "vue";
import { afterEach, describe, expect, it, vi } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/dashboard/CampaignCreateForm.vue",
);
const source = readFileSync(componentPath, "utf8");
let app;
let host;

const games = [
  {
    systemId: 2,
    universeId: 20,
    systemName: "Warhammer Fantasy",
    universeName: "Stary Świat",
  },
  {
    systemId: 1,
    universeId: 10,
    systemName: "Call of Cthulhu",
    universeName: "Lata 20.",
  },
  {
    systemId: 1,
    universeId: 11,
    systemName: "Call of Cthulhu",
    universeName: "Współczesność",
  },
];

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

const mount = async (onSubmit = vi.fn()) => {
  host = document.createElement("div");
  document.body.append(host);
  app = createApp(loadComponent(), { games, onSubmit });
  app.config.globalProperties.$t = (key) => key;
  const vm = app.mount(host);
  await nextTick();
  return { onSubmit, vm };
};

afterEach(() => {
  app?.unmount();
  app = null;
  host?.remove();
  host = null;
});

describe("CampaignCreateForm", () => {
  it("derives system and world choices from the managed game catalog", async () => {
    const { vm } = await mount();

    expect(vm.systems).toEqual([
      { id: 1, name: "Call of Cthulhu" },
      { id: 2, name: "Warhammer Fantasy" },
    ]);
    expect(vm.draft).toMatchObject({ systemId: 1, universeId: 10 });

    vm.draft.systemId = 2;
    await nextTick();

    expect(vm.universes).toEqual([{ id: 20, name: "Stary Świat" }]);
    expect(vm.draft.universeId).toBe(20);
  });

  it("submits canonical catalog ids and preserves a valid selection on reset", async () => {
    const onSubmit = vi.fn();
    const { vm } = await mount(onSubmit);
    vm.draft.name = "Enemy Within";
    vm.draft.description = "Campaign";
    vm.draft.systemId = 2;
    await nextTick();

    vm.submit();
    expect(onSubmit).toHaveBeenCalledWith({
      name: "Enemy Within",
      description: "Campaign",
      systemId: 2,
      universeId: 20,
    });

    vm.reset();
    expect(vm.draft).toMatchObject({
      name: "",
      description: "",
      systemId: 1,
      universeId: 10,
    });
  });
});
