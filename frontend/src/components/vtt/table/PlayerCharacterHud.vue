<template>
  <section
    v-if="visible"
    class="hud-runtime player-character-hud"
    :style="hudStyle"
    :aria-label="
      $t('vtt.table.playerHud.characterLabel', { name: character.name })
    "
    :aria-busy="saving ? 'true' : 'false'"
  >
    <div
      v-if="pinnedSpells.length"
      class="hud-pinned-spells"
      role="group"
      :aria-label="$t('vtt.table.playerHud.actions.spellPins.label')"
    >
      <span>{{ $t("vtt.table.playerHud.actions.spellPins.label") }}</span>
      <button
        v-for="spell in pinnedSpells"
        :key="spell.id"
        type="button"
        :title="
          $t('vtt.table.playerHud.actions.spellPins.cast', {
            spell: spell.name,
          })
        "
        @click="openPinnedSpell(spell.id)"
      >
        {{ spell.name }}
      </button>
    </div>

    <div class="hud-runtime__stage">
      <AuthenticatedImage
        class="hud-avatar-image"
        :src="avatar"
        :alt="$t('vtt.table.playerHud.avatarAlt', { name: character.name })"
        width="175"
        height="175"
        decoding="async"
      />

      <div
        class="hud-hp-fill"
        :style="{ '--hp': healthRatio }"
        aria-hidden="true"
      ></div>
      <span
        class="hud-sprite hud-sprite--hp-foreground hud-hp-foreground"
        aria-hidden="true"
      ></span>
      <p class="hud-text hud-name">{{ character.name || "—" }}</p>
      <output
        class="hud-text hud-health"
        :aria-label="$t('vtt.table.playerHud.health.label')"
        :title="healthTooltip"
        aria-live="polite"
        >{{ healthValue }}</output
      >

      <button
        type="button"
        class="hud-control hud-sprite hud-sprite--button-minus hud-minus"
        :disabled="!canModifyHealth"
        :aria-disabled="String(!canModifyHealth)"
        :title="$t('vtt.table.playerHud.modify.decreaseHealth')"
        :aria-label="$t('vtt.table.playerHud.modify.decreaseHealth')"
        @click="modifyHealth(-1)"
      >
        <span
          class="hud-sprite hud-sprite--symbol-minus"
          aria-hidden="true"
        ></span>
      </button>
      <button
        type="button"
        class="hud-control hud-sprite hud-sprite--button-plus hud-plus"
        :disabled="!canModifyHealth"
        :aria-disabled="String(!canModifyHealth)"
        :title="$t('vtt.table.playerHud.modify.increaseHealth')"
        :aria-label="$t('vtt.table.playerHud.modify.increaseHealth')"
        @click="modifyHealth(1)"
      >
        <span
          class="hud-sprite hud-sprite--symbol-plus"
          aria-hidden="true"
        ></span>
      </button>

      <div
        class="hud-menu"
        role="group"
        :aria-label="$t('vtt.table.playerHud.actions.label')"
      >
        <button
          v-for="action in hudActions"
          :key="action.id"
          type="button"
          class="hud-control hud-sprite hud-sprite--button-menu"
          :class="`hud-menu--icon-${action.sprite}`"
          :disabled="!action.available"
          :aria-disabled="String(!action.available)"
          :title="actionTitle(action)"
          :aria-label="actionTitle(action)"
          :data-hud-action="action.id"
          @click="runAction(action)"
        >
          <span
            class="hud-sprite"
            :class="`hud-sprite--icon-${action.sprite}`"
            aria-hidden="true"
          ></span>
        </button>
      </div>

      <p class="hud-text hud-summary">
        {{ $t("vtt.table.playerHud.experience.summary", experienceSummary) }}
      </p>
      <p class="hud-text hud-pd">
        PD {{ displayValue(model.experience.complex.current) }}
      </p>

      <template v-for="bar in experienceBars" :key="bar.id">
        <p class="hud-text hud-xp-label" :class="`hud-xp-label--${bar.id}`">
          {{ $t(bar.labelKey) }}
        </p>
        <p class="hud-text hud-xp-value" :class="`hud-xp-value--${bar.id}`">
          {{ experiencePercentLabel(bar.resource) }}
        </p>
        <div
          class="hud-xp-progress"
          :class="`hud-xp-progress--${bar.id}`"
          role="progressbar"
          :aria-label="$t(bar.ariaKey)"
          aria-valuemin="0"
          aria-valuemax="100"
          :aria-valuenow="experiencePercent(bar.resource)"
          :title="experienceTooltip(bar.id)"
        >
          <span
            class="hud-xp-fill"
            :class="`hud-xp-fill--${bar.runtimeId}`"
            :style="{ '--xp': experienceRatio(bar.resource) }"
            aria-hidden="true"
          ></span>
        </div>
      </template>

      <button
        type="button"
        class="hud-control hud-die hud-die-button"
        :disabled="!diceAction?.available"
        :aria-disabled="String(!diceAction?.available)"
        :title="$t('vtt.table.playerHud.actions.rollD100')"
        :aria-label="$t('vtt.table.playerHud.actions.rollD100')"
        data-hud-action="dice"
        @click="runAction(diceAction)"
      >
        <span
          class="hud-sprite hud-sprite--dice-d100"
          aria-hidden="true"
        ></span>
        <span class="hud-visually-hidden">{{
          $t("vtt.table.playerHud.actions.rollD100")
        }}</span>
      </button>
      <button
        type="button"
        class="hud-control hud-sprite hud-sprite--button-3d hud-mode"
        :disabled="!diceAction?.available"
        :aria-disabled="String(!diceAction?.available)"
        :title="$t('vtt.table.playerHud.actions.choose3dDice')"
        :aria-label="$t('vtt.table.playerHud.actions.choose3dDice')"
        data-hud-action="dice-selector"
        @click="$emit('open-dice-selector')"
      >
        <span class="hud-mode-label">3D</span>
      </button>
    </div>

    <p
      v-if="notice"
      class="hud-notice"
      :class="{ 'hud-notice--error': noticeIsError }"
      :role="noticeIsError ? 'alert' : 'status'"
    >
      {{ notice }}
    </p>
  </section>
