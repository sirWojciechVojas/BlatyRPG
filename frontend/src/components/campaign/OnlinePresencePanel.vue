<template>
  <section class="campaign-panel">
    <h2>{{ $t("campaignLobby.online.title") }}</h2>
    <p class="connection-state">
      {{ $t(`campaignLobby.connection.${knownStatus}`) }}
      <button v-if="manualRetryAvailable" type="button" @click="$emit('retry')">
        {{ $t("campaignLobby.actions.retry") }}
      </button>
    </p>
    <ul class="campaign-list presence-list">
      <li v-for="member in members" :key="member.userId">
        <span class="presence-dot" :class="{ online: member.isOnline }" />
        <span>{{ member.username || member.email }}</span>
        <small>
          {{
            member.isOnline
              ? $t("campaignLobby.online.online")
              : $t("campaignLobby.online.offline")
          }}
        </small>
      </li>
    </ul>
  </section>
</template>

<script>
export default {
  name: "OnlinePresencePanel",
  props: {
    members: { type: Array, default: () => [] },
    status: { type: String, default: "idle" },
    manualRetryAvailable: { type: Boolean, default: false },
  },
  emits: ["retry"],
  computed: {
    knownStatus() {
      return [
        "ready",
        "syncing",
        "reconnecting",
        "auth_failed",
        "forbidden",
        "exhausted",
        "disconnected",
      ].includes(this.status)
        ? this.status
        : "connecting";
    },
  },
};
</script>
