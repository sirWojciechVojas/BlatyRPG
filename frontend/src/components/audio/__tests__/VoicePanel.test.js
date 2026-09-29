import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { afterEach, describe, expect, it, vi } from "vitest";
import { createApp, nextTick } from "vue";

const componentPath = resolve(
  process.cwd(),
  "src/components/audio/VoicePanel.vue",
);
const componentSource = readFileSync(componentPath, "utf8");
const descriptor = parse(componentSource, {
  filename: componentPath,
}).descriptor;
const panelStyles = readFileSync(
  resolve(process.cwd(), "src/components/audio/voicePanel.css"),
  "utf8",
);
const executable = descriptor.script.content
  .replace(/^import[^;]+;\s*$/gmu, "")
  .replace("export default {", "return {");
const IconStub = {
  props: ["name"],
  template: '<svg :data-icon="name"></svg>',
};
const VideoStageStub = {
  props: ["joined"],
  template: '<div class="video-stage-stub" :data-joined="joined"></div>',
};
const Panel = new Function("TableRailIcon", "VideoStage", executable)(
  IconStub,
  VideoStageStub,
);
Panel.template = descriptor.template.content;

const participants = () => [
  {
    identity: "player-muted",
    nickname: "Beta",
    role: "player",
    local: false,
    avatar: "",
    hasMicrophone: true,
    muted: true,
    speaking: false,
    meterAvailable: true,
    connectionQuality: "good",
    level: 0,
    volume: 1,
    pan: 0,
    localMuted: false,
  },
  {
    identity: "gm-local",
    nickname: "Admin",
    role: "gm",
    local: true,
    avatar: "",
    hasMicrophone: true,
    muted: false,
    speaking: false,
    meterAvailable: true,
    connectionQuality: "excellent",
    level: 0.17,
    volume: 1,
    pan: 0,
    localMuted: false,
  },
  {
    identity: "player-speaking",
    nickname: "Alpha",
    role: "player",
    local: false,
    avatar: "",
    hasMicrophone: true,
    muted: false,
    speaking: true,
    meterAvailable: true,
    connectionQuality: "good",
    level: 0.62,
    volume: 0.7,
    pan: -0.2,
    localMuted: false,
  },
];

let app = null;
let host = null;
let store = null;

const mountAt = (width = 400) => {
  window.innerWidth = width;
  host = document.createElement("div");
  host.style.width = `${width}px`;
  host.style.height = "640px";
  document.body.appendChild(host);
  store = {
    state: {
      voice: {
        campaignId: 5,
        status: "connected",
        joining: false,
        muted: false,
        deafened: false,
        pushToTalk: false,
        pushToTalkPressed: false,
        participants: participants(),
        audioBlocked: false,
        audioContextState: "running",
        error: null,
      },
      video: {
        cameraEnabled: true,
        busy: false,
        participants: [
          { identity: "gm-local", cameraOn: true },
          { identity: "player-speaking", cameraOn: true },
          { identity: "player-muted", cameraOn: false },
        ],
      },
    },
    dispatch: vi.fn().mockResolvedValue(null),
  };
  app = createApp(Panel, { campaignId: 5 });
  app.config.globalProperties.$t = (key, values = {}) =>
    Object.entries(values).reduce(
      (text, [name, value]) => text.replace(`{${name}}`, value),
      key,
    );
  app.config.globalProperties.$te = () => true;
  app.config.globalProperties.$store = store;
  app.mount(host);
  return host;
};

afterEach(() => {
  app?.unmount();
  host?.remove();
  app = null;
  host = null;
  store = null;
});

