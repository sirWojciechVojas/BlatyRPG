import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { afterEach, describe, expect, it, vi } from "vitest";
import { createApp, nextTick } from "vue";

const componentPath = resolve(
  process.cwd(),
  "src/components/audio/JukeboxLibraryTab.vue",
);
const descriptor = parse(readFileSync(componentPath, "utf8"), {
  filename: componentPath,
}).descriptor;
const executable = descriptor.script.content
  .replace(/^import[^;]+;\s*$/gmu, "")
  .replace("export default {", "return {");
const LibraryTab = new Function("audioInputAvailability", executable)(() => ({
  device: null,
  status: "missing",
}));
LibraryTab.template = descriptor.template.content;

let app = null;
let host = null;

const mount = () => {
  const track = {
    id: 17,
    title: "Old title",
    url: "https://www.youtube.com/watch?v=oldvideo123",
    sourceType: "external",
    category: "music",
    library: { scope: "personal", name: "My library" },
  };
  const dispatch = vi.fn().mockResolvedValue({
    ...track,
    title: "New title",
    url: "https://youtu.be/newvideo123",
  });
  host = document.createElement("div");
  document.body.appendChild(host);
  app = createApp(LibraryTab, {
    jukebox: {
      tracks: [track],
      channels: { music: {} },
    },
    voice: { devices: { inputs: [] }, externalDeviceIds: {} },
    channelIds: ["music"],
    selectedChannel: "music",
    canManage: true,
    canControl: true,
  });
  app.config.globalProperties.$t = (key) => key;
  app.config.globalProperties.$store = { dispatch };
  app.mount(host);
  return { dispatch, element: host };
};

afterEach(() => {
  app?.unmount();
  host?.remove();
  app = null;
  host = null;
});

describe("JukeboxLibraryTab personal track editing", () => {
  it("edits the title and URL of an external GM-library track", async () => {
    const { dispatch, element } = mount();

    element.querySelector("[data-track-edit]").click();
    await nextTick();

    const title = element.querySelector("[data-track-edit-title]");
    const url = element.querySelector("[data-track-edit-url]");
    expect(title.value).toBe("Old title");
    expect(url.value).toBe("https://www.youtube.com/watch?v=oldvideo123");

    title.value = "New title";
    title.dispatchEvent(new Event("input", { bubbles: true }));
    url.value = "https://youtu.be/newvideo123";
    url.dispatchEvent(new Event("input", { bubbles: true }));
    element
      .querySelector("[data-track-edit-form]")
      .dispatchEvent(new Event("submit", { bubbles: true, cancelable: true }));

    await vi.waitFor(() => {
      expect(dispatch).toHaveBeenCalledWith("jukebox/updatePersonal", {
        trackId: 17,
        title: "New title",
        url: "https://youtu.be/newvideo123",
      });
    });
    await vi.waitFor(() => {
      expect(element.querySelector("[data-track-edit-form]")).toBeNull();
    });
  });
});
