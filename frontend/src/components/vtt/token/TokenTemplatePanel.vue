<template>
  <section class="token-template-panel">
    <header>
      <div>
        <small>{{ $t("vtt.tokenTemplates.kicker") }}</small>
        <h2>{{ $t("vtt.tokenTemplates.title") }}</h2>
      </div>
      <button
        type="button"
        class="token-template-panel__refresh"
        :disabled="phase === 'loading'"
        :title="$t('vtt.tokenTemplates.refresh')"
        @click="load"
      >
        ↻
      </button>
    </header>

    <label class="token-template-panel__search">
      <span aria-hidden="true">⌕</span>
      <input
        v-model.trim="query"
        type="search"
        :placeholder="$t('vtt.tokenTemplates.search')"
      />
    </label>

    <p v-if="phase === 'loading'" class="token-template-panel__state">
      {{ $t("vtt.tokenTemplates.loading") }}
    </p>
    <div v-else-if="error" class="token-template-panel__state" role="alert">
      <p>{{ $t("vtt.tokenTemplates.error") }}</p>
      <button type="button" @click="load">
        {{ $t("vtt.tokenTemplates.retry") }}
      </button>
    </div>
    <p v-else-if="!filtered.length" class="token-template-panel__state">
      {{
        query
          ? $t("vtt.tokenTemplates.noResults")
          : $t("vtt.tokenTemplates.empty")
      }}
    </p>
    <div v-else class="token-template-panel__grid">
      <article
        v-for="template in filtered"
        :key="template.id"
        class="token-template-card"
        draggable="true"
        @dragstart="startDrag($event, template)"
        @dragend="endDrag"
      >
        <div class="token-template-card__image">
          <AuthenticatedImage
            v-if="template.imageUrl"
            :src="template.imageUrl"
            alt=""
            draggable="false"
          />
          <span v-else>{{ initials(template.name) }}</span>
          <small>{{ sizeLabel(template) }}</small>
        </div>
        <div class="token-template-card__copy">
          <strong>{{ template.name }}</strong>
          <span>{{
            $t(`vtt.token.dispositions.${template.disposition}`)
          }}</span>
        </div>
        <button
          type="button"
          :disabled="busyId === template.id"
          @click="$emit('place', template)"
        >
          {{
            busyId === template.id
              ? $t("vtt.tokenTemplates.placing")
              : $t("vtt.tokenTemplates.placeCenter")
          }}
        </button>
      </article>
    </div>
  </section>
</template>

<script>
import AuthenticatedImage from "@/components/ui/AuthenticatedImage.vue";
import { tokenTemplateApiClient } from "@/lib/vtt/tokenTemplateApiClient";
import {
  beginTokenTemplateDrag,
  endTokenTemplateDrag,
} from "@/lib/vtt/tokenTemplateDragSession";

export default {
  name: "TokenTemplatePanel",
  components: { AuthenticatedImage },
  props: {
    campaignId: { type: [Number, String], required: true },
    busyId: { type: [Number, String], default: null },
  },
  emits: ["place"],
  data: () => ({ items: [], query: "", phase: "idle", error: null }),
  computed: {
    filtered() {
      const needle = this.query.toLocaleLowerCase();
      if (!needle) return this.items;
      return this.items.filter((item) =>
        item.name.toLocaleLowerCase().includes(needle),
      );
    },
  },
  watch: { campaignId: { immediate: true, handler: "load" } },
  beforeUnmount() {
    endTokenTemplateDrag();
  },
  methods: {
    async load() {
      if (!this.campaignId) return;
      this.phase = "loading";
      this.error = null;
      try {
        const result = await tokenTemplateApiClient.list(this.campaignId);
        this.items = result.items;
        this.phase = "ready";
      } catch (error) {
        this.error = error;
        this.phase = "error";
      }
    },
    startDrag(event, template) {
      beginTokenTemplateDrag(event.dataTransfer, template);
    },
    endDrag() {
      endTokenTemplateDrag();
    },
    initials(name) {
      return String(name || "?")
        .split(/\s+/u)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toLocaleUpperCase();
    },
    sizeLabel(template) {
      return `${template.widthCells} × ${template.heightCells}`;
    },
  },
};
</script>

<style scoped>
.token-template-panel {
  display: grid;
  gap: 14px;
  height: 100%;
  min-height: 0;
  padding: 16px;
  color: #eee9dc;
  overflow: auto;
}
.token-template-panel > header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.token-template-panel h2 {
  margin: 2px 0 0;
  font-size: 1.08rem;
}
.token-template-panel small {
  color: #9d978c;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.token-template-panel__refresh,
.token-template-panel button {
  border: 1px solid rgba(196, 168, 111, 0.35);
  border-radius: 8px;
  background: rgba(196, 168, 111, 0.1);
  color: inherit;
  padding: 7px 10px;
}
.token-template-panel__search {
  display: flex;
  align-items: center;
  gap: 8px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 9px;
  padding: 8px 11px;
  background: rgba(0, 0, 0, 0.22);
}
.token-template-panel__search input {
  min-width: 0;
  width: 100%;
  border: 0;
  outline: 0;
  background: transparent;
  color: inherit;
}
.token-template-panel__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 12px;
  align-content: start;
}
.token-template-card {
  display: grid;
  gap: 9px;
  padding: 10px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  background: rgba(8, 10, 13, 0.58);
  cursor: grab;
}
.token-template-card:active {
  cursor: grabbing;
}
.token-template-card__image {
  position: relative;
  display: grid;
  place-items: center;
  aspect-ratio: 1;
  overflow: hidden;
  border-radius: 9px;
  background: #17191d;
  color: #c4a86f;
  font-size: 1.6rem;
}
.token-template-card__image img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.token-template-card__image small {
  position: absolute;
  right: 6px;
  bottom: 6px;
  padding: 3px 5px;
  border-radius: 5px;
  background: rgba(0, 0, 0, 0.76);
  color: #fff;
  font-size: 0.66rem;
  letter-spacing: 0;
  text-transform: none;
}
.token-template-card__copy {
  display: grid;
  min-width: 0;
}
.token-template-card__copy strong {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.token-template-card__copy span {
  color: #9d978c;
  font-size: 0.78rem;
}
.token-template-card button {
  cursor: pointer;
}
.token-template-card button:disabled {
  opacity: 0.55;
  cursor: wait;
}
.token-template-panel__state {
  margin: auto;
  color: #aaa49a;
  text-align: center;
}
@media (max-width: 560px) {
  .token-template-panel {
    padding: 12px;
  }
  .token-template-panel__grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
