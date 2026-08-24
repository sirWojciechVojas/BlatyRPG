import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

const read = (path) => readFileSync(resolve(process.cwd(), path), "utf8");

describe("application navigation", () => {
  it("keeps one branded global navbar outside the landing page", () => {
    const app = read("src/App.vue");

    expect(app.match(/class="app-nav-brand"/g)).toHaveLength(1);
    expect(app).toContain("BlatyRPG-logo.png");
    expect(app).toContain("landing.brand.title");
    expect(app).toContain('class="app-nav-links"');
    expect(app).toContain('class="app-nav-actions"');
    expect(app).not.toContain("nav-sep");
  });

  it("uses the same horizontal padding as the landing navbar", () => {
    const navigation = read("src/styles/ui/navigation.css");
    const landing = read("src/views/styles/home/base.css");
    const padding = "6px clamp(12px, 3vw, 42px)";

    expect(navigation).toContain(`padding: ${padding}`);
    expect(landing).toContain(`padding: ${padding}`);
    expect(navigation).toContain(
      "grid-template-columns: minmax(165px, 1fr) auto minmax(285px, 1fr)",
    );
    expect(navigation).not.toMatch(/\.app-navigation\s+a\s*\{/);
  });

  it("keeps the shared background and 3D Dice label on every navbar", () => {
    const navigation = read("src/styles/ui/navigation.css");
    const polish = JSON.parse(read("src/i18n/locales/pl.json"));
    const english = JSON.parse(read("src/i18n/locales/en.json"));

    expect(navigation).toContain("navbar-bg.jpg");
    expect(navigation).not.toContain("background-color: #0a0807");
    expect(polish.nav.diceRoller).toBe("Kości 3D");
    expect(english.nav.diceRoller).toBe("3D Dice");
  });
});
