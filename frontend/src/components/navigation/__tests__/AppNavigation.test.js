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
  });

  it("uses the same horizontal padding as the landing navbar", () => {
    const navigation = read("src/styles/ui/navigation.css");
    const landing = read("src/views/styles/home/base.css");
    const padding = "6px clamp(12px, 3vw, 42px)";

    expect(navigation).toContain(`padding: ${padding}`);
    expect(landing).toContain(`padding: ${padding}`);
  });
});
