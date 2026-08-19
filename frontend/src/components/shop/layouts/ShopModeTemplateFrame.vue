<!-- Responsibility: ShopModeTemplateFrame shop interface component. -->
<template>
  <BaseShopLayout
    :layout-class="layoutClass"
    :shell-class="shellClass"
    :content-id="contentId"
    :content-class="contentClass"
    :notification-zone="notificationZone"
  >
    <template #header>
      <slot name="header">
        <div class="input-group input-group-md">
          <input
            type="text"
            class="form-control bg-transparent text-light ih-95"
            :value="headerValue"
            readonly
            disabled
          />
        </div>
      </slot>
    </template>

    <template #filters>
      <slot name="filters" />
    </template>

    <template #default>
      <div class="trade-form shop-mode-template-frame p-0">
        <div
          v-if="hasTitlebar"
          class="d-flex align-items-start justify-content-between mb-2 shop-mode-template-frame__titlebar"
          :class="{
            'shop-mode-template-frame__titlebar--actions-only': hasOnlyActions,
          }"
        >
          <div
            v-if="hasTitleContent"
            class="shop-mode-template-frame__titlecopy"
          >
            <div
              v-if="title || slots.title"
              class="text-light shop-mode-template-frame__title"
            >
              <slot name="title">{{ title }}</slot>
            </div>
            <div
              v-if="slots.titleMeta"
              class="shop-mode-template-frame__titlemeta"
            >
              <slot name="titleMeta" />
            </div>
          </div>

          <div
            v-if="slots.titleActions"
            class="d-flex gap-2 shop-mode-template-frame__actions"
          >
            <slot name="titleActions" />
          </div>
        </div>

        <div
          class="row col-md-12 trade-form-shell shop-mode-template-frame__body"
        >
          <slot />
        </div>
      </div>
    </template>

    <template #summary>
      <slot name="summary" />
    </template>

    <template #actions>
      <slot name="actions" />
    </template>
  </BaseShopLayout>
</template>

<script setup>
import { computed, useSlots } from "vue";
import BaseShopLayout from "@/components/shop/layouts/BaseShopLayout.vue";

const props = defineProps({
  shellClass: {
    type: String,
    default: "trade-list-shell shop-mode-shell",
  },
  layoutClass: {
    type: [String, Array, Object],
    default: "",
  },
  contentId: {
    type: String,
    default: "",
  },
  contentClass: {
    type: String,
    default: "shop-mode-content trade-panel-content",
  },
  headerValue: {
    type: String,
    default: "",
  },
  title: {
    type: String,
    default: "",
  },
  notificationZone: {
    type: String,
    default: "",
  },
});

const slots = useSlots();
const hasTitleContent = computed(() =>
  Boolean(props.title || slots.title || slots.titleMeta),
);
const hasOnlyActions = computed(
  () => !hasTitleContent.value && Boolean(slots.titleActions),
);
const hasTitlebar = computed(() =>
  Boolean(hasTitleContent.value || slots.titleActions),
);
</script>
