<template>
  <section v-if="compact" class="table-shop-panel__launcher">
    <p>{{ $t("vtt.table.shop.description") }}</p>
    <button type="button" class="scene-button" @click="$emit('promote')">
      {{ $t("vtt.table.shop.openWindow") }}
    </button>
  </section>
  <section v-else class="table-shop-panel">
    <p v-if="error" class="table-shop-panel__state" role="alert">
      {{ error }}
      <button type="button" class="scene-button" @click="initialize">
        {{ $t("vtt.scene.actions.retry") }}
      </button>
    </p>
    <p v-else-if="!ready" class="table-shop-panel__state" role="status">
      {{ $t("ui.loading") }}
    </p>
    <ShopGmWorkspace v-else />
  </section>
</template>

<script>
import { defineAsyncComponent } from "vue";
import { ensureShopStoreModule } from "@/store/modules/loadShopModule";

const ShopGmWorkspace = defineAsyncComponent(
  () => import(/* webpackChunkName: "shop-gm" */ "@/views/ShopGmWorkspace.vue"),
);

export default {
  name: "TableShopPanel",
  components: { ShopGmWorkspace },
  props: { compact: { type: Boolean, default: false } },
  emits: ["promote"],
  data: () => ({ loading: false, ready: false, error: "" }),
  mounted() {
    if (!this.compact) this.initialize();
  },
  methods: {
    async initialize() {
      this.loading = true;
      this.error = "";
      try {
        await ensureShopStoreModule(this.$store);
        this.ready = true;
      } catch (_error) {
        this.error = this.$t("vtt.table.shop.loadError");
      } finally {
        this.loading = false;
      }
    },
  },
};
</script>
