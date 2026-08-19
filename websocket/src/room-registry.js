const instanceKey = (session) => `${session.userId}:${session.clientInstanceId}`;

const createRoom = (campaignId) => ({
  campaignId,
  sequence: 0,
  connections: new Map(),
  instances: new Map(),
});

export class RoomRegistry {
  constructor() {
    this.rooms = new Map();
  }

  ensure(campaignId) {
    if (!this.rooms.has(campaignId)) this.rooms.set(campaignId, createRoom(campaignId));
    return this.rooms.get(campaignId);
  }

  add(session) {
    const room = this.ensure(session.campaignId);
    const key = instanceKey(session);
    const replacedId = room.instances.get(key);
    const replaced = replacedId ? room.connections.get(replacedId) || null : null;
    if (replaced) room.connections.delete(replaced.id);
    room.connections.set(session.id, session);
    room.instances.set(key, session.id);
    return replaced;
  }

  remove(session) {
    const room = this.rooms.get(session.campaignId);
    if (!room || room.connections.get(session.id) !== session) return false;
    room.connections.delete(session.id);
    const key = instanceKey(session);
    if (room.instances.get(key) === session.id) room.instances.delete(key);
    return true;
  }

  sessions(campaignId) {
    return [...(this.rooms.get(campaignId)?.connections.values() || [])];
  }

  currentSequence(campaignId) {
    return this.rooms.get(campaignId)?.sequence || 0;
  }

  nextSequence(campaignId) {
    const room = this.ensure(campaignId);
    room.sequence += 1;
    return room.sequence;
  }

  connectionCount(campaignId) {
    return this.rooms.get(campaignId)?.connections.size || 0;
  }
}
