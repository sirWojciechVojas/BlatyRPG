import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { afterEach, describe, expect, it, vi } from "vitest";
import { createApp, nextTick } from "vue";

const componentPath = resolve(
  process.cwd(),
  "src/components/audio/JukeboxPlaybackTab.vue",
);
const descriptor = parse(readFileSync(componentPath, "utf8"), {
  filename: componentPath,
}).descriptor;
const executable = descriptor.script.content
  .replace(/^import[^;]+;\s*$/gmu, "")
  .replace("export default {", "return {");
const AudioLevelMeter = { template: '<div class="meter-stub"></div>' };
const JukeboxMarqueeText = {
  props: ["text"],
  template: '<strong class="marquee-stub">{{ text }}</strong>',
};
const JukeboxVolumeControl = {
  props: ["maximum", "controlId"],
  template:
    '<button class="volume-stub" :data-maximum="maximum" :data-control="controlId"></button>',
};
const PlaybackTab = new Function(
  "AudioLevelMeter",
  "JukeboxMarqueeText",
  "JukeboxVolumeControl",
  executable,
)(AudioLevelMeter, JukeboxMarqueeText, JukeboxVolumeControl);
PlaybackTab.template = descriptor.template.content;

let app = null;
let host = null;

const channel = {
  trackId: 7,
  title: "Theme",
  status: "playing",
  currentTime: 65,
  duration: 180,
  volume: 0.55,
  localVolume: 0.8,
  muted: false,
  meterAvailable: false,
};

const mount = (canControl) => {
  const dispatch = vi.fn().mockResolvedValue({ sent: true });
  host = document.createElement("div");
  document.body.appendChild(host);
  app = createApp(PlaybackTab, {
    jukebox: {
      channels: { music: { ...channel } },
      queues: { music: [] },
      masterVolume: 1.2,
      masterMuted: false,
    },
    channelIds: ["music"],
    canControl,
  });
  app.config.globalProperties.$t = (key) => key;
  app.config.globalProperties.$te = () => true;
  app.config.globalProperties.$store = { dispatch };
  app.mount(host);
  return { element: host, dispatch };
};

afterEach(() => {
  app?.unmount();
  host?.remove();
  app = null;
  host = null;
});

describe("JukeboxPlaybackTab", () => {
  it("shows time and sends a synchronized seek from the progress bar", async () => {
    const { element, dispatch } = mount(true);
    const progress = element.querySelector(
      '.jukebox-channel-progress input[type="range"]',
    );

    expect(progress.max).toBe("180");
    expect(progress.step).toBe("0.1");
    expect(
      element.querySelector(".jukebox-channel-transport .jukebox-icon-actions"),
    ).not.toBeNull();
    expect(
      element.querySelector(".jukebox-channel-row__main .jukebox-icon-actions"),
    ).toBeNull();
    expect(
      element.querySelector(".jukebox-channel-progress output").textContent,
    ).toContain("1:05 / 3:00");

    progress.dispatchEvent(new Event("pointerdown", { bubbles: true }));
    progress.value = "90";
    progress.dispatchEvent(new Event("input", { bubbles: true }));
    await nextTick();
    expect(
      element.querySelector(".jukebox-channel-progress output").textContent,
    ).toContain("1:30 / 3:00");

    progress.dispatchEvent(new Event("change", { bubbles: true }));
    await nextTick();
    expect(dispatch).toHaveBeenCalledWith("jukebox/previewSeek", {
      channelId: "music",
      position: 90,
    });
    await vi.waitFor(() => {
      expect(dispatch).toHaveBeenCalledWith("jukebox/seek", {
        channelId: "music",
        position: 90,
      });
    });
  });

  it("gives a non-controller a local master up to 140 percent", () => {
    const { element } = mount(false);

    expect(element.querySelector(".jukebox-master-volume")).not.toBeNull();
    expect(
      element.querySelector('.volume-stub[data-control="master"]').dataset
        .maximum,
    ).toBe("1.4");
    expect(element.querySelector(".jukebox-section-heading")).toBeNull();
    expect(element.querySelector(".jukebox-channel-list")).toBeNull();
  });
});
