<template>
  <section class="campaign-panel">
    <h2>{{ $t("campaignLobby.members.title") }}</h2>
    <form class="campaign-invite-form" @submit.prevent="invite">
      <input
        v-model.trim="draft.identifier"
        :placeholder="$t('campaignLobby.invites.identifier')"
        required
        maxlength="255"
        :disabled="busy"
      />
      <select v-model="draft.role" :disabled="busy">
        <option v-for="role in roles" :key="role" :value="role">
          {{ $t(`campaignLobby.roles.${role}`) }}
        </option>
      </select>
      <input
        v-model.trim="draft.message"
        :placeholder="$t('campaignLobby.invites.message')"
        maxlength="500"
        :disabled="busy"
      />
      <button type="submit" :disabled="busy">
        {{ $t("campaignLobby.actions.invite") }}
      </button>
    </form>
    <ul class="campaign-list">
      <li v-for="member in members" :key="member.userId">
        <span>{{ member.username || member.email }}</span>
        <select
          :value="member.role"
          :disabled="busy || member.userId === ownerUserId"
          @change="
            $emit('change-role', {
              userId: member.userId,
              role: $event.target.value,
            })
          "
        >
          <option v-for="role in roles" :key="role" :value="role">
            {{ $t(`campaignLobby.roles.${role}`) }}
          </option>
        </select>
        <button
          type="button"
          :disabled="busy || member.userId === ownerUserId"
          @click="$emit('remove', member.userId)"
        >
          {{ $t("campaignLobby.actions.remove") }}
        </button>
      </li>
    </ul>
    <h3>{{ $t("campaignLobby.invites.pending") }}</h3>
    <ul class="campaign-list">
      <li v-for="item in pendingInvitations" :key="item.id">
        <span>{{ item.invitee.username || item.invitee.email }}</span>
        <small>{{ $t(`campaignLobby.roles.${item.role}`) }}</small>
        <button
          type="button"
          :disabled="busy"
          @click="$emit('revoke', item.id)"
        >
          {{ $t("campaignLobby.actions.revoke") }}
        </button>
      </li>
    </ul>
  </section>
</template>

<script>
export default {
  name: "CampaignMembersPanel",
  props: {
    members: { type: Array, default: () => [] },
    invitations: { type: Array, default: () => [] },
    ownerUserId: { type: [Number, String], default: null },
    busy: { type: Boolean, default: false },
  },
  emits: ["invite", "change-role", "remove", "revoke"],
  data: () => ({
    roles: ["gm", "assistant", "player", "observer"],
    draft: { identifier: "", role: "player", message: "" },
  }),
  computed: {
    pendingInvitations() {
      return this.invitations.filter((item) => item.status === "pending");
    },
  },
  methods: {
    invite() {
      this.$emit("invite", { ...this.draft });
      this.draft.identifier = "";
      this.draft.message = "";
    },
  },
};
</script>
