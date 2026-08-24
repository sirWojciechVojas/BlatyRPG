<template>
  <section class="table-character-panel">
    <aside class="table-character-panel__browser">
      <header>
        <input
          v-model.trim="query"
          type="search"
          :placeholder="$t('characters.list.title')"
          :aria-label="$t('characters.list.title')"
        />
        <button
          type="button"
          :disabled="loading"
          :title="$t('characters.actions.refresh')"
          @click="loadCharacters"
        >
          ↻
        </button>
      </header>
      <p v-if="loading && !characters.length" role="status">
        {{ $t("characters.loading.list") }}
      </p>
      <p v-else-if="!filteredCharacters.length">
        {{ $t("characters.empty.title") }}
      </p>
      <button
        v-for="character in filteredCharacters"
        v-else
        :key="character.id"
        type="button"
        :class="{ selected: character.id === selectedId }"
        :draggable="canCreateToken"
        :title="canCreateToken ? $t('vtt.token.dragActor') : ''"
        @dragstart="dragCharacter($event, character)"
        @click="selectCharacter(character.id)"
      >
        <img :src="avatar(character)" alt="" />
        <span>
          <strong>{{ character.name }}</strong>
          <small>{{ details(character) }}</small>
        </span>
      </button>
    </aside>

    <div class="table-character-panel__sheet">
      <p v-if="notice" class="table-character-panel__notice" role="status">
        {{ notice }}
      </p>
      <p v-if="loadError" class="table-character-panel__error" role="alert">
        {{ loadError }}
      </p>
      <p v-if="loadingSheet" class="table-character-panel__loading">
        {{ $t("characters.loading.sheet") }}
      </p>
      <CharacterSheetEditor
        v-else
        :character="selectedCharacter"
        :saving="saving || deleting"
        :error="saveError"
        @save="saveCharacter"
        @delete="deleteCharacter"
      />
    </div>
  </section>
</template>

<script>
import CharacterSheetEditor from "@/components/characters/CharacterSheetEditor.vue";
import { characterApiClient } from "@/lib/character/characterApiClient";
import { characterErrorKey } from "@/lib/character/characterErrorKey";
import { resolveCharacterAvatar } from "@/lib/trade/characterAvatar";
import { TOKEN_ACTOR_MIME } from "@/lib/vtt/tokenDrop";

export default {
  name: "TableCharacterPanel",
  components: { CharacterSheetEditor },
  props: {
    campaignId: { type: [Number, String], required: true },
    canCreateToken: { type: Boolean, default: false },
  },
  emits: ["changed"],
  data: () => ({
    characters: [],
    selectedId: null,
    selectedCharacter: null,
    query: "",
    loading: false,
    loadingSheet: false,
    saving: false,
    deleting: false,
    loadError: "",
    saveError: "",
    notice: "",
    listRequestSequence: 0,
    sheetRequestSequence: 0,
  }),
  computed: {
    filteredCharacters() {
      const query = this.query.toLocaleLowerCase();
      return query
        ? this.characters.filter((item) =>
            item.name.toLocaleLowerCase().includes(query),
          )
        : this.characters;
    },
  },
  watch: {
    campaignId: "resetAndLoad",
  },
  mounted() {
    this.loadCharacters();
  },
  beforeUnmount() {
    this.listRequestSequence += 1;
    this.sheetRequestSequence += 1;
  },
  methods: {
    resetAndLoad() {
      this.listRequestSequence += 1;
      this.sheetRequestSequence += 1;
      this.characters = [];
      this.selectedId = null;
      this.selectedCharacter = null;
      this.loadCharacters();
    },
    async loadCharacters() {
      const sequence = ++this.listRequestSequence;
      this.loading = true;
      this.loadError = "";
      try {
        const result = await characterApiClient.list(this.campaignId);
        if (sequence !== this.listRequestSequence) return;
        this.characters = result.characters;
        const nextId = this.selectedId || this.characters[0]?.id;
        if (nextId) await this.selectCharacter(nextId);
      } catch (error) {
        if (sequence === this.listRequestSequence) {
          this.loadError = this.$t(characterErrorKey(error, "load"));
        }
      } finally {
        if (sequence === this.listRequestSequence) this.loading = false;
      }
    },
    async selectCharacter(id) {
      const characterId = Number(id);
      const sequence = ++this.sheetRequestSequence;
      this.selectedId = characterId;
      this.loadingSheet = true;
      this.saveError = "";
      try {
        const character = await characterApiClient.get(
          this.campaignId,
          characterId,
        );
        if (sequence !== this.sheetRequestSequence) return;
        this.selectedCharacter = character;
        this.replaceCharacter(character);
      } catch (error) {
        if (sequence === this.sheetRequestSequence) {
          this.saveError = this.$t(characterErrorKey(error, "load"));
        }
      } finally {
        if (sequence === this.sheetRequestSequence) this.loadingSheet = false;
      }
    },
    async saveCharacter(draft) {
      if (!this.selectedId || this.saving) return;
      this.saving = true;
      this.saveError = "";
      try {
        const character = await characterApiClient.update(
          this.campaignId,
          this.selectedId,
          draft,
        );
        this.selectedCharacter = character;
        this.replaceCharacter(character);
        this.notice = this.$t("characters.notices.saved");
        this.$emit("changed", character);
      } catch (error) {
        this.saveError = this.$t(characterErrorKey(error, "save"));
      } finally {
        this.saving = false;
      }
    },
    async deleteCharacter() {
      const character = this.selectedCharacter;
      if (!character?.id || this.deleting) return;
      const question = this.$t("characters.delete.confirm", {
        name: character.name,
      });
      if (!window.confirm(question)) return;
      this.deleting = true;
      try {
        await characterApiClient.delete(this.campaignId, character.id);
        this.characters = this.characters.filter(
          (item) => item.id !== character.id,
        );
        this.selectedCharacter = null;
        this.selectedId = null;
        this.notice = this.$t("characters.notices.deleted");
        this.$emit("changed", character);
        if (this.characters[0])
          await this.selectCharacter(this.characters[0].id);
      } catch (error) {
        this.saveError = this.$t(characterErrorKey(error, "delete"));
      } finally {
        this.deleting = false;
      }
    },
    replaceCharacter(character) {
      const index = this.characters.findIndex(
        (item) => item.id === character.id,
      );
      if (index < 0) this.characters.push(character);
      else this.characters.splice(index, 1, character);
    },
    avatar(character) {
      return resolveCharacterAvatar(character, character.name);
    },
    details(character) {
      const details = character.data?.details || {};
      return [details.race, details.profession, details.class]
        .filter(Boolean)
        .join(" · ");
    },
    dragCharacter(event, character) {
      if (!this.canCreateToken) {
        event.preventDefault();
        return;
      }
      event.dataTransfer.effectAllowed = "copy";
      event.dataTransfer.setData(
        TOKEN_ACTOR_MIME,
        JSON.stringify({
          id: character.id,
          name: character.name,
          imageUrl: this.avatar(character),
        }),
      );
    },
  },
};
</script>
