let apiPromise = null;

const playerError = (value) => {
  const code = Number(value);
  const names = {
    2: "youtube_invalid_parameter",
    5: "youtube_html5_error",
    100: "youtube_video_not_found",
    101: "youtube_embedding_disabled",
    150: "youtube_embedding_disabled",
    153: "youtube_client_identification_required",
  };
  const error = new Error(names[code] || "youtube_player_error");
  error.code = error.message;
  error.providerCode = Number.isFinite(code) ? code : null;
  return error;
};

const allowAutoplay = (iframe) => {
  if (!iframe) return;
  const permissions = new Set(
    String(iframe.getAttribute("allow") || "")
      .split(";")
      .map((value) => value.trim())
      .filter(Boolean),
  );
  permissions.add("autoplay");
  permissions.add("encrypted-media");
  iframe.setAttribute("allow", [...permissions].join("; "));
  iframe.setAttribute("referrerpolicy", "strict-origin-when-cross-origin");
  iframe.setAttribute("tabindex", "-1");
  iframe.setAttribute("aria-hidden", "true");
};

const concealProviderHost = (host) => {
  if (!host) return;
  host.classList.add("jukebox-provider-host");
  host.setAttribute("aria-hidden", "true");
  Object.assign(host.style, {
    position: "fixed",
    top: "0px",
    left: "-10000px",
    width: "320px",
    height: "200px",
    overflow: "hidden",
    opacity: "0",
    pointerEvents: "none",
  });
};

const loadYouTubeApi = () => {
  if (typeof window === "undefined")
    return Promise.reject(new Error("youtube_browser_required"));
  if (window.YT?.Player) return Promise.resolve(window.YT);
  if (apiPromise) return apiPromise;
  apiPromise = new Promise((resolve, reject) => {
    const previous = window.onYouTubeIframeAPIReady;
    const timer = window.setTimeout(() => {
      apiPromise = null;
      window.onYouTubeIframeAPIReady = previous;
      reject(new Error("youtube_api_timeout"));
    }, 15000);
    window.onYouTubeIframeAPIReady = () => {
      window.clearTimeout(timer);
      previous?.();
      resolve(window.YT);
    };
    const script = document.createElement("script");
    script.src = "https://www.youtube.com/iframe_api";
    script.async = true;
    script.onerror = () => {
      window.clearTimeout(timer);
      apiPromise = null;
      window.onYouTubeIframeAPIReady = previous;
      reject(new Error("youtube_api_unavailable"));
    };
    document.head.appendChild(script);
  });
  return apiPromise;
};

export class YouTubeAudioProvider {
  constructor(track, options = {}) {
    this.track = track;
    this.hostId = options.hostId || "jukebox-provider-host";
    this.onEnded = options.onEnded || (() => {});
    this.onBlocked = options.onBlocked || (() => {});
    this.onPlaying = options.onPlaying || (() => {});
    this.onError = options.onError || (() => {});
    this.player = null;
    this.ready = null;
    this.loop = false;
    this.intendedPlaying = false;
    this.playerState = null;
    this.playerStates = null;
    this.recoveryTimer = null;
    this.visibilityHandler = () => {
      if (document.visibilityState === "visible") this.scheduleRecovery(0);
    };
  }

  async load(position = 0) {
    const YT = await loadYouTubeApi();
    this.playerStates = YT.PlayerState;
    document.addEventListener("visibilitychange", this.visibilityHandler);
    let host = document.getElementById(this.hostId);
    if (!host) {
      host = document.createElement("div");
      host.id = this.hostId;
      document.body.appendChild(host);
    }
    concealProviderHost(host);
    const slot = document.createElement("div");
    host.replaceChildren(slot);
    this.ready = new Promise((resolve, reject) => {
      let ready = false;
      this.player = new YT.Player(slot, {
        width: "320",
        height: "200",
        videoId: this.track.providerReference,
        playerVars: {
          controls: 1,
          playsinline: 1,
          origin: window.location.origin,
        },
        events: {
          onReady: (event) => {
            ready = true;
            allowAutoplay(event.target?.getIframe?.());
            resolve();
          },
          onAutoplayBlocked: () => this.onBlocked(),
          onError: (event) => {
            const error = playerError(event.data);
            this.intendedPlaying = false;
            this.onError(error);
            if (!ready) reject(error);
          },
          onStateChange: (event) => {
            this.playerState = event.data;
            if (event.data === YT.PlayerState.PLAYING) {
              this.clearRecovery();
              this.onPlaying();
            } else if (event.data === YT.PlayerState.ENDED && this.loop) {
              this.player?.seekTo(0, true);
              this.player?.playVideo();
            } else if (event.data === YT.PlayerState.ENDED) {
              this.intendedPlaying = false;
              this.onEnded();
            } else if (event.data === YT.PlayerState.PAUSED) {
              this.scheduleRecovery();
            }
          },
        },
      });
      allowAutoplay(this.player?.getIframe?.());
    });
    await this.ready;
    if (position > 0) this.seek(position);
  }

  async play(position) {
    await this.ready;
    this.intendedPlaying = true;
    if (Number.isFinite(position)) this.seek(position);
    this.player?.playVideo();
  }

  pause() {
    this.intendedPlaying = false;
    this.clearRecovery();
    this.player?.pauseVideo();
  }

  stop() {
    this.intendedPlaying = false;
    this.clearRecovery();
    this.player?.stopVideo();
  }

  seek(position) {
    this.player?.seekTo(Math.max(0, Number(position) || 0), true);
  }

  setVolume(volume, muted = false) {
    if (muted) this.player?.mute();
    else {
      this.player?.unMute();
      this.player?.setVolume(
        Math.round(Math.max(0, Math.min(1, volume)) * 100),
      );
    }
  }

  setLoop(loop) {
    this.loop = Boolean(loop);
  }

  currentTime() {
    return Number(this.player?.getCurrentTime?.()) || 0;
  }

  duration() {
    return Math.max(0, Number(this.player?.getDuration?.()) || 0);
  }

  ensurePlaying() {
    if (!this.intendedPlaying || !this.player) return false;
    const state = Number(this.player.getPlayerState?.() ?? this.playerState);
    if (
      state === this.playerStates?.PLAYING ||
      state === this.playerStates?.BUFFERING
    ) {
      return true;
    }
    this.player.playVideo();
    return false;
  }

  scheduleRecovery(delay = 350) {
    if (!this.intendedPlaying || this.recoveryTimer !== null) return;
    this.recoveryTimer = window.setTimeout(() => {
      this.recoveryTimer = null;
      this.ensurePlaying();
    }, delay);
  }

  clearRecovery() {
    if (this.recoveryTimer !== null) window.clearTimeout(this.recoveryTimer);
    this.recoveryTimer = null;
  }

  playbackRate(rate) {
    this.player?.setPlaybackRate?.(rate);
  }

  destroy() {
    this.intendedPlaying = false;
    this.clearRecovery();
    document.removeEventListener("visibilitychange", this.visibilityHandler);
    this.player?.destroy?.();
    this.player = null;
    document.getElementById(this.hostId)?.remove();
  }
}
