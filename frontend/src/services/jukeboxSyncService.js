const actionTypes = new Set([
  "JUKEBOX_LOAD",
  "JUKEBOX_PLAY",
  "JUKEBOX_PAUSE",
  "JUKEBOX_STOP",
  "JUKEBOX_SEEK",
  "JUKEBOX_VOLUME",
  "JUKEBOX_MUTE",
  "JUKEBOX_LOOP",
  "JUKEBOX_FADE_IN",
  "JUKEBOX_FADE_OUT",
  "JUKEBOX_DEVICE",
]);

export class JukeboxSyncService {
  constructor(mixer, options = {}) {
    this.mixer = mixer;
    this.now = options.now || (() => Date.now());
    // Browser timer functions are Web IDL methods. Keeping an unbound reference
    // and later invoking it as `this.setInterval(...)` changes its receiver and
    // throws "Illegal invocation" in Chromium.
    this.setTimeout =
      options.setTimeout ||
      ((handler, delay) => window.setTimeout(handler, delay));
    this.clearTimeout =
      options.clearTimeout || ((timer) => window.clearTimeout(timer));
    this.setInterval =
      options.setInterval ||
      ((handler, delay) => window.setInterval(handler, delay));
    this.clearInterval =
      options.clearInterval || ((timer) => window.clearInterval(timer));
    this.onError = options.onError || (() => {});
    this.page =
      options.document || (typeof document === "undefined" ? null : document);
    this.offsetSamples = [];
    this.offsetMs = 0;
    this.pendingSync = new Map();
    this.timers = new Map();
    this.catalog = new Map();
    this.state = null;
    this.generation = 0;
    this.driftTimer = null;
    this.visibilityListening = false;
    this.visibilityHandler = () => {
      if (this.page?.hidden) return;
      this.run(this.mixer.recoverPlayback?.());
      this.checkDrift();
    };
  }

  setCatalog(items) {
    this.catalog = new Map(
      (items || []).map((item) => [Number(item.id), item]),
    );
    if (this.state) this.run(this.applyState(this.state));
  }

  createSyncRequest(requestId) {
    const clientSentAt = this.now();
    this.pendingSync.set(requestId, clientSentAt);
    return { type: "JUKEBOX_SYNC", requestId, clientSentAt };
  }

  handle(event) {
    if (event?.type === "JUKEBOX_SYNC") {
      this.acceptClockSample(event.payload || {});
      return;
    }
    if (event?.type === "sync.snapshot" && event.payload?.jukebox) {
      this.run(this.applyState(event.payload.jukebox));
      return;
    }
    if (event?.type === "JUKEBOX_STATE") {
      this.run(this.applyState(event.payload || {}));
      return;
    }
    if (actionTypes.has(event?.type))
      this.run(this.applyAction(event.type, event.payload || {}));
  }

  acceptClockSample(payload) {
    const sent =
      this.pendingSync.get(payload.requestId) ?? Number(payload.clientSentAt);
    this.pendingSync.delete(payload.requestId);
    const received = this.now();
    const serverTime = Number(payload.serverTime);
    if (
      !Number.isFinite(sent) ||
      !Number.isFinite(serverTime) ||
      received < sent
    )
      return;
    const rtt = received - sent;
    if (rtt > 5000) return;
    this.offsetSamples.push({ offset: serverTime - (sent + rtt / 2), rtt });
    this.offsetSamples.sort((left, right) => left.rtt - right.rtt);
    this.offsetSamples = this.offsetSamples.slice(0, 8);
    const best = this.offsetSamples.slice(
      0,
      Math.min(5, this.offsetSamples.length),
    );
    this.offsetMs =
      best.reduce((sum, item) => sum + item.offset, 0) / best.length;
  }

  serverNow() {
    return this.now() + this.offsetMs;
  }

