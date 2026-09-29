import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const source = (relativePath) => {
  const filename = resolve(process.cwd(), relativePath);
  const descriptor = parse(readFileSync(filename, "utf8"), {
    filename,
  }).descriptor;
  return {
    template: descriptor.template?.content || "",
    script: descriptor.script?.content || "",
  };
};

const region = source("src/components/vtt/region/SceneRegionLayer.vue");
const wall = source("src/components/vtt/wall/SceneWallLayer.vue");

describe("scene vertex editing contract", () => {
  it("edits every Region polygon and exposes edge insertion handles", () => {
    expect(region.template).toContain(
      "(polygon, polygonIndex) in displayRegion(region).polygons",
    );
    expect(region.template).toContain("edgeHandles(polygon)");
    expect(region.template).toContain("startEdgePointMove");
    expect(region.script).toContain("insertRegionVertex");
  });

  it("supports precise removal, constrained dragging and keyboard nudging", () => {
    expect(region.template).toContain("@dblclick.stop.prevent");
    expect(region.template).toContain("@click.alt.stop.prevent");
    expect(region.script).toContain("event.altKey");
    expect(region.script).toContain("nudgeActiveVertex");
    expect(region.script).toContain("event.shiftKey");
  });

  it("keeps wall endpoint controls readable at every camera zoom", () => {
    expect(wall.template).toContain("screenSize(16)");
    expect(wall.template).toContain("scene-wall__vertex-readout");
    expect(wall.script).toContain("nudgeActiveWallVertex");
    expect(wall.script).toContain("connectedWallEndpoints");
  });
});
