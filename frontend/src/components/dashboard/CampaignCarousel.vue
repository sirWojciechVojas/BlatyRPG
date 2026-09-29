<template>
  <div class="brpg-carousel">
    <button
      class="brpg-arrow brpg-arrow--left"
      type="button"
      :disabled="atStart"
      :aria-label="$t('dashboard.carousel.previous')"
      @click="scrollByPage(-1)"
    >
      <span class="brpg-icon brpg-icon--left" aria-hidden="true" />
    </button>

    <div
      ref="viewport"
      class="brpg-track"
      :class="{ 'brpg-track--centered': campaigns.length < 5 }"
      tabindex="0"
      :aria-label="$t('dashboard.carousel.label')"
      @scroll.passive="scheduleMeasure"
      @keydown="handleViewportKeydown"
    >
      <CampaignCard
        v-for="(campaign, index) in campaigns"
        :key="campaign.id"
        :campaign="campaign"
        :system-name="campaignSystemName(campaign)"
        :fallback-artwork="fallbackArtwork(campaign)"
        :ribbon="campaignRibbon(campaign)"
        :priority="index < Math.max(capacity, 1)"
      />
    </div>

    <button
      class="brpg-arrow brpg-arrow--right"
      type="button"
      :disabled="atEnd"
      :aria-label="$t('dashboard.carousel.next')"
      @click="scrollByPage(1)"
    >
      <span class="brpg-icon brpg-icon--right" aria-hidden="true" />
    </button>

    <div
      ref="scrollbar"
      class="brpg-scrollbar"
      role="scrollbar"
      tabindex="0"
      aria-orientation="horizontal"
      aria-valuemin="0"
      aria-valuemax="100"
      :aria-valuenow="scrollPercent"
      :aria-valuetext="rangeText"
      @pointerdown.prevent="jumpToPointer"
      @keydown="handleScrollbarKeydown"
    >
      <span
        ref="thumb"
        class="brpg-scrollbar-thumb"
        :class="{ 'is-dragging': dragging }"
        :style="thumbStyle"
        @pointerdown.stop.prevent="startThumbDrag"
        @pointermove.prevent="moveThumb"
        @pointerup.prevent="finishThumbDrag"
        @pointercancel="finishThumbDrag"
      />
    </div>

    <div
      v-if="pageCount > 1"
      class="brpg-pagination"
      role="group"
      :aria-label="$t('dashboard.carousel.pages')"
    >
      <button
        v-for="page in pageCount"
        :key="page"
        class="brpg-page-dot"
        type="button"
        :aria-label="$t('dashboard.carousel.page', { page })"
        :aria-current="page - 1 === currentPage ? 'page' : undefined"
        @click="goToPage(page - 1)"
      />
    </div>

    <p class="brpg-counter" aria-live="polite">{{ rangeText }}</p>
  </div>
</template>

<script>
import CampaignCard from "./CampaignCard.vue";

const GUI_ROOT = `${process.env.BASE_URL || "/"}blaty-rpg-gui`;
const FALLBACKS = [
  "warhammer_2",
  "warhammer_4",
  "dnd",
  "pathfinder",
  "cthulhu",
];
const RIBBONS = ["red", "red", "blue", "green", "blue"];
const clamp = (value, minimum, maximum) =>
  Math.min(Math.max(value, minimum), maximum);

const stableIndex = (campaign) => {
  const numericId = Number(campaign.id);
  if (Number.isFinite(numericId)) return Math.abs(numericId) % FALLBACKS.length;
  return (
    [...String(campaign.id || campaign.name)].reduce(
      (sum, character) => sum + character.charCodeAt(0),
      0,
    ) % FALLBACKS.length
  );
};

