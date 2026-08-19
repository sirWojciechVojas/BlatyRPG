<!-- Responsibility: TradeZonePicker shop interface component. -->
<template>
  <div v-if="options.length" class="zone-picker-toolbar">
    <div class="zone-picker-hub">
      <div
        :ref="setHubRef"
        class="zone-picker-switcher"
        :class="{ 'is-open': panelOpen }"
        :aria-label="ariaLabel"
      >
        <button
          type="button"
          class="zone-picker-nav"
          :title="label"
          :aria-label="label"
          :disabled="disableNav"
          @click="emit('previous')"
        >
          <span aria-hidden="true">&lt;</span>
        </button>

        <button
          :id="triggerId"
          type="button"
          class="zone-picker-trigger"
          :aria-expanded="String(panelOpen)"
          :aria-controls="panelId"
          :disabled="disableTrigger"
          @click="emit('toggle')"
          @keydown.left.prevent="emit('previous')"
          @keydown.right.prevent="emit('next')"
          @keydown.down.prevent="emit('open')"
        >
          <span class="zone-picker-trigger__full">
            <span class="zone-picker-trigger__label">
              {{ activeLabel }}
            </span>
            <span class="zone-picker-trigger__count">
              {{ triggerCountText }}
            </span>
            <span class="zone-picker-trigger__ordinal">
              {{ activeOrdinal }}
            </span>
            <span
              class="zone-picker-trigger__caret"
              :class="{ 'is-open': panelOpen }"
              aria-hidden="true"
            >
              ▾
            </span>
          </span>

          <span
            v-if="showMeter"
            class="zone-picker-trigger__meter"
            aria-hidden="true"
          >
            <span
              class="zone-picker-trigger__meter-fill"
              :style="activeMeterStyle"
            ></span>
          </span>
        </button>

        <button
          type="button"
          class="zone-picker-nav"
          :title="label"
          :aria-label="label"
          :disabled="disableNav"
          @click="emit('next')"
        >
          <span aria-hidden="true">&gt;</span>
        </button>

        <transition name="zone-picker-panel">
          <div v-if="panelOpen" :id="panelId" class="zone-picker-panel">
            <input
              :id="searchId"
              :ref="setSearchInputRef"
              :value="searchValue"
              type="text"
              class="assort-select zone-picker-panel__search"
              :placeholder="searchPlaceholder"
              @input="onSearchInput"
              @keydown.esc.prevent="emit('close')"
            />

            <div v-if="hotOptions.length" class="zone-picker-quick">
              <button
                v-for="option in hotOptions"
                :key="`zone-picker-hot-${optionKey(option)}`"
                type="button"
                class="zone-picker-quick__btn"
                :class="{ active: isActive(option) }"
                :title="optionTitle(option)"
                :aria-pressed="String(isActive(option))"
                @click="emit('select', option.value, { closePanel: true })"
              >
                <span class="zone-picker-quick__code">{{
                  optionCode(option)
                }}</span>
                <span class="zone-picker-quick__count">{{
                  quickMeta(option)
                }}</span>
              </button>
            </div>

            <div class="zone-picker-panel__list" role="listbox">
              <button
                v-for="option in filteredOptions"
                :key="`zone-picker-${optionKey(option)}`"
                type="button"
                class="zone-picker-row"
                :class="rowClasses(option)"
                :title="optionTitle(option)"
                :aria-pressed="String(isActive(option))"
                @click="emit('select', option.value, { closePanel: true })"
              >
                <span class="zone-picker-row__badge">{{
                  optionCode(option)
                }}</span>
                <span class="zone-picker-row__main">
                  <span class="zone-picker-row__label">
                    {{ option.label }}
                  </span>
                  <span
                    v-if="showMeter"
                    class="zone-picker-row__meter"
                    aria-hidden="true"
                  >
                    <span
                      class="zone-picker-row__meter-fill"
                      :style="meterStyle(option)"
                    ></span>
                  </span>
                </span>
                <span class="zone-picker-row__meta">{{ rowMeta(option) }}</span>
              </button>
            </div>

            <div v-if="!filteredOptions.length" class="assort-history-info">
              {{ emptyText }}
            </div>
          </div>
        </transition>
      </div>
    </div>
  </div>
</template>

<script setup>
import { tradeZonePickerProps } from "./tradeZonePickerProps";

const props = defineProps(tradeZonePickerProps);

const emit = defineEmits([
  "previous",
  "next",
  "toggle",
  "open",
  "close",
  "select",
  "update:searchValue",
]);

function setHubRef(el) {
  if (
    props.hubRef &&
    typeof props.hubRef === "object" &&
    "value" in props.hubRef
  ) {
    Reflect.set(props.hubRef, "value", el);
  }
}

function setSearchInputRef(el) {
  if (
    props.searchInputRef &&
    typeof props.searchInputRef === "object" &&
    "value" in props.searchInputRef
  ) {
    Reflect.set(props.searchInputRef, "value", el);
  }
}

function onSearchInput(event) {
  emit("update:searchValue", event?.target?.value ?? "");
}

function rowClasses(option) {
  return {
    active: props.isActive(option),
    ...props.rowExtraClass(option),
  };
}
</script>

<style scoped src="./TradeZonePicker.css"></style>
