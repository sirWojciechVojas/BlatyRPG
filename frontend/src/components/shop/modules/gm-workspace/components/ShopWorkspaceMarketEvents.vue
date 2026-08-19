<template>
  <section class="profile-compact-panel profile-tab-section">
    <header class="profile-tab-section__header">
      <div>
        <b>
          {{ $t("shop.workspace.profile.events.title") }}
          <ShopHelpTooltip
            :label="$t('shop.workspace.profile.events.title')"
            :text="$t('shop.workspace.profile.help.marketEvents')"
          />
        </b>
        <small>{{ $t("shop.workspace.profile.events.description") }}</small>
      </div>
      <div class="profile-tab-section__actions">
        <select
          v-if="profileDraft.marketEvents.length"
          :value="selectedMarketEvent?.id || ''"
          :aria-label="$t('shop.workspace.profile.events.choose')"
          @change="selectedMarketEventId = $event.target.value"
        >
          <option
            v-for="event in profileDraft.marketEvents"
            :key="event.id"
            :value="event.id"
          >
            {{
              event.name ||
              $t(`shop.workspace.profile.events.types.${event.type}`)
            }}
          </option>
        </select>
        <button
          type="button"
          class="profile-mini-action"
          @click="addMarketEvent"
        >
          + {{ $t("shop.workspace.profile.events.add") }}
        </button>
      </div>
    </header>

    <p v-if="!selectedMarketEvent" class="profile-empty-row">
      {{ $t("shop.workspace.profile.events.empty") }}
    </p>
    <article v-else class="profile-market-event">
      <DenseField
        :label="$t('shop.workspace.profile.events.active')"
        :tooltip="$t('shop.workspace.profile.help.eventEnabled')"
        group
      >
        <label class="profile-market-event__enabled">
          <input
            v-model="selectedMarketEvent.enabled"
            type="checkbox"
            @change="markShopDirty"
          />
          <span>{{ $t("shop.workspace.profile.events.enabledHint") }}</span>
        </label>
      </DenseField>
      <DenseField
        :label="$t('shop.workspace.profile.events.name')"
        :tooltip="$t('shop.workspace.profile.help.eventName')"
      >
        <input
          v-model.trim="selectedMarketEvent.name"
          maxlength="120"
          @input="markShopDirty"
        />
      </DenseField>
      <DenseField
        :label="$t('shop.workspace.profile.events.kind')"
        :tooltip="$t('shop.workspace.profile.help.eventType')"
      >
        <select v-model="selectedMarketEvent.type" @change="markShopDirty">
          <option v-for="type in eventTypes" :key="type" :value="type">
            {{ $t(`shop.workspace.profile.events.types.${type}`) }}
          </option>
        </select>
      </DenseField>
      <DenseField
        :label="$t('shop.workspace.profile.events.startsAt')"
        :tooltip="$t('shop.workspace.profile.help.eventDates')"
      >
        <input
          v-model="selectedMarketEvent.startsAt"
          type="date"
          @change="markShopDirty"
        />
      </DenseField>
      <DenseField
        :label="$t('shop.workspace.profile.events.endsAt')"
        :tooltip="$t('shop.workspace.profile.help.eventDates')"
      >
        <input
          v-model="selectedMarketEvent.endsAt"
          type="date"
          @change="markShopDirty"
        />
      </DenseField>
      <DenseField
        :label="$t('shop.workspace.profile.events.price')"
        :tooltip="$t('shop.workspace.profile.help.eventPrice')"
      >
        <input
          :value="Math.round(Number(selectedMarketEvent.multiplier || 1) * 100)"
          type="number"
          min="10"
          max="500"
          @input="updateEventPrice($event.target.value)"
        />
      </DenseField>
      <DenseField
        :label="$t('shop.workspace.profile.events.availability')"
        :tooltip="$t('shop.workspace.profile.help.eventAvailability')"
      >
        <input
          v-model.number="selectedMarketEvent.availabilityDelta"
          type="number"
          min="-100"
          max="100"
          @input="markShopDirty"
        />
      </DenseField>
      <DenseField
        :label="$t('shop.workspace.profile.events.modes')"
        :tooltip="$t('shop.workspace.profile.help.eventModes')"
        group
      >
        <div class="profile-market-event__modes">
          <label v-for="mode in ['buy', 'sell']" :key="mode">
            <input
              type="checkbox"
              :checked="selectedMarketEvent.modes.includes(mode)"
              @change="
                toggleMarketEventMode(
                  selectedMarketEvent,
                  mode,
                  $event.target.checked,
                )
              "
            />
            {{ $t(`shop.shopEditor.pricing.exceptions.modes.${mode}`) }}
          </label>
        </div>
      </DenseField>
      <DenseField
        :label="$t('shop.workspace.profile.events.classes')"
        :tooltip="$t('shop.workspace.profile.help.eventClasses')"
      >
        <input
          :value="marketEventClasses(selectedMarketEvent)"
          @change="
            updateMarketEventClasses(selectedMarketEvent, $event.target.value)
          "
        />
      </DenseField>
      <DenseField
        :label="$t('shop.workspace.profile.events.genres')"
        :tooltip="$t('shop.workspace.profile.help.eventGenres')"
      >
        <input
          :value="marketEventList(selectedMarketEvent, 'itemGenres')"
          @change="
            updateMarketEventList(
              selectedMarketEvent,
              'itemGenres',
              $event.target.value,
            )
          "
        />
      </DenseField>
      <DenseField
        :label="$t('shop.workspace.profile.events.locations')"
        :tooltip="$t('shop.workspace.profile.help.eventLocations')"
      >
        <input
          :value="marketEventList(selectedMarketEvent, 'locationTypes')"
          @change="
            updateMarketEventList(
              selectedMarketEvent,
              'locationTypes',
              $event.target.value,
            )
          "
        />
      </DenseField>
      <DenseField
        :label="$t('shop.workspace.profile.events.items')"
        :tooltip="$t('shop.workspace.profile.help.eventItems')"
      >
        <input
          :value="marketEventList(selectedMarketEvent, 'templateIds')"
          @change="
            updateMarketEventList(
              selectedMarketEvent,
              'templateIds',
              $event.target.value,
            )
          "
        />
      </DenseField>
      <button
        type="button"
        class="profile-market-event__remove"
        @click="removeSelectedEvent"
      >
        {{ $t("actions.delete") }}
      </button>
    </article>
  </section>
