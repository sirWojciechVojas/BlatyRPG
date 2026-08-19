<!-- Responsibility: ShopNotificationBar shop interface component. -->
<template>
  <div
    class="shop-notification-slot"
    :class="[
      `shop-notification-slot--${zone}`,
      { 'shop-notification-slot--stacked': visibleNotifications.length > 1 },
    ]"
  >
    <TransitionGroup
      name="shop-notification-fade"
      tag="div"
      class="shop-notification-stack"
    >
      <section
        v-for="notification in visibleNotifications"
        :key="notification.id"
        class="shop-notification"
        :class="`shop-notification--${notification.type}`"
        :role="roleFor(notification)"
        aria-live="polite"
      >
        <span class="shop-notification__sigil" aria-hidden="true">
          <i :class="notification.icon"></i>
        </span>
        <span class="shop-notification__body">
          <strong class="shop-notification__title">
            {{ notification.title }}
          </strong>
          <span class="shop-notification__message">
            {{ notification.message }}
          </span>
          <span
            v-if="detailsText(notification)"
            class="shop-notification__details"
          >
            {{ detailsText(notification) }}
          </span>
        </span>
        <button
          type="button"
          class="shop-notification__close"
          :aria-label="$t('common.actions.close')"
          @click="dismiss(notification.id)"
        >
          <span aria-hidden="true">&times;</span>
        </button>
      </section>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useShopNotifications } from "@/components/shop/composables/useShopNotifications";

const props = defineProps({
  zone: {
    type: String,
    default: "system",
  },
});

const { current, notifications, dismiss } = useShopNotifications(props.zone);

const activeProblems = computed(() =>
  notifications.value.filter((entry) =>
    ["error", "warning"].includes(entry.type),
  ),
);

const visibleNotifications = computed(() => {
  if (activeProblems.value.length > 1) {
    return activeProblems.value;
  }
  return current.value ? [current.value] : [];
});

const detailsText = (notification) => {
  const details = notification?.details;
  if (Array.isArray(details)) {
    return details.filter(Boolean).join(" | ");
  }
  return String(details || "").trim();
};

const roleFor = (notification) =>
  notification?.type === "error" ? "alert" : "status";
</script>

<style scoped src="./ShopNotificationBar.css"></style>
