import { audioMixerService } from "@/services/audioMixerService";
import { normalizeWallSoundConfig, wallSoundGain } from "@/lib/vtt/wallSound";

const uniqueId = (prefix) =>
  (typeof crypto !== "undefined" && crypto.randomUUID?.()) ||
  `${prefix}-${Date.now()}-${Math.random().toString(16).slice(2)}`;

const safeLegacyUrl = (value) => {
  const url = String(value || "").trim();
  return /^\/(?!\/)/u.test(url) || /^https?:\/\//iu.test(url) ? url : "";
};

class WallAudioRuntime {
  constructor() {
    this.context = null;
    this.frame = null;
    this.loops = new Map();
    this.projectedLoops = new Map();
    this.legacyPlayers = new Set();
  }

  setContext(context) {
    this.context = context?.scene ? context : null;
    this.schedule();
  }

  schedule() {
    if (this.frame !== null) return;
    const scheduleFrame =
      (typeof window !== "undefined" && window.requestAnimationFrame) ||
      ((callback) => setTimeout(callback, 16));
    this.frame = scheduleFrame(() => {
      this.frame = null;
      void this.refreshLoops();
    });
  }

  track(trackId) {
    return (this.context?.tracks || []).find(
      (track) => Number(track.id) === Number(trackId),
    );
  }

  gain(wall, rule) {
    return wallSoundGain(
      wall,
      rule,
      this.context?.listeners || [],
      this.context?.scene || {},
    );
  }

  async refreshLoops() {
    const wanted = new Set();
    if (this.context?.scene) {
      for (const wall of this.context.walls || []) {
        const config = normalizeWallSoundConfig(wall.soundConfig);
        for (const rule of config.rules) {
          if (
            !rule.enabled ||
            rule.trigger !== "proximityLoop" ||
            !rule.trackId
          )
            continue;
          const track = this.track(rule.trackId);
          const gain = this.gain(wall, rule);
          const playbackId = `wall-loop:${this.context.scene.id}:${wall.id}:${rule.id}:${track?.id || 0}`;
          if (!track?.url || gain <= 0.0001) continue;
          wanted.add(playbackId);
          const active = this.loops.get(playbackId);
          if (active?.trackId === Number(track.id)) {
            active.gain = gain;
            audioMixerService.setEffectVolume(playbackId, gain, 100);
            continue;
          }
          if (active) {
            audioMixerService.stopEffect(playbackId, rule.fadeOutMs);
            this.loops.delete(playbackId);
          }
          const entry = {
            trackId: Number(track.id),
            gain,
            pending: true,
            fadeOutMs: rule.fadeOutMs,
          };
          this.loops.set(playbackId, entry);
          void audioMixerService
            .unlock()
            .then(() => {
              if (this.loops.get(playbackId) !== entry) return false;
              return audioMixerService.playEffect({
                playbackId,
                track,
                volume: gain,
                loop: true,
                fadeInMs: rule.fadeInMs,
                fadeOutMs: rule.fadeOutMs,
              });
            })
            .then((played) => {
              if (this.loops.get(playbackId) !== entry) return;
              if (!played) this.loops.delete(playbackId);
              else entry.pending = false;
            })
            .catch(() => {
              if (this.loops.get(playbackId) === entry) {
                this.loops.delete(playbackId);
              }
            });
        }
      }
    }
    for (const [playbackId, active] of this.loops) {
      if (wanted.has(playbackId)) continue;
      audioMixerService.stopEffect(playbackId, active.fadeOutMs || 250);
      this.loops.delete(playbackId);
    }
  }

  playLegacy(rule, gain) {
    const url = safeLegacyUrl(rule.legacyUrl);
    if (!url || typeof Audio === "undefined" || gain <= 0.0001) return false;
    const player = new Audio(url);
    player.volume = Math.min(1, Math.max(0, gain));
    this.legacyPlayers.add(player);
    const release = () => this.legacyPlayers.delete(player);
    player.addEventListener?.("ended", release, { once: true });
    player.addEventListener?.("error", release, { once: true });
    Promise.resolve(player.play()).catch(release);
    return true;
  }

