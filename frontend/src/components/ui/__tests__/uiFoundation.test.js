import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";
import { resolveRouteUi, routeUiRootClasses } from "@/components/ui/routeUi";

describe("route UI foundation", () => {
  it("uses metadata before compatibility fallbacks", () => {
    const ui = resolveRouteUi({
      name: "scene-workspace",
      meta: {
        ui: {
          layout: "public",
          workspace: false,
          navigation: "hidden",
        },
      },
    });

    expect(ui).toMatchObject({
      enabled: true,
      layout: "public",
      navigation: "hidden",
      showNavigation: false,
      isPublic: true,
      isWorkspace: false,
    });
  });

  it("keeps current workspace and overlay routes compatible", () => {
    expect(resolveRouteUi({ name: "scene-workspace" })).toMatchObject({
      layout: "workspace",
      isWorkspace: true,
      showNavigation: true,
    });
    expect(resolveRouteUi({ name: "shop-gm" })).toMatchObject({
      layout: "overlay",
      navigation: "overlay",
      enabled: true,
    });
  });

  it("does not activate the design system for DiceRoller3D", () => {
    const diceUi = resolveRouteUi({
      name: "dice",
      path: "/dice",
      meta: { uiSystem: true },
    });

    expect(diceUi.enabled).toBe(false);
    expect(diceUi.navigation).toBe("overlay");
    expect(routeUiRootClasses(diceUi)).toEqual([]);
  });

  it("returns scoped shell classes for regular routes", () => {
    expect(
      routeUiRootClasses(
        resolveRouteUi({
          name: "scene-workspace",
          meta: { layout: "workspace" },
        }),
      ),
    ).toEqual(["ui-system-active", "ui-shell", "ui-shell--workspace"]);
  });

  it("keeps custom primitives inactive without the route scope", () => {
    const files = [
      "src/styles/ui/foundation.css",
      "src/styles/ui/primitives.css",
      "src/styles/ui/tooltip.css",
    ];

    files.forEach((file) => {
      const source = readFileSync(resolve(process.cwd(), file), "utf8");
      const unscopedSelectors = source
        .split("\n")
        .filter((line) => /^\.ui-(?!system-active)/.test(line));
      expect(unscopedSelectors, file).toEqual([]);
    });
  });

  it("never stretches public navigation as page content", () => {
    const source = readFileSync(
      resolve(process.cwd(), "src/styles/ui/foundation.css"),
      "utf8",
    );

    expect(source).toContain("> :first-child:not(.app-navigation)");
    expect(source).not.toMatch(/ui-shell--public\s*>\s*:first-child\s*\{/);
  });

  it("loads one stable application scrollbar without layout shifts", () => {
    const index = readFileSync(
      resolve(process.cwd(), "src/styles/ui/index.css"),
      "utf8",
    );
    const source = readFileSync(
      resolve(process.cwd(), "src/styles/ui/scrollbar.css"),
      "utf8",
    );

    expect(index.trimEnd()).toMatch(/@import "\.\/scrollbar\.css";$/);
    expect(source).toMatch(/html\s*\{[^}]*scrollbar-gutter:\s*stable;/s);
    expect(source).toMatch(
      /html:has\(> body\.ui-shell--workspace\)\s*\{[^}]*scrollbar-gutter:\s*auto;/s,
    );
    expect(source).not.toMatch(/\*\s*\{[^}]*scrollbar-gutter:/s);
    expect(source).toContain("scrollbar-width: thin");
    expect(source).toContain("scrollbar-color:");
    expect(source).toContain("*::-webkit-scrollbar-thumb");
    expect(source).not.toMatch(/overflow-y:\s*scroll/);
  });

  it("keeps each UI foundation file below 300 lines", () => {
    const files = [
      "src/components/ui/routeUi.js",
      "src/components/ui/UiButton.vue",
      "src/components/ui/UiField.vue",
      "src/components/ui/UiPanel.vue",
      "src/components/ui/UiTooltip.vue",
      "src/styles/ui/tokens.css",
      "src/styles/ui/foundation.css",
      "src/styles/ui/primitives.css",
      "src/styles/ui/tooltip.css",
      "src/styles/ui/navigation.css",
      "src/styles/ui/scrollbar.css",
    ];

    files.forEach((file) => {
      const source = readFileSync(resolve(process.cwd(), file), "utf8");
      expect(source.trimEnd().split("\n").length, file).toBeLessThanOrEqual(
        300,
      );
    });
  });
});