describe("VoicePanel", () => {
  it.each([320, 400, 430])(
    "renders the compact hierarchy without console errors at %d px",
    (width) => {
      const consoleError = vi
        .spyOn(console, "error")
        .mockImplementation(() => {});
      try {
        const element = mountAt(width);
        expect(element.querySelector(".voice-connection")).not.toBeNull();
        expect(element.querySelector(".video-stage-stub")).not.toBeNull();
        expect(element.querySelectorAll(".voice-member")).toHaveLength(3);
        expect(element.querySelectorAll(".voice-member__meter")).toHaveLength(
          3,
        );
        expect(
          element.querySelectorAll(".voice-self__controls button"),
        ).toHaveLength(4);
        expect(consoleError).not.toHaveBeenCalled();
      } finally {
        consoleError.mockRestore();
      }
    },
  );

  it("sorts speakers before the GM and all other players", () => {
    const element = mountAt();
    const names = [
      ...element.querySelectorAll(".voice-member__identity > strong"),
    ].map((node) => node.textContent.trim().split(/\s+/u)[0]);

    expect(names).toEqual(["Alpha", "Admin", "Beta"]);
    expect(element.querySelectorAll(".voice-member--speaking")).toHaveLength(1);
  });

  it("keeps volume and panorama inside the participant context menu", async () => {
    const element = mountAt();
    expect(
      element.querySelectorAll('.voice-member input[type="range"]'),
    ).toHaveLength(0);

    element.querySelector(".voice-member__settings-trigger").click();
    await nextTick();

    const controls = document.body.querySelectorAll(
      '.voice-context-menu input[type="range"]',
    );
    expect(controls).toHaveLength(2);
    controls[0].value = "0.45";
    controls[0].dispatchEvent(new Event("input", { bubbles: true }));
    controls[1].value = "0.4";
    controls[1].dispatchEvent(new Event("input", { bubbles: true }));

    expect(store.dispatch).toHaveBeenCalledWith("voice/setParticipantVolume", {
      identity: "player-speaking",
      volume: 0.45,
    });
    expect(store.dispatch).toHaveBeenCalledWith("voice/setParticipantPan", {
      identity: "player-speaking",
      pan: 0.4,
    });
  });

  it("routes PTT, deafen and local participant mute through the existing store", async () => {
    const element = mountAt();

    element.querySelector('.voice-switch[role="switch"]').click();
    const controlButtons = element.querySelectorAll(
      ".voice-self__controls button",
    );
    controlButtons[1].click();
    element.querySelector(".voice-member__settings-trigger").click();
    await nextTick();
    document.body
      .querySelector('.voice-context-menu button[role="menuitemcheckbox"]')
      .click();

    expect(store.dispatch).toHaveBeenCalledWith("voice/setPushToTalk", true);
    expect(store.dispatch).toHaveBeenCalledWith("voice/setDeafened", true);
    expect(store.dispatch).toHaveBeenCalledWith("voice/setParticipantMuted", {
      identity: "player-speaking",
      muted: true,
    });
  });

  it("toggles the local microphone directly from its participant row", () => {
    const element = mountAt();

    const localMicrophone = element.querySelector(
      ".voice-member--local .voice-member__microphone--button",
    );
    expect(localMicrophone).not.toBeNull();
    expect(localMicrophone.getAttribute("aria-label")).toBe("audio.voice.mute");

    localMicrophone.click();

    expect(store.dispatch).toHaveBeenCalledWith("voice/toggleMute");
  });

  it("renders centered camera controls and toggles the local camera", () => {
    const element = mountAt();
    const localRow = element.querySelector(".voice-member--local");
    const localCamera = localRow.querySelector(".voice-member__camera--button");

    expect(localCamera).not.toBeNull();
    expect(localCamera.querySelector("svg").dataset.icon).toBe("camera");
    expect(localCamera.getAttribute("aria-label")).toBe(
      "audio.video.turnCameraOff",
    );
    expect(
      element.querySelector('.voice-member__camera svg[data-icon="cameraOff"]'),
    ).not.toBeNull();
    expect(panelStyles).toContain(
      ".voice-panel-shell.audio-panel .voice-member__microphone",
    );
    expect(panelStyles).toContain("place-items: center");
    expect(panelStyles).toContain("line-height: 0");
    expect(panelStyles).toContain("visibility: visible");
    expect(panelStyles).toContain("stroke: currentcolor");

    localCamera.click();

    expect(store.dispatch).toHaveBeenCalledWith("video/toggleCamera");
  });

  it("opens a clamped participant context menu on right click", async () => {
    const element = mountAt();
    const participant = element.querySelector(".voice-member");

    participant.dispatchEvent(
      new MouseEvent("contextmenu", {
        bubbles: true,
        clientX: 120,
        clientY: 160,
      }),
    );
    await nextTick();

    const menu = document.body.querySelector(".voice-context-menu");
    expect(menu).not.toBeNull();
    expect(menu.getAttribute("role")).toBe("menu");
    expect(menu.style.left).toBe("120px");
    expect(menu.style.top).toBe("160px");
    expect(menu.querySelector("strong").textContent).toContain("Alpha");
  });

  it("owns only disposable panel listeners and keeps the participant list scrollable", () => {
    expect(componentSource).toContain(
      'window.removeEventListener("pointerdown", this.handleOutsidePointerDown)',
    );
    expect(componentSource).toContain(
      'window.removeEventListener("keydown", this.handleEscape)',
    );
    expect(componentSource).toContain(
      'window.removeEventListener("blur", this.releasePushToTalk)',
    );
    expect(panelStyles).toContain(".voice-members__list");
    expect(panelStyles).toContain("overflow: auto");
    expect(panelStyles).toContain("@media (max-width: 360px)");
    expect(panelStyles).toContain("@media (max-height: 560px)");
    expect(panelStyles).not.toContain("width: min(100%, 11rem)");
    expect(panelStyles).toContain(".voice-context-menu");
    expect(panelStyles).toContain("position: fixed");
  });
});
