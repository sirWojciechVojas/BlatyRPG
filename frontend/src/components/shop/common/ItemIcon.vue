<!-- Responsibility: ItemIcon shop interface component. -->
<template>
  <span
    class="item-icon"
    :style="{ width: `${size}px`, height: `${size}px` }"
    aria-hidden="true"
  >
    <img v-if="directSource" :src="directSource" alt="" />
    <span v-else class="item-icon__sprite" :style="spriteStyle" />
  </span>
</template>

<script setup>
import { computed } from "vue";
import inventorySprite42 from "@/assets/app-ui/img/inventory/invIco42x42.png";
import inventorySprite72 from "@/assets/app-ui/img/inventory/invIco72x72.png";
import inventorySprite144 from "@/assets/app-ui/img/inventory/invIco144x144.png";
import { resolveItemIconClass } from "@/lib/trade/shopItemIconResolver";
import {
  getIconMetadata,
  iconMetadataRevision,
} from "@/lib/trade/iconMetadataRegistry";

const props = defineProps({
  item: { type: Object, default: () => ({}) },
  size: { type: Number, default: 30 },
});

const iconToken = computed(() => {
  const explicit = String(props.item?.IMG_CLASS || props.item?.imgClass || "")
    .trim()
    .toLowerCase();
  return /^v\d{4}$/u.test(explicit)
    ? explicit
    : resolveItemIconClass(props.item).toLowerCase();
});
const directSource = computed(() => {
  void iconMetadataRevision.value;
  return String(
    props.item?.iconUrl ||
      props.item?.ICON_URL ||
      props.item?.imageUrl ||
      props.item?.IMAGE_URL ||
      (props.size <= 48
        ? getIconMetadata(iconToken.value)?.imageUrlSmall
        : getIconMetadata(iconToken.value)?.imageUrlLarge) ||
      getIconMetadata(iconToken.value)?.imageUrl ||
      "",
  ).trim();
});
const iconNumber = computed(() => {
  const match = iconToken.value.match(/^v(\d{1,4})$/);
  const parsed = match ? Number(match[1]) : 1;
  return Math.max(1, Math.min(1375, Number.isFinite(parsed) ? parsed : 1));
});
const spriteStyle = computed(() => {
  const index = iconNumber.value - 1;
  const column = index % 20;
  const row = Math.floor(index / 20);
  const inventorySprite =
    props.size >= 96
      ? inventorySprite144
      : props.size >= 58
        ? inventorySprite72
        : inventorySprite42;
  return {
    backgroundImage: `url("${inventorySprite}")`,
    backgroundPosition: `${-column * props.size}px ${-row * props.size}px`,
    backgroundSize: `${20 * props.size}px auto`,
  };
});
</script>

<style scoped>
.item-icon {
  display: inline-grid;
  flex: 0 0 auto;
  place-items: center;
  overflow: hidden;
  border: 1px solid rgba(222, 181, 108, 0.5);
  border-radius: 0.24rem;
  background: linear-gradient(145deg, #53361f, #17100b 72%);
  box-shadow:
    inset 0 0 0 1px rgba(255, 231, 176, 0.08),
    0 1px 4px rgba(0, 0, 0, 0.65);
}
.item-icon img,
.item-icon__sprite {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.item-icon__sprite {
  background-repeat: no-repeat;
}
</style>
