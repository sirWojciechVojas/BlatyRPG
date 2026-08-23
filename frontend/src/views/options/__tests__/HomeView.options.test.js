import { describe, expect, it, vi } from "vitest";

vi.mock("@/components/home/LandingCtaSection.vue", () => ({ default: {} }));
vi.mock("@/components/home/LandingFeaturesSection.vue", () => ({
  default: {},
}));
vi.mock("@/components/home/LandingGallerySection.vue", () => ({ default: {} }));
vi.mock("@/components/home/LandingHeroSection.vue", () => ({ default: {} }));
vi.mock("@/components/home/LandingModulesSection.vue", () => ({ default: {} }));
vi.mock("@/components/home/LandingPlansSection.vue", () => ({ default: {} }));
vi.mock("@/components/home/LandingStatsSection.vue", () => ({ default: {} }));
vi.mock("@/components/home/LandingUspStrip.vue", () => ({ default: {} }));

import options from "@/views/options/HomeView.options";

describe("HomeView landing navigation", () => {
  it("restores every original landing destination and keeps plans accessible", () => {
    const state = options.data();

    expect(state.sectionLinks.map(({ target }) => target)).toEqual([
      "features",
      "gallery",
      "modules",
      "stats",
      "plans",
      "cta",
    ]);
  });

  it("uses background.jpg as the landing page background", () => {
    const state = options.data();
    const style = options.computed.styleVars.call({ assets: state.assets });

    expect(style["--landing-background"]).toBe(
      `url("${state.assets.background}")`,
    );
    expect(style["--landing-background"]).not.toBe(
      `url("${state.assets.bg2}")`,
    );
  });

  it("closes the compact menu before scrolling to a section", () => {
    const context = {
      menuOpen: true,
      closeMenu: vi.fn(),
      scrollTo: vi.fn(),
    };

    options.methods.selectSection.call(context, "gallery");

    expect(context.closeMenu).toHaveBeenCalledOnce();
    expect(context.scrollTo).toHaveBeenCalledWith("gallery");
  });

  it("toggles and closes the mobile menu deterministically", () => {
    const context = { menuOpen: false };

    options.methods.toggleMenu.call(context);
    expect(context.menuOpen).toBe(true);

    options.methods.closeMenu.call(context);
    expect(context.menuOpen).toBe(false);
  });
});