</template>

<script>
import { authSession } from "@/lib/auth/authSession";
import AuthenticatedImage from "@/components/ui/AuthenticatedImage.vue";
import { characterApiClient } from "@/lib/character/characterApiClient";
import { magicApiClient } from "@/lib/magic/magicApiClient";
import { resolveCharacterAvatar } from "@/lib/trade/characterAvatar";
import { isPlayerHudModalId } from "./playerHudModalRegistry";
import {
  cloneDataWithNumber,
  createPlayerCharacterHudModel,
  isHudCharacterAllowed,
  nextResourceValue,
  playerHudActions,
  selectHudCharacter,
  tokenResourcesWithValue,
} from "./playerCharacterHudModel";

const HUD_ACTIONS = Object.freeze([
  ["character", "character"],
  ["advance", "development"],
  ["combat", "combat"],
  ["shop", "inventory"],
  ["history", "book"],
  ["spells", "scroll"],
  ["notes", "quill"],
  ["journal", "journal"],
  ["map", "map"],
  ["bestiary", "bestiary"],
  ["traits", "talents"],
  ["purse", "purse"],
  ["abilities", "star"],
  ["settings", "settings"],
]);

export default {
  name: "PlayerCharacterHud",
  components: { AuthenticatedImage },
  props: {
    campaignId: { type: [Number, String], required: true },
    characters: { type: Array, default: () => [] },
    focusedCharacterId: { type: [Number, String], default: null },
    selectedCharacterId: { type: [Number, String], default: null },
    tokens: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    canOpenShop: { type: Boolean, default: false },
    shopBusy: { type: Boolean, default: false },
    contextPhase: { type: String, default: "idle" },
    contextGeneration: { type: Number, default: 0 },
    contextUnauthorized: { type: Boolean, default: false },
    contextError: { type: Object, default: null },
  },
  emits: [
    "visibility-change",
    "fit-map",
    "open-dice",
    "open-dice-selector",
    "open-modal",
  ],
  data: () => ({
    session: authSession.read(),
    character: null,
    loading: false,
    saving: "",
    notice: "",
    noticeIsError: false,
    viewportWidth: 0,
    unsubscribeAuth: null,
    loadedCampaignId: null,
    loadRequestSequence: 0,
    saveRequestSequence: 0,
    magicRequestSequence: 0,
    pinnedSpells: [],
    pinChangeListener: null,
  }),
  computed: {
    userId() {
      return this.session?.user?.id ?? null;
    },
    selectedSummary() {
      return selectHudCharacter({
        characters: this.characters,
        userId: this.userId,
        focusedCharacterId: this.focusedCharacterId,
        selectedCharacterId: this.selectedCharacterId,
        canManage: this.canManage,
        campaignId: this.campaignId,
      });
    },
    eligible() {
      const campaignId = Number(this.campaignId);
      return (
        Number.isInteger(campaignId) &&
        campaignId > 0 &&
        this.contextUnauthorized !== true &&
        (this.canManage || Boolean(this.userId)) &&
        Boolean(this.selectedSummary)
      );
    },
    requestKey() {
      return [
        this.campaignId,
        this.contextGeneration,
        this.userId,
        this.selectedSummary?.id || "none",
        this.eligible ? "ready" : "hidden",
      ].join(":");
    },
    visible() {
      return (
        this.eligible &&
        isHudCharacterAllowed({
          character: this.character,
          summary: this.selectedSummary,
          userId: this.userId,
          characterId: this.selectedSummary?.id,
          canManage: this.canManage,
        })
      );
    },
    model() {
      return createPlayerCharacterHudModel(this.character || {}, this.tokens);
    },
    avatar() {
      return resolveCharacterAvatar(this.character, this.character?.name);
    },
    actions() {
      return playerHudActions(this.canOpenShop, this.canManage, this.shopBusy);
    },
    hudActions() {
      const actionsById = new Map(
        this.actions.map((action) => [action.id, action]),
      );
      return HUD_ACTIONS.map(([id, sprite]) => ({
        ...(actionsById.get(id) || { id, available: false }),
        sprite,
      }));
    },
    diceAction() {
      return this.actions.find((action) => action.id === "dice") || null;
    },
    experienceBars() {
      return [
        {
          id: "minimum",
          runtimeId: "min",
          resource: this.model.experience.minimum,
          labelKey: "vtt.table.playerHud.experience.minimumLabel",
          ariaKey: "vtt.table.playerHud.experience.minimum",
        },
        {
          id: "complex",
          runtimeId: "max",
          resource: this.model.experience.complex,
          labelKey: "vtt.table.playerHud.experience.complexLabel",
          ariaKey: "vtt.table.playerHud.experience.complex",
        },
      ];
    },
    experienceSummary() {
      return {
        spent: this.displayValue(this.model.experience.complex.spent),
        available: this.displayValue(this.model.experience.available),
      };
    },
    healthValue() {
      return `${this.displayValue(this.model.health.current)} / ${this.displayValue(this.model.health.maximum)}`;
    },
    canModifyHealth() {
      return (
        this.visible &&
        !this.saving &&
        this.character?.capabilities?.canEdit === true &&
        this.model.health.editable === true
      );
    },
    healthTooltip() {
      return this.$t("vtt.table.playerHud.health.tooltip", {
        current: this.displayValue(this.model.health.current),
        maximum: this.displayValue(this.model.health.maximum),
      });
    },
    healthRatio() {
      return this.model.health.percent / 100;
    },
    hudStyle() {
      if (!this.viewportWidth || this.viewportWidth >= 804) return {};
      const scale = this.viewportWidth / 1072;
      return {
        "--hud-width": `${this.viewportWidth}px`,
        "--hud-height": `${376 * scale}px`,
        "--hud-scale": scale,
      };
    },
  },
  watch: {
    requestKey: {
      immediate: true,
      handler() {
        this.resetAndLoad();
      },
    },
    visible: {
      immediate: true,
      handler(value) {
        this.$emit("visibility-change", value);
      },
    },
  },
  created() {
    this.unsubscribeAuth = authSession.subscribe((session) => {
      this.session = session;
      if (!session) {
        this.loadRequestSequence += 1;
        this.saveRequestSequence += 1;
        this.character = null;
        this.loadedCampaignId = null;
      }
    });
  },
  mounted() {
    this.updateViewportWidth();
    this.pinChangeListener = (event) => {
      if (
        Number(event?.detail?.campaignId) === Number(this.campaignId) &&
        Number(event?.detail?.characterId) === Number(this.character?.id)
      ) {
        this.loadPinnedSpells();
      }
    };
    window.addEventListener("resize", this.updateViewportWidth, {
      passive: true,
    });
    window.addEventListener(
      "blatyrpg:magic-pins-changed",
      this.pinChangeListener,
    );
  },
  beforeUnmount() {
    this.loadRequestSequence += 1;
    this.saveRequestSequence += 1;
    this.magicRequestSequence += 1;
    this.unsubscribeAuth?.();
    window.removeEventListener("resize", this.updateViewportWidth);
    window.removeEventListener(
      "blatyrpg:magic-pins-changed",
      this.pinChangeListener,
    );
    this.$emit("visibility-change", false);
  },
  methods: {
    updateViewportWidth() {
      this.viewportWidth = document.documentElement.clientWidth;
    },
    resetAndLoad() {
      this.loadRequestSequence += 1;
      this.saveRequestSequence += 1;
      this.magicRequestSequence += 1;
      this.loading = false;
      this.saving = "";
      this.notice = "";
      this.noticeIsError = false;
      if (!this.eligible) {
        this.character = null;
        this.pinnedSpells = [];
        this.loadedCampaignId = null;
        return;
      }
      const selectionChanged =
        String(this.character?.id ?? "") !==
          String(this.selectedSummary?.id ?? "") ||
        String(this.loadedCampaignId ?? "") !== String(this.campaignId ?? "");
      if (selectionChanged) {
        this.character = null;
        this.pinnedSpells = [];
        this.loadedCampaignId = null;
      }
      this.loadCharacter();
    },
    async loadCharacter() {
      if (!this.eligible || !this.selectedSummary?.id) return null;
      const sequence = ++this.loadRequestSequence;
      const campaignId = this.campaignId;
      const characterId = this.selectedSummary.id;
      const userId = this.userId;
      this.loading = true;
      try {
        const character = await characterApiClient.get(campaignId, characterId);
        if (
          sequence !== this.loadRequestSequence ||
          String(campaignId) !== String(this.campaignId) ||
          !isHudCharacterAllowed({
            character,
            summary: this.selectedSummary,
            userId,
            characterId,
            canManage: this.canManage,
          })
        ) {
          return null;
        }
        this.character = character;
        this.loadedCampaignId = campaignId;
        this.loadPinnedSpells();
        return character;
      } catch (_error) {
        if (
          sequence === this.loadRequestSequence &&
          (String(this.character?.id ?? "") !== String(characterId) ||
            String(this.loadedCampaignId ?? "") !== String(campaignId))
        ) {
          this.character = null;
          this.loadedCampaignId = null;
        }
        return null;
      } finally {
        if (sequence === this.loadRequestSequence) this.loading = false;
      }
    },
    displayValue(value) {
      return Number.isFinite(Number(value)) && value !== null
        ? Number(value)
        : "—";
    },
    async loadPinnedSpells() {
      if (!this.character?.id) return;
      const sequence = ++this.magicRequestSequence;
      const campaignId = this.campaignId;
      const characterId = this.character.id;
      try {
        const magic = await magicApiClient.get(campaignId, characterId);
        if (
          sequence !== this.magicRequestSequence ||
          Number(characterId) !== Number(this.character?.id) ||
          Number(campaignId) !== Number(this.campaignId)
        ) {
          return;
        }
        this.pinnedSpells = (magic?.knownSpells || [])
          .filter((spell) => spell.pinned)
          .sort((left, right) => (left.pinOrder || 99) - (right.pinOrder || 99))
          .slice(0, 6);
      } catch (_error) {
        if (sequence === this.magicRequestSequence) this.pinnedSpells = [];
      }
    },
    openPinnedSpell(spellId) {
      this.$emit("open-modal", "spells", {
        characterId: this.character.id,
        spellId,
      });
    },
    actionLabel(action) {
      return this.$t(`vtt.table.playerHud.actions.${action.id}`);
    },
    actionTitle(action) {
      const label = this.actionLabel(action);
      if (action.busy) {
        return this.$t("vtt.table.playerHud.actions.shopOpening");
      }
      return action.available
        ? label
        : this.$t("vtt.table.playerHud.actions.disabled", { action: label });
    },
    runAction(action) {
      if (!action?.available) return;
      if (isPlayerHudModalId(action.id)) {
        this.$emit("open-modal", action.id, {
          characterId: this.character.id,
        });
        return;
      }
      const events = {
        map: ["fit-map"],
        dice: ["open-dice"],
      };
      const [event, payload] = events[action.id] || [];
      if (event) this.$emit(event, payload);
    },
    experienceRatio(resource) {
      const current = Number(resource?.current);
      const required = Number(resource?.required);
      if (
        !Number.isFinite(current) ||
        !Number.isFinite(required) ||
        required <= 0
      ) {
        return 0;
      }
      return Math.max(0, Math.min(1, current / required));
    },
    experiencePercent(resource) {
      return Number((this.experienceRatio(resource) * 100).toFixed(1));
    },
    experiencePercentLabel(resource) {
      const locale = this.$i18n?.locale || "pl";
      return `${new Intl.NumberFormat(locale, {
        maximumFractionDigits: 1,
      }).format(this.experiencePercent(resource))}%`;
    },
    experienceTooltip(type) {
      const resource = this.model.experience[type];
      return this.$t(`vtt.table.playerHud.experience.${type}Tooltip`, {
        current: this.displayValue(resource.current),
        required: this.displayValue(resource.required),
        available: this.displayValue(resource.available),
      });
    },
    mutationIsCurrent(sequence, campaignId, characterId, userId) {
      return (
        sequence === this.saveRequestSequence &&
        String(campaignId) === String(this.campaignId) &&
        isHudCharacterAllowed({
          character: this.character,
          summary: this.selectedSummary,
          userId,
          characterId,
          canManage: this.canManage,
        })
      );
    },
    async refreshAfterSave(sequence, campaignId, characterId, userId) {
      await Promise.allSettled([
        this.$store.dispatch("campaignContext/refresh"),
        this.$store.dispatch("vtt/loadTokens"),
      ]);
      if (!this.mutationIsCurrent(sequence, campaignId, characterId, userId)) {
        return;
      }
      await this.loadCharacter();
    },
    async modifyHealth(delta) {
      if (!this.canModifyHealth) return;
      const resource = this.model.health;
      const nextValue = nextResourceValue(
        resource.current,
        delta,
        resource.maximum,
      );
      if (nextValue === null || nextValue === resource.current) return;
      await this.saveResource("health", resource.source, nextValue);
    },
    async saveResource(kind, source, nextValue) {
      if (!source || this.saving || !this.character) return;
      const sequence = ++this.saveRequestSequence;
      const campaignId = this.campaignId;
      const characterId = this.character.id;
      const userId = this.userId;
      this.saving = kind;
      this.notice = "";
      this.noticeIsError = false;
      try {
        if (source.kind === "token") {
          const resources = tokenResourcesWithValue(
            source.token,
            source.barIndex,
            nextValue,
          );
          if (!resources) throw new Error("resource_unavailable");
          await this.$store.dispatch("vtt/updateToken", {
            token: source.token,
            changes: { resources },
          });
        } else {
          const data = cloneDataWithNumber(
            this.character.data,
            source.currentPath,
            nextValue,
          );
          if (!data) throw new Error("resource_unavailable");
          const updated = await characterApiClient.update(
            campaignId,
            characterId,
            {
              name: this.character.name,
              avatarUrl: this.character.avatarUrl,
              data,
              revision: this.character.revision,
              updatedAt: this.character.updatedAt,
            },
          );
          if (
            this.mutationIsCurrent(sequence, campaignId, characterId, userId) &&
            isHudCharacterAllowed({
              character: updated,
              summary: this.selectedSummary,
              userId,
              characterId,
              canManage: this.canManage,
            })
          ) {
            this.character = updated;
          }
        }
        await this.refreshAfterSave(sequence, campaignId, characterId, userId);
        if (this.mutationIsCurrent(sequence, campaignId, characterId, userId)) {
          this.notice = this.$t("vtt.table.playerHud.notices.saved");
        }
      } catch (error) {
        if (
          !this.mutationIsCurrent(sequence, campaignId, characterId, userId)
        ) {
          return;
        }
        if (error?.status === 409 || error?.code === "character_conflict") {
          await Promise.allSettled([
            this.loadCharacter(),
            this.$store.dispatch("vtt/loadTokens"),
          ]);
          this.notice = this.$t("vtt.table.playerHud.notices.conflict");
        } else {
          this.notice = this.$t("vtt.table.playerHud.notices.saveError");
        }
        this.noticeIsError = true;
      } finally {
        if (sequence === this.saveRequestSequence) this.saving = "";
      }
    },
  },
};
</script>

<style src="./player-character-hud.css"></style>
