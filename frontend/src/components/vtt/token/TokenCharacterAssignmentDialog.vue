<template>
  <Teleport to="body">
    <div
      class="token-character-dialog__backdrop"
      @mousedown.self="$emit('close')"
    >
      <section
        class="token-character-dialog"
        role="dialog"
        aria-modal="true"
        :aria-label="$t('vtt.token.assignment.title')"
      >
        <header>
          <div>
            <small>{{ $t("vtt.token.assignment.kicker") }}</small>
            <h2>{{ $t("vtt.token.assignment.title") }}</h2>
            <p>{{ token.name }}</p>
          </div>
          <button
            type="button"
            :title="$t('vtt.token.assignment.close')"
            @click="$emit('close')"
          >
            ×
          </button>
        </header>

        <nav>
          <button
            type="button"
            :class="{ active: mode === 'existing' }"
            @click="mode = 'existing'"
          >
            {{ $t("vtt.token.assignment.existing") }}
          </button>
          <button
            type="button"
            :class="{ active: mode === 'new' }"
            @click="mode = 'new'"
          >
            {{ $t("vtt.token.assignment.new") }}
          </button>
        </nav>

        <p v-if="error" class="token-character-dialog__error" role="alert">
          {{ error }}
        </p>
        <p
          v-if="createdCharacterId"
          class="token-character-dialog__notice"
          role="status"
        >
          {{ $t("vtt.token.assignment.createdRetry") }}
        </p>

        <div
          v-if="mode === 'existing'"
          class="token-character-dialog__existing"
        >
          <input
            v-model.trim="query"
            type="search"
            :placeholder="$t('vtt.token.assignment.search')"
          />
          <div class="token-character-dialog__characters">
            <button
              v-for="character in filteredCharacters"
              :key="character.id"
              type="button"
              :class="{ active: Number(selectedId) === Number(character.id) }"
              @click="selectedId = character.id"
            >
              <AuthenticatedImage
                v-if="avatar(character)"
                :src="avatar(character)"
                alt=""
                draggable="false"
              />
              <span v-else>{{ initials(character.name) }}</span>
              <strong>{{ character.name }}</strong>
              <small>#{{ character.id }}</small>
            </button>
          </div>
          <p
            v-if="!filteredCharacters.length"
            class="token-character-dialog__empty"
          >
            {{ $t("vtt.token.assignment.noCharacters") }}
          </p>
        </div>

        <label v-else class="token-character-dialog__new">
          <span>{{ $t("vtt.token.assignment.name") }}</span>
          <input v-model.trim="newName" maxlength="150" required />
          <small>{{ $t("vtt.token.assignment.newHint") }}</small>
        </label>

        <footer>
          <button
            v-if="token.characterId"
            type="button"
            class="danger"
            :disabled="busy"
            @click="$emit('detach')"
          >
            {{ $t("vtt.token.assignment.detach") }}
          </button>
          <span />
          <button type="button" :disabled="busy" @click="$emit('close')">
            {{ $t("vtt.token.assignment.cancel") }}
          </button>
          <button
            type="button"
            class="primary"
            :disabled="busy || !canSubmit"
            @click="submit"
          >
            {{ busy ? $t("vtt.token.assignment.saving") : submitLabel }}
          </button>
        </footer>
      </section>
    </div>
  </Teleport>
</template>

<script>
import AuthenticatedImage from "@/components/ui/AuthenticatedImage.vue";

