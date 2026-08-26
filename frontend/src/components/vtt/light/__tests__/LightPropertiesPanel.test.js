import { describe, expect, it } from "vitest";
import {
  changedLightProperties,
  normalizeLightProperties,
} from "../lightPropertiesDraft";

const form = (overrides = {}) => ({
  brightRadius: 200,
  dimRadius: 400,
  darknessMin: 0,
  darknessMax: 1,
  lumens: 2200,
  color: "#FFD27A",
  ...overrides,
});

describe("LightPropertiesPanel saves", () => {
  it("builds a minimal update instead of a stale full snapshot", () => {
    const changes = changedLightProperties(form(), form({ color: "#ffffff" }));

    expect(changes).toEqual({ color: "#ffffff" });
  });

  it("does not produce an empty update", () => {
    expect(changedLightProperties(form(), form())).toEqual({});
  });

  it("normalizes dependent values before saving", () => {
    expect(
      normalizeLightProperties(
        form({ brightRadius: 500, dimRadius: 300, lumens: 2199.8 }),
      ),
    ).toMatchObject({ brightRadius: 300, dimRadius: 300, lumens: 2200 });
  });
});
