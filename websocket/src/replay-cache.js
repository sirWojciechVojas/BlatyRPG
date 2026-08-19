export class ReplayCache {
  constructor({ maxEntries = 10000, now = Date.now } = {}) {
    this.entries = new Map();
    this.maxEntries = maxEntries;
    this.now = now;
  }

  consume(jti, expiresAtMs) {
    this.prune();
    if (this.entries.has(jti)) return false;
    if (this.entries.size >= this.maxEntries) {
      throw new Error("ticket_replay_cache_full");
    }
    this.entries.set(jti, expiresAtMs);
    return true;
  }

  prune() {
    const now = this.now();
    for (const [jti, expiresAt] of this.entries) {
      if (expiresAt <= now) this.entries.delete(jti);
    }
  }

  get size() {
    this.prune();
    return this.entries.size;
  }
}
