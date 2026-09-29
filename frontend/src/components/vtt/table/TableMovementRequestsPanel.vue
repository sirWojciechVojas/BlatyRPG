<template>
  <section class="movement-requests">
    <dl class="movement-requests__facts">
      <div>
        <dt>{{ $t("vtt.table.notifications.connection") }}</dt>
        <dd>
          {{ $t(`vtt.table.notifications.connectionStates.${knownStatus}`) }}
        </dd>
      </div>
      <div>
        <dt>{{ $t("vtt.table.notifications.online") }}</dt>
        <dd>{{ onlineCount }}/{{ members.length }}</dd>
      </div>
      <div v-if="canManage">
        <dt>{{ $t("vtt.table.notifications.invites") }}</dt>
        <dd>{{ pendingInvitations.length }}</dd>
      </div>
    </dl>

    <section class="movement-requests__presence">
      <header>
        <div>
          <small>{{ $t("vtt.table.notifications.presence.kicker") }}</small>
          <h3>{{ $t("vtt.table.notifications.presence.title") }}</h3>
        </div>
        <button
          v-if="manualRetryAvailable"
          type="button"
          class="scene-button"
          @click="$emit('retry')"
        >
          {{ $t("vtt.table.notifications.presence.retry") }}
        </button>
      </header>
      <ul v-if="members.length" class="movement-requests__members">
        <li v-for="member in members" :key="member.userId">
          <span class="presence-dot" :class="{ online: member.isOnline }" />
          <span>{{ member.username || member.email }}</span>
          <small>
            {{
              $t(
                member.isOnline
                  ? "vtt.table.notifications.presence.online"
                  : "vtt.table.notifications.presence.offline",
              )
            }}
          </small>
        </li>
      </ul>
      <p v-else class="movement-requests__empty">
        {{ $t("vtt.table.notifications.presence.empty") }}
      </p>
    </section>

    <header>
      <div>
        <small>{{ $t("vtt.table.notifications.movement.kicker") }}</small>
        <h3>{{ $t("vtt.table.notifications.movement.title") }}</h3>
      </div>
      <b>{{ pending.length }}</b>
    </header>

    <div v-if="requests.length" class="movement-requests__list">
      <article
        v-for="request in requests"
        :key="request.id"
        :class="`movement-request--${request.status}`"
      >
        <div class="movement-request__identity">
          <span>{{ initials(request.tokenName) }}</span>
          <div>
            <strong>{{ request.tokenName }}</strong>
            <small>{{ request.requesterName }}</small>
          </div>
          <em>{{ statusLabel(request.status) }}</em>
        </div>
        <dl>
          <div>
            <dt>{{ $t("vtt.table.notifications.movement.cost") }}</dt>
            <dd>{{ request.cost }} PR</dd>
          </div>
          <div>
            <dt>{{ $t("vtt.table.notifications.movement.before") }}</dt>
            <dd>{{ request.spent }} / {{ request.range }}</dd>
          </div>
          <div>
            <dt>{{ $t("vtt.table.notifications.movement.after") }}</dt>
            <dd>{{ request.spent + request.cost }} / {{ request.range }}</dd>
          </div>
        </dl>
        <div
          v-if="canResolve && request.status === 'pending'"
          class="movement-request__actions"
        >
          <button
            type="button"
            class="scene-button scene-button--danger"
            :disabled="busy"
            @click="resolve(request.id, 'reject')"
          >
            {{ $t("vtt.table.notifications.movement.reject") }}
          </button>
          <button
            type="button"
            class="scene-button scene-button--primary"
            :disabled="busy"
            @click="resolve(request.id, 'approve')"
          >
            {{ $t("vtt.table.notifications.movement.approve") }}
          </button>
        </div>
        <p v-else-if="request.status === 'pending'">
          {{ $t("vtt.table.notifications.movement.waiting") }}
        </p>
      </article>
    </div>
    <p v-else class="movement-requests__empty">
      {{ $t("vtt.table.notifications.movement.empty") }}
    </p>
  </section>
</template>

