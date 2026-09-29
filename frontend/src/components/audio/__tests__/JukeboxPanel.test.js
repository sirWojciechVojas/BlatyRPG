import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { afterEach, describe, expect, it, vi } from "vitest";
import { createApp } from "vue";

const componentPath = resolve(
  process.cwd(),
  "src/components/audio/JukeboxPanel.vue",
);
const descriptor = parse(readFileSync(componentPath, "utf8"), {
  filename: componentPath,
}).descriptor;
const compactStyles = readFileSync(
  resolve(process.cwd(), "src/components/audio/jukeboxCompact.css"),
  "utf8",
);
const executable = descriptor.script.content
  .replace(/^import[^;]+;\s*$/gmu, "")
  .replace("export default {", "return {");
const PlaybackStub = {
  props: ["channelIds"],
  template:
    '<div class="playback-stub"><i v-for="id in channelIds" :key="id" class="jukebox-channel-row"></i></div>',
};
const Panel = new Function(
  "JukeboxLibraryTab",
  "JukeboxPlaybackTab",
  "JukeboxPlaylistsTab",
  executable,
)({}, PlaybackStub, {});
Panel.template = descriptor.template.content;

let app = null;
let host = null;

const mountAt = (width, realtimeStatus = "connected") => {
  window.innerWidth = width;
  host = document.createElement("div");
  host.style.width = `${width}px`;
  document.body.appendChild(host);
  app = createApp(Panel, { campaignId: 5 });
  app.config.globalProperties.$t = (key) => key;
  app.config.globalProperties.$te = () => true;
  app.config.globalProperties.$store = {
    state: {
      realtime: { status: realtimeStatus },
      voice: {},
      jukebox: {
        campaignId: 5,
        phase: "ready",
        capabilities: { canManage: true, canControl: true },
        audioContextState: "running",
        audioBlocked: false,
        error: null,
      },
    },
    dispatch: vi.fn().mockResolvedValue(null),
  };
  app.mount(host);
  return host;
};

afterEach(() => {
  app?.unmount();
  host?.remove();
  app = null;
  host = null;
});

describe("JukeboxPanel compact widths", () => {
  it("keeps fluid sizing and collapses dense controls for the narrow module", () => {
    expect(compactStyles).toContain("width: 100%");
    expect(compactStyles).toContain("min-width: 0");
    expect(compactStyles).toContain("@media (max-width: 360px)");
    expect(compactStyles).toContain("grid-template-columns: 1fr");
    expect(compactStyles).not.toContain(
      ".audio-level-meter > output {\n  display: none",
    );
    expect(compactStyles).toContain("height: 0.4rem");
    expect(compactStyles).toContain("bottom: calc(100% + 0.35rem)");
    expect(compactStyles).toContain("width: 2.25rem");
    expect(compactStyles).toContain("z-index: 50");
  });

  it.each([320, 400, 430])(
    "renders all channels and tabs without a runtime error at %d px",
    (width) => {
      const consoleError = vi
        .spyOn(console, "error")
        .mockImplementation(() => {});
      try {
        const element = mountAt(width);
        expect(element.querySelectorAll('[role="tab"]')).toHaveLength(3);
        expect(element.querySelectorAll(".jukebox-channel-row")).toHaveLength(
          6,
        );
        expect(element.querySelector(".jukebox-compact")).not.toBeNull();
        expect(consoleError).not.toHaveBeenCalled();
      } finally {
        consoleError.mockRestore();
      }
    },
  );

  it("shows an active synchronization state for a ready realtime session", () => {
    const element = mountAt(400, "ready");

    expect(element.querySelector(".audio-status").dataset.status).toBe(
      "connected",
    );
    expect(element.querySelector(".audio-status").textContent.trim()).toBe(
      "audio.jukebox.realtimeConnected",
    );
  });
});
