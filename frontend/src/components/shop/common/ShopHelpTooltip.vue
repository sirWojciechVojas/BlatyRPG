<!-- Responsibility: ShopHelpTooltip shop interface component. -->
<template>
  <span class="shop-help-tooltip">
    <button
      ref="trigger"
      type="button"
      class="shop-help-tooltip__trigger"
      :aria-label="label || text"
      :data-bs-title="text"
      data-bs-toggle="tooltip"
      @click.stop
    >
      i
    </button>
  </span>
</template>

<script setup>
import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from "vue";
import { Tooltip } from "bootstrap";

const props = defineProps({
  label: {
    type: String,
    default: "",
  },
  text: {
    type: String,
    required: true,
  },
  align: {
    type: String,
    default: "center",
  },
});

const trigger = ref(null);
const placement = computed(() => {
  if (props.align === "right") return "bottom-end";
  if (props.align === "left") return "bottom-start";
  return "bottom";
});
let tooltip = null;

const mountTooltip = () => {
  tooltip?.dispose();
  tooltip = trigger.value
    ? new Tooltip(trigger.value, {
        container: "body",
        customClass: "shop-bootstrap-tooltip",
        boundary: "viewport",
        delay: { show: 80, hide: 60 },
        offset: [0, 7],
        placement: placement.value,
        title: props.text,
        trigger: "hover focus",
      })
    : null;
};

onMounted(mountTooltip);
onBeforeUnmount(() => tooltip?.dispose());
watch(
  () => [props.text, props.align],
  async () => {
    await nextTick();
    mountTooltip();
  },
);
</script>

<style scoped>
.shop-help-tooltip {
  position: relative;
  display: inline-flex;
  align-items: center;
  flex: 0 0 auto;
}

.shop-help-tooltip__trigger {
  width: 1.15rem;
  height: 1.15rem;
  min-width: 1.15rem;
  min-height: 1.15rem;
  padding: 0;
  border-radius: 999px;
  border: 1px solid #d6a350;
  background: #2b1a0d;
  box-shadow:
    inset 0 0 0 1px rgba(255, 225, 164, 0.08),
    0 0 0.32rem rgba(214, 163, 80, 0.16);
  color: #ffe3a4;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.66rem;
  font-weight: 700;
  line-height: 1;
  cursor: help;
  transition:
    border-color 0.18s ease,
    background 0.18s ease,
    transform 0.18s ease;
}

.shop-help-tooltip__trigger:hover,
.shop-help-tooltip__trigger:focus-visible {
  border-color: rgba(255, 214, 142, 0.95);
  background: #5b3818;
  color: #fff4d8;
  box-shadow:
    0 0 0 2px rgba(255, 210, 125, 0.14),
    0 0 0.7rem rgba(226, 166, 74, 0.32);
  transform: translateY(-1px);
  outline: none;
}
</style>

<style>
.shop-bootstrap-tooltip {
  --bs-tooltip-max-width: min(28rem, calc(100vw - 1rem));
  --bs-tooltip-bg: #120c08;
  --bs-tooltip-color: #fff3d9;
  --bs-tooltip-opacity: 1;
  z-index: 10050 !important;
  pointer-events: none;
}

.shop-bootstrap-tooltip .tooltip-inner {
  border: 1px solid #c38d43;
  border-radius: 0.4rem;
  background: linear-gradient(180deg, #21150c, #100a07);
  box-shadow:
    0 0.8rem 1.8rem rgba(0, 0, 0, 0.7),
    0 0 0.8rem rgba(202, 143, 62, 0.12);
  padding: 0.62rem 0.72rem;
  color: #fff3d9;
  font-size: 0.78rem;
  font-variant: normal;
  font-weight: 400;
  letter-spacing: normal;
  line-height: 1.5;
  text-align: left;
  text-transform: none !important;
}
</style>