export default {
  name: "TokenCharacterAssignmentDialog",
  components: { AuthenticatedImage },
  props: {
    token: { type: Object, required: true },
    characters: { type: Array, default: () => [] },
    busy: { type: Boolean, default: false },
    error: { type: String, default: "" },
    createdCharacterId: { type: [Number, String], default: null },
  },
  emits: ["close", "assign", "create", "detach"],
  data() {
    return {
      mode: "existing",
      query: "",
      selectedId: this.token.characterId || this.createdCharacterId || null,
      newName: this.token.name || "",
    };
  },
  computed: {
    filteredCharacters() {
      const needle = this.query.toLocaleLowerCase();
      return this.characters.filter(
        (character) =>
          !needle || character.name.toLocaleLowerCase().includes(needle),
      );
    },
    canSubmit() {
      return this.mode === "existing"
        ? Boolean(this.selectedId)
        : Boolean(this.newName);
    },
    submitLabel() {
      return this.mode === "existing"
        ? this.$t("vtt.token.assignment.assign")
        : this.$t("vtt.token.assignment.createAndAssign");
    },
  },
  watch: {
    createdCharacterId(value) {
      if (!value) return;
      this.mode = "existing";
      this.selectedId = value;
    },
  },
  mounted() {
    window.addEventListener("keydown", this.onKeydown);
  },
  beforeUnmount() {
    window.removeEventListener("keydown", this.onKeydown);
  },
  methods: {
    avatar(character) {
      return String(character.avatarUrl || character.assets?.portrait || "");
    },
    initials(name) {
      return String(name || "?")
        .split(/\s+/u)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toLocaleUpperCase();
    },
    submit() {
      if (!this.canSubmit || this.busy) return;
      if (this.mode === "existing")
        this.$emit("assign", Number(this.selectedId));
      else this.$emit("create", this.newName);
    },
    onKeydown(event) {
      if (event.key === "Escape") this.$emit("close");
    },
  },
};
</script>

<style scoped>
.token-character-dialog__backdrop {
  position: fixed;
  inset: 0;
  z-index: 1400;
  display: grid;
  place-items: center;
  padding: 18px;
  background: rgba(3, 4, 6, 0.76);
  backdrop-filter: blur(4px);
}
.token-character-dialog {
  display: grid;
  gap: 14px;
  width: min(620px, 100%);
  max-height: min(760px, calc(100vh - 36px));
  overflow: auto;
  border: 1px solid rgba(196, 168, 111, 0.38);
  border-radius: 14px;
  padding: 18px;
  background: #121418;
  color: #eee9dc;
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.62);
}
.token-character-dialog header,
.token-character-dialog footer,
.token-character-dialog nav {
  display: flex;
  align-items: center;
  gap: 9px;
}
.token-character-dialog header {
  justify-content: space-between;
}
.token-character-dialog h2 {
  margin: 2px 0;
  font-size: 1.2rem;
}
.token-character-dialog p {
  margin: 0;
}
.token-character-dialog header small {
  color: #c4a86f;
  text-transform: uppercase;
  letter-spacing: 0.1em;
}
.token-character-dialog button,
.token-character-dialog input {
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.05);
  color: inherit;
  padding: 9px 11px;
}
.token-character-dialog nav button {
  flex: 1;
}
.token-character-dialog button.active,
.token-character-dialog button.primary {
  border-color: #c4a86f;
  background: rgba(196, 168, 111, 0.18);
}
.token-character-dialog button.danger {
  color: #ef918a;
}
.token-character-dialog__existing {
  display: grid;
  gap: 10px;
  min-height: 180px;
}
.token-character-dialog__characters {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
.token-character-dialog__characters button {
  display: grid;
  grid-template-columns: 42px 1fr auto;
  align-items: center;
  gap: 9px;
  text-align: left;
}
.token-character-dialog__characters img,
.token-character-dialog__characters button > span {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  object-fit: cover;
  display: grid;
  place-items: center;
  background: #24272c;
}
.token-character-dialog__characters strong {
  overflow: hidden;
  text-overflow: ellipsis;
}
.token-character-dialog__characters small {
  color: #99948b;
}
.token-character-dialog__new {
  display: grid;
  gap: 8px;
}
.token-character-dialog__new small,
.token-character-dialog__empty {
  color: #99948b;
}
.token-character-dialog__error {
  color: #ff9d94;
}
.token-character-dialog__notice {
  color: #e8ce91;
}
.token-character-dialog footer {
  justify-content: flex-end;
}
.token-character-dialog footer span {
  flex: 1;
}
@media (max-width: 560px) {
  .token-character-dialog__characters {
    grid-template-columns: 1fr;
  }
  .token-character-dialog footer {
    flex-wrap: wrap;
  }
  .token-character-dialog footer span {
    display: none;
  }
}
</style>