  playCue(wall, cue) {
    if (!this.context?.scene || !wall) return false;
    const rules = normalizeWallSoundConfig(wall.soundConfig).rules.filter(
      (rule) => rule.enabled && rule.trigger === cue,
    );
    let handled = false;
    for (const rule of rules) {
      const gain = this.gain(wall, rule);
      if (gain <= 0.0001) continue;
      const track = this.track(rule.trackId);
      if (!track?.url) {
        handled = this.playLegacy(rule, gain) || handled;
        continue;
      }
      handled = true;
      const playbackId = uniqueId(`wall-cue:${wall.id}:${rule.id}`);
      void audioMixerService
        .unlock()
        .then(() =>
          audioMixerService.playEffect({
            playbackId,
            track,
            volume: gain,
            loop: false,
            fadeInMs: rule.fadeInMs,
            fadeOutMs: rule.fadeOutMs,
          }),
        )
        .catch(() => {});
    }
    return handled;
  }

  applyProjectedState(payload = {}) {
    if (
      !this.context?.scene ||
      Number(payload.sceneId) !== Number(this.context.scene.id)
    ) {
      return;
    }
    const wanted = new Set();
    for (const item of payload.activeLoops || []) {
      const playbackId = String(item?.playbackId || "");
      if (!playbackId || !item.audio?.url) continue;
      wanted.add(playbackId);
      const active = this.projectedLoops.get(playbackId);
      if (active) {
        active.fadeOutMs = Number(item.fadeOutMs) || 0;
        audioMixerService.setEffectVolume(playbackId, item.volume, 100);
        continue;
      }
      const entry = {
        fadeOutMs: Number(item.fadeOutMs) || 0,
      };
      this.projectedLoops.set(playbackId, entry);
      void audioMixerService
        .unlock()
        .then(() => {
          if (this.projectedLoops.get(playbackId) !== entry) return false;
          return audioMixerService.playEffect({
            playbackId,
            track: item.audio,
            volume: Number(item.volume) || 0,
            loop: true,
            fadeInMs: Number(item.fadeInMs) || 0,
            fadeOutMs: Number(item.fadeOutMs) || 0,
          });
        })
        .then((played) => {
          if (this.projectedLoops.get(playbackId) === entry && !played) {
            this.projectedLoops.delete(playbackId);
          }
        })
        .catch(() => {
          if (this.projectedLoops.get(playbackId) === entry) {
            this.projectedLoops.delete(playbackId);
          }
        });
    }
    for (const [playbackId, active] of this.projectedLoops) {
      if (wanted.has(playbackId)) continue;
      audioMixerService.stopEffect(playbackId, active.fadeOutMs || 250);
      this.projectedLoops.delete(playbackId);
    }
  }

  playProjectedCue(payload = {}) {
    if (
      !this.context?.scene ||
      Number(payload.sceneId) !== Number(this.context.scene.id)
    ) {
      return;
    }
    for (const item of payload.items || []) {
      if (!item?.playbackId || !item.audio?.url) continue;
      void audioMixerService
        .unlock()
        .then(() =>
          audioMixerService.playEffect({
            playbackId: item.playbackId,
            track: item.audio,
            volume: Number(item.volume) || 0,
            loop: false,
            fadeInMs: Number(item.fadeInMs) || 0,
            fadeOutMs: Number(item.fadeOutMs) || 0,
          }),
        )
        .catch(() => {});
    }
  }

  clear() {
    const cancelFrame =
      (typeof window !== "undefined" && window.cancelAnimationFrame) ||
      clearTimeout;
    if (this.frame !== null) cancelFrame(this.frame);
    this.frame = null;
    this.context = null;
    for (const [playbackId, active] of this.loops) {
      audioMixerService.stopEffect(playbackId, active.fadeOutMs || 150);
    }
    this.loops.clear();
    for (const [playbackId, active] of this.projectedLoops) {
      audioMixerService.stopEffect(playbackId, active.fadeOutMs || 150);
    }
    this.projectedLoops.clear();
    for (const player of this.legacyPlayers) {
      try {
        player.pause();
      } catch (_error) {
        // A released legacy media element needs no further cleanup.
      }
    }
    this.legacyPlayers.clear();
  }
}

export const wallAudioRuntime = new WallAudioRuntime();
