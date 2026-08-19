<!-- Responsibility: BaseShopLayout shop interface component. -->
<template>
  <div
    class="shop-base-layout d-flex flex-column h-100 overflow-hidden"
    :class="layoutClasses"
  >
    <!-- Shared shell: header, filters, content, summary and actions are slot-driven. -->
    <div
      ref="headerTrigger"
      class="input-group input-group-md flex-shrink-0"
      :class="{ 'shop-base-layout__header-trigger': hasPopupToolbar }"
      :role="hasPopupToolbar ? 'button' : undefined"
      :tabindex="hasPopupToolbar ? 0 : undefined"
      :aria-expanded="hasPopupToolbar ? String(toolbarOpen) : undefined"
      :aria-haspopup="hasPopupToolbar ? 'dialog' : undefined"
      @click="toggleToolbar"
      @keydown.enter.prevent="toggleToolbar"
      @keydown.space.prevent="toggleToolbar"
    >
      <slot name="header" />
      <span
        v-if="hasPopupToolbar"
        class="shop-base-layout__toolbar-chevron"
        aria-hidden="true"
        >⌄</span
      >
    </div>
    <slot name="filters" />
    <div :class="[shellClass, 'd-flex flex-column flex-grow-1']">
      <div
        v-if="hasToolbar && toolbarInsideShell"
        v-show="toolbarOpen"
        ref="toolbarPopup"
        class="input-group input-group-md flex-shrink-0 shop-base-layout__toolbar"
        role="dialog"
        @click.stop
      >
        <slot name="toolbar" />
      </div>
      <template v-if="hasToolbar && !toolbarInsideShell">
        <slot name="toolbar" />
      </template>
      <div :id="contentId" :class="contentClass">
        <slot />
      </div>
    </div>
    <slot name="summary" />
    <div
      v-if="hasActions"
      class="shop-base-layout__actions-bar"
      :class="{
        'shop-base-layout__actions-bar--with-notification': hasNotificationZone,
      }"
    >
      <div class="shop-base-layout__actions-inner">
        <div class="shop-base-layout__actions-frame">
          <slot name="actions" />
          <ShopNotificationBar
            v-if="hasNotificationZone"
            :zone="notificationZone"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  computed,
  onBeforeUnmount,
  onMounted,
  ref,
  useSlots,
  watch,
} from "vue";
import ShopNotificationBar from "@/components/shop/common/ShopNotificationBar.vue";

const props = defineProps({
  layoutClass: {
    type: [String, Array, Object],
    default: "",
  },
  shellClass: {
    type: String,
    default: "trade-list-shell",
  },
  contentId: {
    type: String,
    default: "",
  },
  contentClass: {
    type: String,
    default: "outline w-100 text-light bg-transparent trade-list",
  },
  toolbarInsideShell: {
    type: Boolean,
    default: false,
  },
  showToolbar: {
    type: Boolean,
    default: true,
  },
  notificationZone: {
    type: String,
    default: "",
  },
});

const slots = useSlots();
const headerTrigger = ref(null);
const toolbarPopup = ref(null);
const toolbarOpen = ref(false);
const hasToolbar = computed(() => props.showToolbar && Boolean(slots.toolbar));
const hasPopupToolbar = computed(
  () => hasToolbar.value && props.toolbarInsideShell,
);
const hasFilters = computed(() => Boolean(slots.filters));
const hasSummary = computed(() => Boolean(slots.summary));
const hasActions = computed(() => Boolean(slots.actions));
const hasNotificationZone = computed(() => Boolean(props.notificationZone));
const layoutClasses = computed(() => [
  props.layoutClass,
  {
    "shop-base-layout--with-actions": hasActions.value,
    "shop-base-layout--with-filters": hasFilters.value,
    "shop-base-layout--with-summary": hasSummary.value,
    "shop-base-layout--with-toolbar": hasToolbar.value,
  },
]);

const toggleToolbar = () => {
  if (hasPopupToolbar.value) {
    toolbarOpen.value = !toolbarOpen.value;
  }
};

const closeToolbarOnOutsidePointer = (event) => {
  if (
    !toolbarOpen.value ||
    headerTrigger.value?.contains(event.target) ||
    toolbarPopup.value?.contains(event.target)
  ) {
    return;
  }
  toolbarOpen.value = false;
};

const closeToolbarOnEscape = (event) => {
  if (event.key !== "Escape" || !toolbarOpen.value) {
    return;
  }
  toolbarOpen.value = false;
  headerTrigger.value?.focus();
};

watch(hasPopupToolbar, (enabled) => {
  if (!enabled) {
    toolbarOpen.value = false;
  }
});

onMounted(() => {
  document.addEventListener("pointerdown", closeToolbarOnOutsidePointer);
  document.addEventListener("keydown", closeToolbarOnEscape);
});

onBeforeUnmount(() => {
  document.removeEventListener("pointerdown", closeToolbarOnOutsidePointer);
  document.removeEventListener("keydown", closeToolbarOnEscape);
});
</script>