<script>
export default {
  name: "TableMovementRequestsPanel",
  props: {
    requests: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
    invitations: { type: Array, default: () => [] },
    realtimeStatus: { type: String, default: "disconnected" },
    manualRetryAvailable: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    canResolve: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
  },
  emits: ["resolve", "retry"],
  computed: {
    pending() {
      return this.requests.filter((request) => request.status === "pending");
    },
    onlineCount() {
      return this.members.filter((member) => member.isOnline).length;
    },
    pendingInvitations() {
      return this.invitations.filter(
        (invitation) => invitation.status === "pending",
      );
    },
    knownStatus() {
      return [
        "ready",
        "syncing",
        "reconnecting",
        "auth_failed",
        "forbidden",
        "exhausted",
        "disconnected",
      ].includes(this.realtimeStatus)
        ? this.realtimeStatus
        : "connecting";
    },
  },
  methods: {
    initials(name) {
      return String(name || "?")
        .split(/\s+/u)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toLocaleUpperCase();
    },
    statusLabel(status) {
      return this.$t(`vtt.table.notifications.movement.status.${status}`);
    },
    resolve(requestId, decision) {
      this.$emit("resolve", { requestId, decision });
    },
  },
};
</script>

<style scoped>
.movement-requests {
  display: grid;
  align-content: start;
  gap: 10px;
  padding: 10px;
}
.movement-requests__facts,
article dl {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 4px;
  margin: 0;
}
.movement-requests__facts div,
article dl div {
  padding: 5px 6px;
  background: #17110d;
  border: 1px solid #3f3024;
}
dt {
  color: #9f8d74;
  font-size: 8px;
  text-transform: uppercase;
}
dd {
  margin: 1px 0 0;
  color: #f0dbb6;
  font-weight: 800;
  font-size: 11px;
}
header,
.movement-request__identity,
.movement-request__actions {
  display: flex;
  align-items: center;
  gap: 7px;
}
header {
  justify-content: space-between;
  border-bottom: 1px solid #463424;
  padding-bottom: 6px;
}
header small {
  color: #b7823e;
  font-size: 8px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}
h3 {
  margin: 1px 0 0;
  color: #f2ddbb;
  font-size: 12px;
}
header b {
  min-width: 24px;
  padding: 3px;
  color: #1b1008;
  text-align: center;
  background: #e7ac54;
  border-radius: 12px;
}
.movement-requests__list {
  display: grid;
  gap: 6px;
  overflow: auto;
}
.movement-requests__presence {
  display: grid;
  gap: 6px;
}
.movement-requests__members {
  display: grid;
  gap: 1px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.movement-requests__members li {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 7px;
  padding: 6px;
  color: #f0dbb6;
  background: #17110d;
  border: 1px solid #3f3024;
}
.movement-requests__members li > span:not(.presence-dot) {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.movement-requests__members small {
  color: #a89577;
  font-size: 9px;
}
article {
  display: grid;
  gap: 7px;
  padding: 8px;
  background: #110d0a;
  border: 1px solid #594027;
  border-left: 3px solid #d59b45;
  border-radius: 4px;
}
.movement-request__identity > span {
  display: grid;
  width: 30px;
  height: 30px;
  place-items: center;
  color: #f3d9a9;
  background: #2b1c11;
  border: 1px solid #8a5f2d;
  border-radius: 50%;
  font-weight: 900;
}
.movement-request__identity div {
  display: grid;
  flex: 1;
}
.movement-request__identity strong {
  color: #f4e3c5;
  font-size: 11px;
}
.movement-request__identity small {
  color: #a89577;
  font-size: 9px;
}
.movement-request__identity em {
  color: #e8b660;
  font-size: 8px;
  font-style: normal;
  text-transform: uppercase;
}
.movement-request__actions {
  justify-content: flex-end;
}
.movement-request__actions button {
  min-height: 28px;
}
article p,
.movement-requests__empty {
  margin: 0;
  color: #aa9678;
  font-size: 10px;
}
.movement-request--approved {
  border-left-color: #52b878;
}
.movement-request--rejected,
.movement-request--expired {
  border-left-color: #b85b4a;
  opacity: 0.72;
}
</style>