export default {
  name: "CampaignCarousel",
  components: { CampaignCard },
  props: {
    campaigns: { type: Array, required: true },
    games: { type: Array, default: () => [] },
  },
  emits: ["range-change"],
  data: () => ({
    clientWidth: 0,
    scrollWidth: 0,
    scrollLeft: 0,
    capacity: 1,
    visibleStart: 0,
    visibleEnd: 0,
    dragging: false,
    dragPointerId: null,
    dragStartX: 0,
    dragStartScroll: 0,
    resizeObserver: null,
    measureFrame: 0,
    lastRangeSignature: "",
  }),
  computed: {
    maxScroll() {
      return Math.max(0, this.scrollWidth - this.clientWidth);
    },
    atStart() {
      return this.scrollLeft <= 1;
    },
    atEnd() {
      return this.maxScroll <= 1 || this.scrollLeft >= this.maxScroll - 1;
    },
    pageCount() {
      return Math.max(1, Math.ceil(this.campaigns.length / this.capacity));
    },
    currentPage() {
      if (!this.maxScroll || this.pageCount === 1) return 0;
      let closestPage = 0;
      let closestDistance = Number.POSITIVE_INFINITY;
      for (let page = 0; page < this.pageCount; page += 1) {
        const distance = Math.abs(this.scrollLeft - this.pageTarget(page));
        if (distance < closestDistance) {
          closestDistance = distance;
          closestPage = page;
        }
      }
      return closestPage;
    },
    thumbWidthPercent() {
      if (!this.scrollWidth) return 100;
      return clamp((this.clientWidth / this.scrollWidth) * 100, 12, 100);
    },
    thumbLeftPercent() {
      if (!this.maxScroll) return 0;
      return (
        (this.scrollLeft / this.maxScroll) * (100 - this.thumbWidthPercent)
      );
    },
    thumbStyle() {
      return {
        width: `${this.thumbWidthPercent}%`,
        left: `${this.thumbLeftPercent}%`,
      };
    },
    scrollPercent() {
      return this.maxScroll
        ? Math.round((this.scrollLeft / this.maxScroll) * 100)
        : 0;
    },
    visibleCount() {
      return this.campaigns.length
        ? Math.max(0, this.visibleEnd - this.visibleStart + 1)
        : 0;
    },
    rangeText() {
      return this.$t("dashboard.carousel.range", {
        start: this.campaigns.length ? this.visibleStart + 1 : 0,
        end: this.campaigns.length ? this.visibleEnd + 1 : 0,
        total: this.campaigns.length,
      });
    },
  },
  watch: {
    campaigns: {
      deep: true,
      handler() {
        this.$nextTick(this.scheduleMeasure);
      },
    },
  },
  mounted() {
    this.resizeObserver = new ResizeObserver(this.scheduleMeasure);
    this.resizeObserver.observe(this.$refs.viewport);
    this.$nextTick(this.measure);
  },
  beforeUnmount() {
    this.resizeObserver?.disconnect();
    if (this.measureFrame) cancelAnimationFrame(this.measureFrame);
  },
  methods: {
    cards() {
      return [...(this.$refs.viewport?.querySelectorAll(".brpg-card") || [])];
    },
    scheduleMeasure() {
      if (this.measureFrame) cancelAnimationFrame(this.measureFrame);
      this.measureFrame = requestAnimationFrame(() => {
        this.measureFrame = 0;
        this.measure();
      });
    },
    measure() {
      const viewport = this.$refs.viewport;
      const cards = this.cards();
      if (!viewport) return;

      this.clientWidth = viewport.clientWidth;
      this.scrollWidth = viewport.scrollWidth;
      this.scrollLeft = clamp(viewport.scrollLeft, 0, this.maxScroll);

      const firstCard = cards[0];
      const secondCard = cards[1];
      if (firstCard) {
        const step = secondCard
          ? secondCard.offsetLeft - firstCard.offsetLeft
          : firstCard.offsetWidth;
        const gap = Math.max(0, step - firstCard.offsetWidth);
        this.capacity = Math.max(
          1,
          Math.round((viewport.clientWidth + gap) / Math.max(step, 1)),
        );
      }

      if (cards.length) {
        const leftEdge = viewport.scrollLeft + 2;
        const rightEdge = viewport.scrollLeft + viewport.clientWidth - 2;
        const visible = cards
          .map((card, index) => ({
            index,
            left: card.offsetLeft,
            right: card.offsetLeft + card.offsetWidth,
          }))
          .filter((card) => card.right > leftEdge && card.left < rightEdge);
        this.visibleStart = visible[0]?.index ?? 0;
        this.visibleEnd = visible.at(-1)?.index ?? 0;
      } else {
        this.visibleStart = 0;
        this.visibleEnd = 0;
      }

      const range = {
        start: cards.length ? this.visibleStart + 1 : 0,
        end: cards.length ? this.visibleEnd + 1 : 0,
        visible: this.visibleCount,
        total: this.campaigns.length,
      };
      const signature = JSON.stringify(range);
      if (signature !== this.lastRangeSignature) {
        this.lastRangeSignature = signature;
        this.$emit("range-change", range);
      }
    },
    pageTarget(page) {
      const cards = this.cards();
      const index = Math.min(
        page * this.capacity,
        Math.max(0, cards.length - 1),
      );
      return clamp(cards[index]?.offsetLeft || 0, 0, this.maxScroll);
    },
    scrollTo(left, behavior = "smooth") {
      this.$refs.viewport?.scrollTo({
        left: clamp(left, 0, this.maxScroll),
        behavior,
      });
    },
    goToPage(page) {
      this.scrollTo(this.pageTarget(clamp(page, 0, this.pageCount - 1)));
    },
    scrollByPage(direction) {
      this.goToPage(this.currentPage + direction);
    },
    scrollToCampaign(campaignId) {
      this.$nextTick(() => {
        const index = this.campaigns.findIndex(
          (campaign) => String(campaign.id) === String(campaignId),
        );
        const card = this.cards()[index];
        if (!card) return;
        this.scrollTo(
          card.offsetLeft - (this.clientWidth - card.offsetWidth) / 2,
        );
      });
    },
    handleViewportKeydown(event) {
      if (event.target !== event.currentTarget) return;
      if (["ArrowLeft", "PageUp"].includes(event.key)) {
        event.preventDefault();
        this.scrollByPage(-1);
      } else if (["ArrowRight", "PageDown"].includes(event.key)) {
        event.preventDefault();
        this.scrollByPage(1);
      } else if (event.key === "Home") {
        event.preventDefault();
        this.scrollTo(0);
      } else if (event.key === "End") {
        event.preventDefault();
        this.scrollTo(this.maxScroll);
      }
    },
    handleScrollbarKeydown(event) {
      if (["ArrowLeft", "PageUp"].includes(event.key)) {
        event.preventDefault();
        this.scrollByPage(-1);
      } else if (["ArrowRight", "PageDown"].includes(event.key)) {
        event.preventDefault();
        this.scrollByPage(1);
      } else if (event.key === "Home") {
        event.preventDefault();
        this.scrollTo(0);
      } else if (event.key === "End") {
        event.preventDefault();
        this.scrollTo(this.maxScroll);
      }
    },
    jumpToPointer(event) {
      if (!this.maxScroll) return;
      const track = this.$refs.scrollbar;
      const thumb = this.$refs.thumb;
      const trackRect = track.getBoundingClientRect();
      const thumbWidth = thumb.getBoundingClientRect().width;
      const available = Math.max(1, trackRect.width - thumbWidth);
      const position = clamp(
        event.clientX - trackRect.left - thumbWidth / 2,
        0,
        available,
      );
      this.scrollTo((position / available) * this.maxScroll);
    },
    startThumbDrag(event) {
      if (!this.maxScroll) return;
      this.dragging = true;
      this.dragPointerId = event.pointerId;
      this.dragStartX = event.clientX;
      this.dragStartScroll = this.scrollLeft;
      event.currentTarget.setPointerCapture(event.pointerId);
    },
    moveThumb(event) {
      if (!this.dragging || event.pointerId !== this.dragPointerId) return;
      const trackWidth = this.$refs.scrollbar.getBoundingClientRect().width;
      const thumbWidth = this.$refs.thumb.getBoundingClientRect().width;
      const available = Math.max(1, trackWidth - thumbWidth);
      const delta =
        ((event.clientX - this.dragStartX) / available) * this.maxScroll;
      this.scrollTo(this.dragStartScroll + delta, "auto");
    },
    finishThumbDrag(event) {
      if (event.pointerId !== this.dragPointerId) return;
      if (event.currentTarget.hasPointerCapture?.(event.pointerId)) {
        event.currentTarget.releasePointerCapture(event.pointerId);
      }
      this.dragging = false;
      this.dragPointerId = null;
    },
    campaignSystemName(campaign) {
      const match = this.games.find(
        (game) => Number(game.systemId) === Number(campaign.systemId),
      );
      return (
        match?.systemName ||
        match?.systemCode ||
        campaign.systemType ||
        this.$t("dashboard.campaign.unknownSystem")
      );
    },
    fallbackArtwork(campaign) {
      const system = String(campaign.systemType || "").toLowerCase();
      let name;
      if (/cthulhu|coc/.test(system)) name = "cthulhu";
      else if (/pathfinder/.test(system)) name = "pathfinder";
      else if (/dnd|dungeons|dragon/.test(system)) name = "dnd";
      else if (/warhammer|wfrp/.test(system)) {
        name = stableIndex(campaign) % 2 ? "warhammer_4" : "warhammer_2";
      } else name = FALLBACKS[stableIndex(campaign)];
      return `${GUI_ROOT}/campaigns/${name}.webp`;
    },
    campaignRibbon(campaign) {
      return campaign.isActive ? RIBBONS[stableIndex(campaign)] : "neutral";
    },
  },
};
</script>
