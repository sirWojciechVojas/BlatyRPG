import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it, vi } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/table/TableCharacterAccessPanel.vue",
);
const source = readFileSync(componentPath, "utf8");
const { descriptor } = parse(source, { filename: componentPath });

const componentOptions = () => {
  const executable = descriptor.script.content.replace(
    "export default {",
    "return {",
  );
  return new Function(executable)();
};

describe("TableCharacterAccessPanel", () => {
  it("offers public visibility and a multi-user controller list", () => {
    const template = descriptor.template.content;

    expect(template).toContain("visibilityForEveryone");
    expect(template).toContain('role="switch"');
    expect(template).toContain("controlledBy");
    expect(template).toContain('type="checkbox"');
    expect(template).toContain("setController(member.userId");
    expect(template).not.toContain("access.levels");
    expect(template).not.toContain("grantAccess");
  });

  it("maps public visibility to observer or hidden access", async () => {
    const options = componentOptions();
    const dispatch = vi.fn().mockResolvedValue({ id: 1 });
    const emit = vi.fn();
    const vm = {
      characterId: 12,
      publicVisible: false,
      error: "",
      $store: { dispatch },
      $emit: emit,
      run: options.methods.run,
    };

    await options.methods.setPublicVisibility.call(vm, true);
    await options.methods.setPublicVisibility.call(vm, false);

    expect(dispatch.mock.calls).toEqual([
      [
        "campaignContext/updateCharacterVisibility",
        { characterId: 12, visibility: "observer" },
      ],
      [
        "campaignContext/updateCharacterVisibility",
        { characterId: 12, visibility: "none" },
      ],
    ]);
    expect(emit).toHaveBeenCalledTimes(2);
  });

  it("adds and removes independent character controllers", async () => {
    const options = componentOptions();
    const dispatch = vi.fn().mockResolvedValue({ id: 1 });
    const emit = vi.fn();
    const vm = {
      characterId: 12,
      error: "",
      $store: { dispatch },
      $emit: emit,
      run: options.methods.run,
    };

    await options.methods.setController.call(vm, 7, true);
    await options.methods.setController.call(vm, 8, false);

    expect(dispatch.mock.calls).toEqual([
      [
        "campaignContext/setCharacterAccess",
        { characterId: 12, userId: 7, accessLevel: "owner" },
      ],
      [
        "campaignContext/setCharacterAccess",
        { characterId: 12, userId: 8, accessLevel: "none" },
      ],
    ]);
    expect(emit).toHaveBeenCalledTimes(2);
  });
});
