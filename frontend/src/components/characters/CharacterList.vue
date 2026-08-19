<template>
  <aside class="character-list" :aria-label="$t('characters.list.title')">
    <div class="character-list-heading">
      <div>
        <p class="character-eyebrow">{{ $t("characters.list.eyebrow") }}</p>
        <h2>{{ $t("characters.list.title") }}</h2>
      </div>
      <span>{{ characters.length }}</span>
    </div>
    <div v-if="loading" class="character-list-state" aria-live="polite">
      {{ $t("characters.loading.list") }}
    </div>
    <div v-else-if="!characters.length" class="character-list-state">
      <strong>{{ $t("characters.empty.title") }}</strong>
      <p>{{ $t("characters.empty.description") }}</p>
    </div>
    <button
      v-for="character in characters"
      v-else
      :key="character.id"
      class="character-list-item"
      :class="{ selected: character.id === selectedId }"
      type="button"
      @click="$emit('select', character.id)"
    >
      <img :src="avatar(character)" alt="" />
      <span>
        <strong>{{ character.name }}</strong>
        <small>
          {{ details(character) }}
          <template v-if="character.isLegacyUnassigned">
            · {{ $t("characters.list.legacy") }}
          </template>
        </small>
      </span>
      <span class="character-edit-indicator" aria-hidden="true">
        {{ character.capabilities.canEdit ? "✦" : "◇" }}
      </span>
    </button>
  </aside>
</template>

<script>
import { resolveCharacterAvatar } from "@/lib/trade/characterAvatar";

export default {
  name: "CharacterList",
  emits: ["select"],
  props: {
    characters: { type: Array, default: () => [] },
    selectedId: { type: Number, default: null },
    loading: { type: Boolean, default: false },
  },
  methods: {
    avatar(character) {
      return resolveCharacterAvatar(character, character.name);
    },
    details(character) {
      const data = character.data?.details || {};
      return (
        [data.race, data.profession, data.class]
          .filter(Boolean)
          .map(String)
          .join(" · ") || this.$t("characters.list.noDetails")
      );
    },
  },
};
</script>