</template>

<script>
import { computed, ref } from "vue";
import DenseField from "@/components/shop/common/DenseField.vue";
import ShopHelpTooltip from "@/components/shop/common/ShopHelpTooltip.vue";
import { useShopWorkspaceContext } from "../shopWorkspaceContext";

export default {
  name: "ShopWorkspaceMarketEvents",
  components: { DenseField, ShopHelpTooltip },
  setup() {
    const context = useShopWorkspaceContext();
    const selectedMarketEventId = ref("");
    const selectedMarketEvent = computed(
      () =>
        context.profileDraft.marketEvents.find(
          (event) => String(event.id) === String(selectedMarketEventId.value),
        ) ||
        context.profileDraft.marketEvents[0] ||
        null,
    );
    const updateEventPrice = (value) => {
      selectedMarketEvent.value.multiplier = Number(value) / 100;
      context.markShopDirty();
    };
    const removeSelectedEvent = () => {
      if (!selectedMarketEvent.value) return;
      context.removeMarketEvent(selectedMarketEvent.value.id);
      selectedMarketEventId.value = "";
    };
    return {
      ...context,
      selectedMarketEvent,
      selectedMarketEventId,
      updateEventPrice,
      removeSelectedEvent,
      eventTypes: [
        "war",
        "plague",
        "siege",
        "festival",
        "crop_failure",
        "closed_route",
        "custom",
      ],
    };
  },
};
</script>