  async applyState(snapshot) {
    const generation = ++this.generation;
    this.state = snapshot;
    this.mixer.setDuckingSettings(snapshot.settings || {});
    const state = snapshot.state || {};
    const results = await Promise.allSettled(
      Object.entries(state).map(async ([channelId, channel]) => {
        this.cancel(channelId);
        if (channel.sourceType === "external-input" || !channel.trackId) {
          if (channel.sourceType === "external-input") {
            this.mixer.setExternalSource(channelId, {
              label: channel.deviceLabel || channel.sourceLabel,
              deviceSlot: channel.deviceSlot,
              status: channel.status,
            });
            this.applyMix(channelId, channel);
          }
          if (channel.status === "stopped") this.mixer.stop(channelId);
          return;
        }
        const track = this.catalog.get(Number(channel.trackId));
        if (!track) return;
        await this.mixer.load(
          channelId,
          {
            ...track,
            playlistId: channel.playlistId,
            sourceLabel: channel.sourceLabel,
          },
          this.expectedPosition(channel),
        );
        if (generation !== this.generation) return;
        this.applyMix(channelId, channel);
        if (channel.status === "playing") {
          const play = () =>
            this.mixer.play(channelId, this.expectedPosition(channel));
          const delay = Math.max(
            0,
            Number(channel.executeAt) - this.serverNow(),
          );
          if (delay > 15)
            this.timers.set(
              channelId,
              this.setTimeout(() => this.run(play()), delay),
            );
          else await play();
        } else if (channel.status === "stopped") this.mixer.stop(channelId);
        else this.mixer.pause(channelId);
      }),
    );
    const failed = results.find((result) => result.status === "rejected");
    if (failed) throw failed.reason;
    this.startDriftCheck();
  }

  async applyAction(type, payload) {
    const channelId = payload.channelId;
    if (!channelId) return;
    const executeAt = Number(payload.executeAt) || this.serverNow();
    if (type === "JUKEBOX_DEVICE") {
      this.cancel(channelId);
      this.rememberAction(type, payload, executeAt);
      const run = () =>
        this.mixer.setExternalSource(channelId, {
          label: payload.deviceLabel,
          deviceSlot: payload.deviceSlot,
          status: "playing",
        });
      const delay = Math.max(0, executeAt - this.serverNow());
      if (delay <= 15) run();
      else this.timers.set(channelId, this.setTimeout(run, delay));
      return;
    }
    if (
      ["JUKEBOX_LOAD", "JUKEBOX_PLAY", "JUKEBOX_FADE_IN"].includes(type) &&
      payload.trackId
    ) {
      const current = this.mixer.channels[channelId];
      if (
        Number(current?.trackId) !== Number(payload.trackId) ||
        Number(current?.playlistId || 0) !== Number(payload.playlistId || 0) ||
        String(current?.sourceLabel || "") !== String(payload.sourceLabel || "")
      ) {
        const track = this.catalog.get(Number(payload.trackId));
        if (!track) return;
        await this.mixer.load(
          channelId,
          {
            ...track,
            playlistId: payload.playlistId,
            sourceLabel: payload.sourceLabel,
          },
          Number(payload.position) || 0,
        );
      }
    }
    this.cancel(channelId);
    this.rememberAction(type, payload, executeAt);
    const run = () => {
      const lateBy = Math.max(0, this.serverNow() - executeAt) / 1000;
      const position = Math.max(0, Number(payload.position) || 0) + lateBy;
      if (type === "JUKEBOX_LOAD") {
        this.applyMix(channelId, payload);
        this.mixer.pause(channelId);
      } else if (type === "JUKEBOX_PLAY") {
        this.applyMix(channelId, payload);
        this.run(this.mixer.play(channelId, position));
      } else if (type === "JUKEBOX_PAUSE") this.mixer.pause(channelId);
      else if (type === "JUKEBOX_STOP") this.mixer.stop(channelId);
      else if (type === "JUKEBOX_SEEK") this.mixer.seek(channelId, position);
      else if (type === "JUKEBOX_VOLUME")
        this.mixer.setVolume(channelId, payload.volume);
      else if (type === "JUKEBOX_MUTE")
        this.mixer.setMuted(channelId, payload.muted);
      else if (type === "JUKEBOX_LOOP")
        this.mixer.setLoop(channelId, payload.loop);
      else if (type === "JUKEBOX_FADE_IN") {
        this.mixer.fadeIn(channelId, payload.fadeMs, payload.volume);
        this.run(this.mixer.play(channelId, position));
      } else if (type === "JUKEBOX_FADE_OUT")
        this.mixer.fadeOut(channelId, payload.fadeMs);
    };
    const delay = Math.max(0, executeAt - this.serverNow());
    if (delay <= 15) run();
    else this.timers.set(channelId, this.setTimeout(run, delay));
  }

