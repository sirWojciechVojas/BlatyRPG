import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const path = resolve(process.cwd(), "src/components/admin/AdminAssetsTab.vue");
const descriptor = parse(readFileSync(path, "utf8"), {
  filename: path,
}).descriptor;

describe("AdminAssetsTab", () => {
  it("provides server-backed grid, table, filters and cross-page selection", () => {
    const template = descriptor.template.content;
    const script = descriptor.script.content;
    expect(template).toContain("view === 'grid'");
    expect(template).toContain("view === 'table'");
    expect(template).toContain('v-model="filters.provider"');
    expect(template).toContain('v-model="filters.assignment"');
    expect(template).toContain('v-model="filters.status"');
    expect(script).toContain("new AbortController()");
    expect(script).toContain("selection: new Set()");
    expect(script).toContain("adminApiClient.mediaAssets");
  });

  it("keeps the dense workspace controls available without permanently consuming space", () => {
    const template = descriptor.template.content;
    const script = descriptor.script.content;

    expect(template).toContain("asset-library__advanced-filters");
    expect(template).toContain("asset-library__bulk-fields");
    expect(template).toContain("has-inspector");
    expect(script).toContain("hasAdvancedFilters()");
    expect(script).toContain("clearFilters()");
  });

  it("supports inspector previews, revisions, replacement and safe deletion", () => {
    const template = descriptor.template.content;
    const script = descriptor.script.content;
    expect(template).toContain("<audio");
    expect(template).toContain("<video");
    expect(template).toContain("<iframe");
    expect(script).toContain("revision: this.detail.revision");
    expect(script).toContain("replaceMediaAsset");
    expect(script).toContain('error?.code === "asset_in_use"');
  });

  it("presents registered external map sources without fetching them server-side", () => {
    const template = descriptor.template.content;
    const script = descriptor.script.content;

    expect(template).toContain('value="external"');
    expect(template).toContain("detail.sourceUrl");
    expect(template).toContain("availabilityStatus");
    expect(template).toContain("noopener noreferrer");
    expect(script).toContain("createExternalMediaAsset");
    expect(script).toContain("registerExternal");
  });

  it("surfaces Game Master audio inside the audio category and can publish a selected track", () => {
    const template = descriptor.template.content;
    const script = descriptor.script.content;

    expect(template).toContain("filters.category === 'audio'");
    expect(template).toContain("audioLibrarySearch");
    expect(template).toContain("audioLibraryPagination");
    expect(template).toContain("personalAudioTracks");
    expect(template).toContain("copyToGlobal");
    expect(template).toContain("moveToGlobal");
    expect(script).toContain("setAudioLibrary");
    expect(script).toContain("mediaAudioLibraries");
    expect(script).toContain("new AbortController()");
    expect(script).toContain("publishPersonalAudioTrack");
  });

  it("includes bulk metadata, collections and the four-slot character composer", () => {
    const template = descriptor.template.content;
    const script = descriptor.script.content;
    expect(script).toContain("bulkUpdateMediaAssets");
    expect(script).toContain("changeMediaCollectionAssets");
    for (const slot of ["avatar", "portrait", "token", "fullbody"]) {
      expect(script).toContain(`"${slot}"`);
    }
    expect(template).toContain("createCharacterSet");
  });
});
