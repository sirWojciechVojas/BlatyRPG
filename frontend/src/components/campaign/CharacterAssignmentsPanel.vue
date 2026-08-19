<template>
  <section class="campaign-panel">
    <h2>{{ $t("campaignLobby.characters.assignments") }}</h2>
    <label>
      <span>{{ $t("campaignLobby.characters.character") }}</span>
      <select v-model.number="characterId" @change="load">
        <option :value="null" disabled>
          {{ $t("campaignLobby.characters.choose") }}
        </option>
        <option v-for="item in characters" :key="item.id" :value="item.id">
          {{ item.name }}
        </option>
      </select>
    </label>
    <template v-if="characterId">
      <label>
        <span>{{ $t("campaignLobby.characters.visibility") }}</span>
        <select v-model="visibility" :disabled="busy">
          <option v-for="level in levels" :key="level" :value="level">
            {{ $t(`campaignLobby.access.${level}`) }}
          </option>
        </select>
      </label>
      <button
        type="button"
        :disabled="busy"
        @click="$emit('visibility', { characterId, visibility })"
      >
        {{ $t("campaignLobby.actions.save") }}
      </button>
      <form class="campaign-invite-form" @submit.prevent="grant">
        <select v-model.number="userId" required :disabled="busy">
          <option :value="null" disabled>
            {{ $t("campaignLobby.characters.member") }}
          </option>
          <option
            v-for="member in members"
            :key="member.userId"
            :value="member.userId"
          >
            {{ member.username || member.email }}
          </option>
        </select>
        <select v-model="accessLevel" :disabled="busy">
          <option v-for="level in levels" :key="level" :value="level">
            {{ $t(`campaignLobby.access.${level}`) }}
          </option>
        </select>
        <button type="submit" :disabled="busy">
          {{ $t("campaignLobby.actions.grant") }}
        </button>
        <button type="button" :disabled="busy || !userId" @click="owner">
          {{ $t("campaignLobby.actions.primaryOwner") }}
        </button>
      </form>
      <ul class="campaign-list">
        <li v-for="permission in permissions" :key="permission.id">
          <span>{{ permission.user.username }}</span>
          <small>{{
            $t(`campaignLobby.access.${permission.accessLevel}`)
          }}</small>
        </li>
      </ul>
    </template>
  </section>
</template>

<script>
export default {
  name: "CharacterAssignmentsPanel",
  props: {
    characters: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
    permissions: { type: Array, default: () => [] },
    busy: { type: Boolean, default: false },
  },
  emits: ["load", "access", "owner", "visibility"],
  data: () => ({
    levels: ["none", "limited", "observer", "owner"],
    characterId: null,
    userId: null,
    accessLevel: "owner",
    visibility: "limited",
  }),
  methods: {
    load() {
      const character = this.characters.find(
        (item) => item.id === this.characterId,
      );
      this.visibility = character?.visibility || "limited";
      this.$emit("load", this.characterId);
    },
    grant() {
      this.$emit("access", {
        characterId: this.characterId,
        userId: this.userId,
        accessLevel: this.accessLevel,
      });
    },
    owner() {
      this.$emit("owner", {
        characterId: this.characterId,
        userId: this.userId,
        primary: true,
      });
    },
  },
};
</script>
