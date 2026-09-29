const VOICE_CONSTRAINTS = Object.freeze({
  echoCancellation: true,
  noiseSuppression: true,
  autoGainControl: true,
});

export const VIDEO_QUALITY_PROFILES = Object.freeze({
  auto: Object.freeze({
    width: 1920,
    height: 1080,
    frameRate: 30,
    maxBitrate: 3_500_000,
  }),
  "360p": Object.freeze({
    width: 640,
    height: 360,
    frameRate: 20,
    maxBitrate: 500_000,
  }),
  "720p": Object.freeze({
    width: 1280,
    height: 720,
    frameRate: 30,
    maxBitrate: 1_500_000,
  }),
  "1080p": Object.freeze({
    width: 1920,
    height: 1080,
    frameRate: 30,
    maxBitrate: 3_500_000,
  }),
});

const browserMediaDevices = () =>
  typeof navigator === "undefined" ? null : navigator.mediaDevices;

const fallbackLabel = (kind, index) => {
  if (kind === "audioinput") return `Audio input ${index + 1}`;
  if (kind === "audiooutput") return `Audio output ${index + 1}`;
  return `Camera ${index + 1}`;
};

export const audioInputAvailability = (deviceId, inputs = []) => {
  const id = String(deviceId || "");
  if (!id) return { status: "not-configured", device: null };
  const device =
    (inputs || []).find((item) => String(item.deviceId) === id) || null;
  return { status: device ? "available" : "unavailable", device };
};

export const normalizeVideoQuality = (quality) =>
  Object.prototype.hasOwnProperty.call(VIDEO_QUALITY_PROFILES, quality)
    ? quality
    : "auto";

export const cameraConstraints = (deviceId = "", quality = "auto") => {
  const profile = VIDEO_QUALITY_PROFILES[normalizeVideoQuality(quality)];
  return {
    audio: false,
    video: {
      ...(deviceId ? { deviceId: { exact: String(deviceId) } } : {}),
      width: { ideal: profile.width },
      height: { ideal: profile.height },
      frameRate: { ideal: profile.frameRate, max: profile.frameRate },
    },
  };
};

export class MediaDeviceService {
  constructor(options = {}) {
    this.devices = options.mediaDevices || browserMediaDevices();
    this.listeners = new Set();
    this.listening = false;
    this.handleChange = () => void this.emitDevices();
  }

  async enumerate() {
    if (!this.devices?.enumerateDevices) {
      return { inputs: [], outputs: [], videoInputs: [] };
    }
    const items = await this.devices.enumerateDevices();
    const normalized = items
      .filter((item) =>
        ["audioinput", "audiooutput", "videoinput"].includes(item.kind),
      )
      .map((item, index) => ({
        deviceId: item.deviceId,
        groupId: item.groupId,
        kind: item.kind,
        label: item.label || fallbackLabel(item.kind, index),
      }));
    return {
      inputs: normalized.filter((item) => item.kind === "audioinput"),
      outputs: normalized.filter((item) => item.kind === "audiooutput"),
      videoInputs: normalized.filter((item) => item.kind === "videoinput"),
    };
  }

  captureMicrophone(deviceId = "") {
    return this.captureAudio(deviceId, VOICE_CONSTRAINTS);
  }

  captureExternalInput(deviceId) {
    if (!deviceId) throw new TypeError("audio_input_device_required");
    return this.captureAudio(deviceId, {
      echoCancellation: false,
      noiseSuppression: false,
      autoGainControl: false,
    });
  }

  captureAudio(deviceId, processing) {
    if (!this.devices?.getUserMedia) {
      throw new Error("media_devices_unavailable");
    }
    return this.devices.getUserMedia({
      audio: {
        ...(deviceId ? { deviceId: { exact: deviceId } } : {}),
        ...processing,
      },
      video: false,
    });
  }

  captureCamera(deviceId = "", quality = "auto") {
    if (!this.devices?.getUserMedia) {
      throw new Error("media_devices_unavailable");
    }
    return this.devices.getUserMedia(cameraConstraints(deviceId, quality));
  }

  async monitorExternalInput(deviceId, listener) {
    const stream = await this.captureExternalInput(deviceId);
    const Context =
      typeof window === "undefined"
        ? null
        : window.AudioContext || window.webkitAudioContext;
    if (!Context) {
      stream.getTracks().forEach((track) => track.stop());
      throw new Error("web_audio_unavailable");
    }
    const context = new Context({ latencyHint: "interactive" });
    if (context.state === "suspended") await context.resume().catch(() => {});
    const source = context.createMediaStreamSource(stream);
    const analyser = context.createAnalyser();
    analyser.fftSize = 512;
    analyser.smoothingTimeConstant = 0.72;
    source.connect(analyser);
    const samples = new Uint8Array(analyser.fftSize);
    let active = true;
    let frame = null;
    const raf =
      typeof requestAnimationFrame === "function"
        ? requestAnimationFrame
        : (callback) => setTimeout(callback, 50);
    const cancel =
      typeof cancelAnimationFrame === "function"
        ? cancelAnimationFrame
        : clearTimeout;
    const tick = () => {
      if (!active) return;
      analyser.getByteTimeDomainData(samples);
      let sum = 0;
      for (const sample of samples) {
        const normalized = (sample - 128) / 128;
        sum += normalized * normalized;
      }
      listener(Math.min(1, Math.sqrt(sum / samples.length) * 4));
      frame = raf(tick);
    };
    const stop = () => {
      if (!active) return;
      active = false;
      if (frame !== null) cancel(frame);
      try {
        source.disconnect();
        analyser.disconnect();
      } catch (_error) {
        // The graph may already be disconnected after device removal.
      }
      stream.getTracks().forEach((track) => track.stop());
      void context.close().catch(() => {});
      listener(0);
    };
    stream.getAudioTracks()[0]?.addEventListener("ended", stop, { once: true });
    tick();
    return stop;
  }

  subscribe(listener) {
    let active = true;
    this.listeners.add(listener);
    if (!this.listening) {
      this.devices?.addEventListener?.("devicechange", this.handleChange);
      this.listening = true;
    }
    void this.enumerate()
      .then((devices) => {
        if (active) listener(devices);
      })
      .catch(() => {
        if (active) listener({ inputs: [], outputs: [], videoInputs: [] });
      });
    return () => {
      active = false;
      this.listeners.delete(listener);
      if (!this.listeners.size && this.listening) {
        this.devices?.removeEventListener?.("devicechange", this.handleChange);
        this.listening = false;
      }
    };
  }

  async emitDevices() {
    const devices = await this.enumerate().catch(() => ({
      inputs: [],
      outputs: [],
      videoInputs: [],
    }));
    this.listeners.forEach((listener) => listener(devices));
    return devices;
  }
}

export const mediaDeviceService = new MediaDeviceService();
export { VOICE_CONSTRAINTS };
