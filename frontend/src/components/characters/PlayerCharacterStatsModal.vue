<template>
  <section
    id="characterStats"
    class="player-character-stats-content"
    :aria-busy="loading ? 'true' : 'false'"
  >
    <div
      v-if="loading && !model"
      class="player-character-stats-modal__state"
      role="status"
    >
      <span class="spinner-border" aria-hidden="true"></span>
      <span>{{ $t("vtt.table.characterStats.loading") }}</span>
    </div>

    <div
      v-else-if="loadError && !model"
      class="player-character-stats-modal__state player-character-stats-modal__state--error"
      role="alert"
    >
      <span>{{ loadError }}</span>
      <button type="button" @click="loadCharacter">
        {{ $t("vtt.table.characterStats.retry") }}
      </button>
    </div>

    <div v-else-if="model" class="character-stats-layout cStatsBody">
      <CharacterStatsEquipmentPanel
        :model="model"
        :avatar="avatar"
        :inventory-items="inventoryItems"
        :template-items="templateItems"
        :can-edit-inventory="canEditInventory"
        :moving="inventorySaving"
        :inventory-error="inventoryError"
        :active-weapon-set="activeWeaponSet"
        :weapon-set-saving="weaponSetSaving"
        :avatar-alt="
          $t('vtt.table.characterStats.avatarAlt', { name: model.name })
        "
        @move-items="moveInventoryItems"
        @consume-item="consumeInventoryItem"
        @activate-weapon-set="activateWeaponSet"
      />
      <CharacterStatsTraitsPanel :model="model" />
      <CharacterStatsDetailsPanel :model="model" />
    </div>
  </section>
</template>

<script>
import CharacterStatsDetailsPanel from "@/components/characters/stats/CharacterStatsDetailsPanel.vue";
import CharacterStatsEquipmentPanel from "@/components/characters/stats/CharacterStatsEquipmentPanel.vue";
import CharacterStatsTraitsPanel from "@/components/characters/stats/CharacterStatsTraitsPanel.vue";
import { characterApiClient } from "@/lib/character/characterApiClient";
import { characterErrorKey } from "@/lib/character/characterErrorKey";
import { createPlayerCharacterStatsModel } from "@/components/characters/playerCharacterStatsModel";
import { resolveCharacterPortrait } from "@/lib/trade/characterAvatar";
import { shopApiClient } from "@/lib/trade/shop-api-client";
import { shopOwnerCodeForCharacter } from "@/components/vtt/table/playerCharacterHudModel";

