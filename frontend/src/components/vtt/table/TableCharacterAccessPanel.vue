<template>
  <details v-if="character" class="table-character-access">
    <summary>
      <span class="table-character-access__title">
        {{ $t("vtt.table.characters.access.settings") }}
      </span>
      <span class="table-character-access__summary">
        <span>
          {{
            $t(
              publicVisible
                ? "vtt.table.characters.access.everyoneCanView"
                : "vtt.table.characters.access.everyoneHidden",
            )
          }}
        </span>
        <span aria-hidden="true">·</span>
        <span>{{ controllersSummary }}</span>
      </span>
    </summary>

    <div class="table-character-access__settings">
      <label class="table-character-access__setting">
        <span class="table-character-access__copy">
          <strong>{{
            $t("vtt.table.characters.access.visibilityForEveryone")
          }}</strong>
          <small>{{
            $t("vtt.table.characters.access.visibilityForEveryoneHint")
          }}</small>
        </span>
        <input
          type="checkbox"
          role="switch"
          :checked="publicVisible"
          :disabled="busy"
          @change="setPublicVisibility($event.target.checked)"
        />
      </label>

      <div class="table-character-access__setting">
        <span class="table-character-access__copy">
          <strong>{{ $t("vtt.table.characters.access.controlledBy") }}</strong>
          <small>{{
            $t("vtt.table.characters.access.controlledByHint")
          }}</small>
        </span>
        <details class="table-character-access__controllers">
          <summary>{{ controllersSummary }}</summary>
          <div>
            <label v-for="member in members" :key="member.userId">
              <input
                type="checkbox"
                :checked="isController(member.userId)"
                :disabled="
                  busy ||
                  (isPrimaryOwner(member.userId) && isController(member.userId))
                "
                @change="setController(member.userId, $event.target.checked)"
              />
              <span>{{ memberName(member) }}</span>
              <small v-if="isPrimaryOwner(member.userId)">
                {{ $t("vtt.table.characters.access.primaryOwnerBadge") }}
              </small>
            </label>
          </div>
        </details>
      </div>
    </div>

    <p v-if="error" class="table-character-access__error" role="alert">
      {{ error }}
    </p>
  </details>
</template>

<script>
export default {
  name: "TableCharacterAccessPanel",
  props: {
    characterId: { type: [Number, String], default: null },
    members: { type: Array, default: () => [] },
  },
  emits: ["changed"],
  data: () => ({
    publicVisible: false,
    loading: false,
    error: "",
  }),
  computed: {
    context() {
      return this.$store.state.campaignContext || {};
    },
    character() {
      return (
        (this.context.characters || []).find(
          (item) => Number(item.id) === Number(this.characterId),
        ) || null
      );
    },
    busy() {
      return this.loading || Boolean(this.context.pendingRequests);
    },
    permissions() {
      return (
        this.context.characterPermissions?.[Number(this.characterId)] || []
      );
    },
    controllerIds() {
      const ids = new Set();
      const primary = Number(this.character?.ownerUserId);
      if (primary) ids.add(primary);
      this.permissions.forEach((permission) => {
        if (permission.accessLevel !== "owner") return;
        const id = Number(permission.user?.id ?? permission.userId);
        if (id) ids.add(id);
      });
      return ids;
    },
    controllersSummary() {
      const count = this.controllerIds.size;
      return count
        ? this.$t("vtt.table.characters.access.controllersSummary", { count })
        : this.$t("vtt.table.characters.access.ownerMissing");
    },
  },
  watch: {
    characterId: {
      immediate: true,
      handler() {
        this.syncCharacter();
        this.loadPermissions();
      },
    },
    character: {
      deep: true,
      handler() {
        this.syncCharacter();
      },
    },
  },
  methods: {
    message(error) {
      if (error?.network)
        return this.$t("vtt.table.characters.access.errors.network");
      if (error?.status === 403)
        return this.$t("vtt.table.characters.access.errors.forbidden");
      if (error?.status === 429)
        return this.$t("vtt.table.characters.access.errors.rateLimited");
      return this.$t("vtt.table.characters.access.errors.generic");
    },
    syncCharacter() {
      this.publicVisible = this.character?.visibility === "observer";
      this.error = "";
    },
    async run(action, payload) {
      this.error = "";
      try {
        return await this.$store.dispatch(`campaignContext/${action}`, payload);
      } catch (error) {
        this.error = this.message(error);
        return null;
      }
    },
    async setPublicVisibility(visible) {
      const previous = this.publicVisible;
      this.publicVisible = Boolean(visible);
      const result = await this.run("updateCharacterVisibility", {
        characterId: Number(this.characterId),
        visibility: this.publicVisible ? "observer" : "none",
      });
      if (result === null) this.publicVisible = previous;
      else this.$emit("changed");
    },
    async loadPermissions() {
      if (!Number(this.characterId)) return;
      this.loading = true;
      await this.run("loadCharacterPermissions", Number(this.characterId));
      this.loading = false;
    },
    async setController(userId, enabled) {
      if (!Number(userId)) return;
      const result = await this.run("setCharacterAccess", {
        characterId: Number(this.characterId),
        userId: Number(userId),
        accessLevel: enabled ? "owner" : "none",
      });
      if (result !== null) this.$emit("changed");
    },
    isController(userId) {
      return this.controllerIds.has(Number(userId));
    },
    isPrimaryOwner(userId) {
      return Number(this.character?.ownerUserId) === Number(userId);
    },
    memberName(member) {
      return member.username || member.email || `#${member.userId}`;
    },
  },
};
</script>

<style scoped src="./table-character-access.css"></style>
