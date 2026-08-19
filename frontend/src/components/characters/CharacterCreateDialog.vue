<template>
  <div
    class="character-dialog-backdrop"
    role="presentation"
    @click.self="close"
  >
    <section
      class="character-create-dialog"
      role="dialog"
      aria-modal="true"
      aria-labelledby="character-create-title"
    >
      <header>
        <div>
          <p class="character-eyebrow">{{ $t("characters.create.eyebrow") }}</p>
          <h2 id="character-create-title">
            {{ $t("characters.create.title") }}
          </h2>
        </div>
        <button
          type="button"
          :disabled="busy"
          :aria-label="$t('characters.actions.close')"
          @click="close"
        >
          ×
        </button>
      </header>
      <p>{{ $t("characters.create.description") }}</p>
      <p v-if="error" class="character-create-error" role="alert">
        {{ error }}
      </p>
      <form @submit.prevent="submit">
        <label>
          <span>{{ $t("characters.fields.name") }}</span>
          <input
            v-model.trim="name"
            required
            minlength="2"
            maxlength="150"
            autofocus
          />
        </label>
        <label>
          <span>{{ $t("characters.fields.game") }}</span>
          <select v-model="gameKey" required>
            <option disabled value="">
              {{ $t("characters.create.chooseGame") }}
            </option>
            <option
              v-for="game in games"
              :key="keyFor(game)"
              :value="keyFor(game)"
            >
              {{ game.systemName }} — {{ game.universeName }}
            </option>
          </select>
        </label>
        <p v-if="!games.length" class="character-create-error">
          {{ $t("characters.create.noGames") }}
        </p>
        <footer>
          <button type="button" :disabled="busy" @click="close">
            {{ $t("characters.actions.cancel") }}
          </button>
          <button
            class="character-primary-action"
            type="submit"
            :disabled="busy || !games.length"
          >
            {{
              busy
                ? $t("characters.loading.creating")
                : $t("characters.actions.create")
            }}
          </button>
        </footer>
      </form>
    </section>
  </div>
</template>

<script>
export default {
  name: "CharacterCreateDialog",
  emits: ["close", "create"],
  props: {
    games: { type: Array, default: () => [] },
    busy: { type: Boolean, default: false },
    error: { type: String, default: "" },
  },
  data: () => ({ name: "", gameKey: "" }),
  watch: {
    games: {
      immediate: true,
      handler(games) {
        if (!this.gameKey && games.length) this.gameKey = this.keyFor(games[0]);
      },
    },
  },
  methods: {
    keyFor(game) {
      return `${Number(game.systemId)}:${Number(game.universeId)}`;
    },
    close() {
      if (!this.busy) this.$emit("close");
    },
    submit() {
      const [systemId, universeId] = this.gameKey.split(":").map(Number);
      if (this.name.length < 2 || !systemId || !universeId) return;
      this.$emit("create", {
        name: this.name,
        systemId,
        universeId,
        avatarUrl: "",
        data: {
          details: {},
          attributes: { actual: {}, skills: [], talents: [] },
        },
      });
    },
  },
};
</script>

<style scoped>
.character-dialog-backdrop {
  position: fixed;
  z-index: 1000;
  inset: 0;
  display: grid;
  place-items: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.76);
  backdrop-filter: blur(6px);
}
.character-create-dialog {
  width: min(520px, 100%);
  border: 1px solid rgba(216, 183, 120, 0.42);
  border-radius: 18px;
  padding: 24px;
  color: #f5eddf;
  background: #17100c;
  box-shadow: 0 26px 80px #000;
}
.character-create-dialog header {
  display: flex;
  align-items: start;
  justify-content: space-between;
  gap: 20px;
}
.character-create-dialog h2 {
  margin: 3px 0;
  font-size: 1.8rem;
}
.character-create-dialog > p {
  color: #bbaa93;
}
.character-create-dialog form,
.character-create-dialog label {
  display: grid;
  gap: 8px;
}
.character-create-dialog form {
  gap: 18px;
  margin-top: 22px;
}
.character-create-dialog input,
.character-create-dialog select {
  border: 1px solid rgba(216, 183, 120, 0.32);
  border-radius: 9px;
  padding: 11px 12px;
  color: #f5eddf;
  background: #090706;
  font: inherit;
}
.character-create-dialog button {
  border: 1px solid rgba(216, 183, 120, 0.32);
  border-radius: 999px;
  padding: 9px 16px;
  color: #f5eddf;
  background: transparent;
  cursor: pointer;
  font: inherit;
  font-weight: 700;
}
.character-create-dialog header button {
  border: 0;
  padding: 0 8px;
  font-size: 1.7rem;
}
.character-create-dialog footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}
.character-create-dialog .character-primary-action {
  border: 0;
  color: #21140b;
  background: linear-gradient(135deg, #efd196, #ba663d);
}
.character-create-error {
  color: #ffd4c8 !important;
}
</style>