  rememberAction(type, payload, executeAt) {
    const state = this.state?.state || {};
    const channel = { ...(state[payload.channelId] || {}), ...payload };
    channel.executeAt = executeAt;
    if (type === "JUKEBOX_LOAD") {
      channel.status = "paused";
      channel.startedAt = null;
    } else if (type === "JUKEBOX_DEVICE") {
      channel.status = "playing";
      channel.trackId = null;
      channel.playlistId = null;
      channel.sourceType = "external-input";
      channel.sourceLabel = payload.deviceLabel || "";
      channel.deviceLabel = payload.deviceLabel || "";
      channel.deviceSlot = payload.deviceSlot || null;
      channel.startedAt = executeAt;
    } else if (["JUKEBOX_PLAY", "JUKEBOX_FADE_IN"].includes(type)) {
      channel.status = "playing";
      channel.startedAt =
        executeAt - Math.max(0, Number(payload.position) || 0) * 1000;
    } else if (["JUKEBOX_PAUSE", "JUKEBOX_FADE_OUT"].includes(type)) {
      channel.status = "paused";
      channel.startedAt = null;
    } else if (type === "JUKEBOX_STOP") {
      channel.status = "stopped";
      channel.position = 0;
      channel.startedAt = null;
    } else if (type === "JUKEBOX_SEEK" && channel.status === "playing") {
      channel.startedAt =
        executeAt - Math.max(0, Number(payload.position) || 0) * 1000;
    }
    this.state = {
      ...(this.state || {}),
      state: { ...state, [payload.channelId]: channel },
    };
  }

  applyMix(channelId, channel) {
    if (channel.volume !== undefined)
      this.mixer.setVolume(channelId, channel.volume);
    if (channel.muted !== undefined)
      this.mixer.setMuted(channelId, channel.muted);
    if (channel.loop !== undefined) this.mixer.setLoop(channelId, channel.loop);
  }

  expectedPosition(channel) {
    let position = Math.max(0, Number(channel.position) || 0);
    if (Number(channel.executeAt) > this.serverNow()) return position;
    if (
      channel.status === "playing" &&
      Number.isFinite(Number(channel.startedAt))
    ) {
      position = Math.max(
        0,
        (this.serverNow() - Number(channel.startedAt)) / 1000,
      );
    }
    const duration = Number(channel.duration);
    if (duration > 0 && channel.loop) return position % duration;
    return duration > 0 ? Math.min(position, duration) : position;
  }

  startDriftCheck() {
    if (!this.visibilityListening && this.page?.addEventListener) {
      this.page.addEventListener("visibilitychange", this.visibilityHandler);
      this.visibilityListening = true;
    }
    if (this.driftTimer) return;
    this.driftTimer = this.setInterval(() => this.checkDrift(), 2000);
  }

  checkDrift() {
    if (this.page?.hidden) return;
    const state = this.state?.state || {};
    Object.entries(state).forEach(([channelId, channel]) => {
      if (
        channel.status === "playing" &&
        channel.sourceType !== "external-input"
      ) {
        this.mixer.correctDrift(channelId, this.expectedPosition(channel));
      }
    });
  }

  cancel(channelId) {
    const timer = this.timers.get(channelId);
    if (timer) this.clearTimeout(timer);
    this.timers.delete(channelId);
  }

  run(operation) {
    Promise.resolve(operation).catch((error) => this.onError(error));
  }

  destroy() {
    ++this.generation;
    [...this.timers.keys()].forEach((channelId) => this.cancel(channelId));
    if (this.driftTimer) this.clearInterval(this.driftTimer);
    this.driftTimer = null;
    if (this.visibilityListening) {
      this.page?.removeEventListener?.(
        "visibilitychange",
        this.visibilityHandler,
      );
      this.visibilityListening = false;
    }
    this.pendingSync.clear();
    this.catalog.clear();
    this.state = null;
  }
}