export default {
  name: "PlayerCharacterStatsContent",
  components: {
    CharacterStatsDetailsPanel,
    CharacterStatsEquipmentPanel,
    CharacterStatsTraitsPanel,
  },
  props: {
    campaignId: { type: [Number, String], required: true },
    characterId: { type: [Number, String], default: null },
  },
  data: () => ({
    character: null,
    loading: false,
    loadError: "",
    requestSequence: 0,
    inventoryItems: [],
    templateItems: [],
    inventoryOwnerCode: "",
    canEditInventory: false,
    inventorySaving: false,
    weaponSetSaving: false,
    inventoryError: "",
    loadedCampaignId: null,
  }),
  computed: {
    model() {
      return this.character
        ? createPlayerCharacterStatsModel(this.character)
        : null;
    },
    avatar() {
      return resolveCharacterPortrait(
        this.character?.assets?.portrait || this.character,
        this.character,
        this.character?.name,
      );
    },
    activeWeaponSet() {
      const data = this.character?.data || {};
      const configured =
        data?.inventory?.activeWeaponSet ??
        data?.inventory?.active_weapon_set ??
        data?.activeWeaponSet ??
        data?.active_weapon_set;
      return Number(configured) === 2 ? 2 : 1;
    },
  },
  watch: {
    campaignId() {
      this.loadCharacter();
    },
    characterId() {
      this.loadCharacter();
    },
  },
  mounted() {
    this.loadCharacter();
  },
  beforeUnmount() {
    this.requestSequence += 1;
  },
  methods: {
    async loadCharacter() {
      const campaignId = Number(this.campaignId);
      const characterId = Number(this.characterId);
      if (!Number.isInteger(campaignId) || !Number.isInteger(characterId)) {
        this.character = null;
        this.inventoryItems = [];
        this.templateItems = [];
        this.inventoryOwnerCode = "";
        this.canEditInventory = false;
        this.loading = false;
        return;
      }
      const alreadyLoaded =
        Number(this.character?.id) === characterId &&
        Number(this.loadedCampaignId) === campaignId &&
        !this.loadError;
      if (alreadyLoaded) return;

      const isChangingCharacter =
        this.character &&
        (Number(this.character.id) !== characterId ||
          Number(this.loadedCampaignId) !== campaignId);
      if (isChangingCharacter) {
        this.character = null;
        this.inventoryItems = [];
        this.templateItems = [];
        this.inventoryOwnerCode = "";
        this.canEditInventory = false;
      }

      const sequence = ++this.requestSequence;
      this.inventoryError = "";
      this.loadError = "";
      this.loading = !this.character;
      try {
        const [character, access] = await Promise.all([
          characterApiClient.get(campaignId, characterId),
          shopApiClient.getAccessOptions({ campaignId }),
        ]);
        if (!this.isCurrentRequest(sequence, campaignId, characterId)) return;
        if (Number(character?.id) !== characterId) {
          throw new Error("character_identity_mismatch");
        }
        this.character = character;
        this.loadedCampaignId = campaignId;
        const ownerCode = shopOwnerCodeForCharacter(access, characterId);
        if (!ownerCode) return;
        const bootstrap = await shopApiClient.bootstrap({
          campaignId,
          ownerCode,
          viewMode: "character",
        });
        if (!this.isCurrentRequest(sequence, campaignId, characterId)) return;
        this.inventoryItems = Array.isArray(bootstrap?.inventoryItems)
          ? bootstrap.inventoryItems
          : [];
        this.templateItems = Array.isArray(bootstrap?.templateItems)
          ? bootstrap.templateItems
          : [];
        this.inventoryOwnerCode = ownerCode;
        this.canEditInventory =
          access?.modes?.gm === true || access?.modes?.player === true;
      } catch (error) {
        if (this.isCurrentRequest(sequence, campaignId, characterId)) {
          this.loadError = this.$t(characterErrorKey(error, "load"));
        }
      } finally {
        if (this.isCurrentRequest(sequence, campaignId, characterId)) {
          this.loading = false;
        }
      }
    },
    async moveInventoryItems(moves = [], options = {}) {
      if (
        this.inventorySaving ||
        !this.canEditInventory ||
        !this.inventoryOwnerCode ||
        !Array.isArray(moves) ||
        !moves.length
      ) {
        return;
      }
      const campaignId = Number(this.campaignId);
      const characterId = Number(this.characterId);
      const sequence = this.requestSequence;
      const previousInventoryItems = this.inventoryItems;
      this.inventoryError = "";
      this.inventorySaving = true;
      this.inventoryItems = this.applyInventoryMoves(moves);
      try {
        await Promise.all(
          moves.map(({ item, slot }) =>
            shopApiClient.updateItemInstance(
              { campaignId, ownerCode: this.inventoryOwnerCode },
              Number(item?.ID),
              { slot, itemPlace: slot },
            ),
          ),
        );
        if ([1, 2].includes(Number(options?.activeWeaponSet))) {
          await this.activateWeaponSet(Number(options.activeWeaponSet));
        }
      } catch (error) {
        if (this.isCurrentRequest(sequence, campaignId, characterId)) {
          this.inventoryItems = previousInventoryItems;
          this.inventoryError = this.$t(
            "vtt.table.characterStats.inventory.saveError",
          );
        }
      } finally {
        if (this.isCurrentRequest(sequence, campaignId, characterId)) {
          this.inventorySaving = false;
        }
      }
    },
    async activateWeaponSet(set) {
      const normalized = Number(set) === 2 ? 2 : 1;
      if (
        this.weaponSetSaving ||
        !this.canEditInventory ||
        !this.character ||
        normalized === this.activeWeaponSet
      ) {
        return;
      }

      const campaignId = Number(this.campaignId);
      const characterId = Number(this.characterId);
      const sequence = this.requestSequence;
      const previousCharacter = this.character;
      const data = previousCharacter.data || {};
      const nextCharacter = {
        ...previousCharacter,
        data: {
          ...data,
          inventory: {
            ...(data.inventory || {}),
            activeWeaponSet: normalized,
          },
        },
      };

      this.weaponSetSaving = true;
      this.inventoryError = "";
      this.character = nextCharacter;
      try {
        const updated = await characterApiClient.update(
          campaignId,
          characterId,
          nextCharacter,
        );
        if (this.isCurrentRequest(sequence, campaignId, characterId)) {
          this.character = updated;
        }
      } catch (error) {
        if (this.isCurrentRequest(sequence, campaignId, characterId)) {
          this.character = previousCharacter;
          this.inventoryError = this.$t(
            "vtt.table.characterStats.inventory.weaponSets.saveError",
          );
        }
      } finally {
        this.weaponSetSaving = false;
      }
    },
    async consumeInventoryItem(item) {
      if (
        this.inventorySaving ||
        !this.canEditInventory ||
        !item?.CONSUMPTION
      ) {
        return;
      }
      if (
        item.CONSUMPTION.requiresConfirmation &&
        !window.confirm(
          this.$t("vtt.table.characterStats.inventory.consumeConfirmation"),
        )
      ) {
        return;
      }
      this.inventorySaving = true;
      this.inventoryError = "";
      try {
        const result = await shopApiClient.consumeItem(
          {
            campaignId: Number(this.campaignId),
            ownerCode: this.inventoryOwnerCode,
          },
          {
            itemKind: item.ITEM_RECORD_KIND,
            itemId: Number(item.ID),
            confirmed: true,
          },
          this.consumptionRequestKey(),
        );
        if (result?.character) {
          this.character = { ...this.character, ...result.character };
        }
        this.applyConsumptionResult(item, result || {});
      } catch (error) {
        this.inventoryError =
          error?.message ||
          this.$t("vtt.table.characterStats.inventory.consumeError");
      } finally {
        this.inventorySaving = false;
      }
    },
    consumptionRequestKey() {
      if (typeof crypto !== "undefined" && crypto.randomUUID) {
        return crypto.randomUUID();
      }
      return `consume-${Date.now()}-${Math.random().toString(36).slice(2)}`;
    },
    applyConsumptionResult(item, result) {
      const id = Number(item?.ID);
      const remaining = Math.max(0, Number(result?.remainingPortions) || 0);
      this.inventoryItems = this.inventoryItems.flatMap((entry) => {
        if (Number(entry?.ID) !== id) return [entry];
        if (remaining < 1) return [];
        return [
          {
            ...entry,
            QUANTITY: remaining,
            CONSUMPTION: result.consumption || {
              ...entry.CONSUMPTION,
              portions: remaining,
            },
          },
        ];
      });
      if (Array.isArray(result?.events) && result.events.length) {
        this.inventoryError = result.events.join(" ");
      }
    },
    applyInventoryMoves(moves) {
      const slotsByItemId = new Map(
        moves
          .map(({ item, slot }) => [Number(item?.ID), String(slot || "")])
          .filter(
            ([itemId, slot]) => Number.isInteger(itemId) && itemId > 0 && slot,
          ),
      );
      if (!slotsByItemId.size) return this.inventoryItems;

      return this.inventoryItems.map((item) => {
        const slot = slotsByItemId.get(Number(item?.ID));
        if (!slot) return item;

        return {
          ...item,
          SLOT: slot,
          ITEM_PLACE: slot,
          INSTANCE_META: {
            ...(item?.INSTANCE_META || {}),
            SLOT: slot,
            ITEM_PLACE: slot,
          },
        };
      });
    },
    isCurrentRequest(sequence, campaignId, characterId) {
      return (
        sequence === this.requestSequence &&
        campaignId === Number(this.campaignId) &&
        characterId === Number(this.characterId)
      );
    },
  },
};
</script>

<style src="./styles/PlayerCharacterStatsModal.css"></style>
