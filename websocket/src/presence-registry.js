const publicUser = (entry, online = true, status = null) => ({
  userId: entry.userId,
  displayName: entry.displayName,
  campaignRole: entry.campaignRole,
  online,
  status: status || (entry.connections.size ? "online" : "reconnecting"),
  connectionCount: entry.connections.size,
  connectedAt: entry.connectedAt,
  updatedAt: entry.updatedAt,
});

const transition = (campaignId, entry, change, overrides = {}) => ({
  campaignId,
  userId: entry.userId,
  change,
  user: { ...publicUser(entry), ...overrides },
});

export class PresenceRegistry {
  constructor({ graceMs, onExpired, now = Date.now, setTimer = setTimeout, clearTimer = clearTimeout }) {
    this.campaigns = new Map();
    this.graceMs = graceMs;
    this.onExpired = onExpired;
    this.now = now;
    this.setTimer = setTimer;
    this.clearTimer = clearTimer;
  }

  users(campaignId) {
    if (!this.campaigns.has(campaignId)) this.campaigns.set(campaignId, new Map());
    return this.campaigns.get(campaignId);
  }

  add(session) {
    const users = this.users(session.campaignId);
    let entry = users.get(session.userId);
    const timestamp = new Date(this.now()).toISOString();
    if (!entry) {
      entry = {
        userId: session.userId,
        displayName: session.displayName,
        campaignRole: session.campaignRole,
        capabilities: session.capabilities,
        connections: new Set(),
        connectedAt: timestamp,
        updatedAt: timestamp,
        timer: null,
      };
      users.set(session.userId, entry);
    }
    const reconnecting = entry.timer !== null;
    if (entry.timer !== null) this.clearTimer(entry.timer.handle);
    entry.timer = null;
    const previousCount = entry.connections.size;
    entry.connections.add(session.id);
    entry.displayName = session.displayName;
    entry.campaignRole = session.campaignRole;
    entry.capabilities = session.capabilities;
    entry.updatedAt = timestamp;
    const change = reconnecting ? "reconnected" : previousCount ? "connection_added" : "connected";
    return transition(session.campaignId, entry, change);
  }

  replace(previous, next) {
    const users = this.users(next.campaignId);
    const entry = users.get(next.userId);
    if (!entry) return this.add(next);
    if (entry.timer !== null) this.clearTimer(entry.timer.handle);
    entry.timer = null;
    entry.connections.delete(previous.id);
    entry.connections.add(next.id);
    entry.displayName = next.displayName;
    entry.campaignRole = next.campaignRole;
    entry.capabilities = next.capabilities;
    entry.updatedAt = new Date(this.now()).toISOString();
    return transition(next.campaignId, entry, "reconnected");
  }

  remove(session, { grace = true, reason = "disconnected" } = {}) {
    const users = this.campaigns.get(session.campaignId);
    const entry = users?.get(session.userId);
    if (!entry || !entry.connections.delete(session.id)) return null;
    entry.updatedAt = new Date(this.now()).toISOString();
    if (entry.connections.size) {
      return transition(session.campaignId, entry, "connection_removed");
    }
    if (grace && this.graceMs > 0) {
      const marker = { handle: null };
      marker.handle = this.setTimer(() => {
        if (entry.timer !== marker) return;
        entry.timer = null;
        entry.updatedAt = new Date(this.now()).toISOString();
        users.delete(entry.userId);
        if (!users.size) this.campaigns.delete(session.campaignId);
        this.onExpired(
          transition(session.campaignId, entry, "disconnected", {
            online: false,
            status: "offline",
            connectionCount: 0,
          }),
        );
      }, this.graceMs);
      marker.handle?.unref?.();
      entry.timer = marker;
      return transition(session.campaignId, entry, "reconnecting", {
        online: true,
        status: "reconnecting",
        connectionCount: 0,
        reason,
      });
    }
    users.delete(entry.userId);
    if (!users.size) this.campaigns.delete(session.campaignId);
    return transition(session.campaignId, entry, reason === "left" ? "left" : "disconnected", {
      online: false,
      status: "offline",
      connectionCount: 0,
    });
  }

  snapshot(campaignId) {
    return [...(this.campaigns.get(campaignId)?.values() || [])]
      .map((entry) => publicUser(entry))
      .sort((left, right) => left.userId - right.userId);
  }

  stop() {
    for (const users of this.campaigns.values()) {
      for (const entry of users.values()) {
        if (entry.timer !== null) this.clearTimer(entry.timer.handle);
      }
    }
    this.campaigns.clear();
  }
}
