<template>
  <Teleport to="body">
    <Transition name="character-stats-item-dialog">
      <div
        v-if="item"
        class="character-stats-item-dialog__backdrop"
        @mousedown.self="$emit('close')"
      >
        <section
          ref="dialog"
          class="character-stats-item-dialog"
          role="dialog"
          aria-modal="true"
          :aria-label="
            $t('vtt.table.characterStats.inventory.itemDialog.aria', {
              name: displayName,
            })
          "
        >
          <header class="character-stats-item-dialog__header">
            <h3>{{ displayName }}</h3>
            <button
              ref="closeButton"
              type="button"
              :aria-label="
                $t('vtt.table.characterStats.inventory.itemDialog.close')
              "
              @click="$emit('close')"
            >
              <span aria-hidden="true">×</span>
            </button>
          </header>

          <div class="character-stats-item-dialog__body">
            <div class="character-stats-item-dialog__summary">
              <div class="character-stats-item-dialog__icon-frame">
                <ItemIcon :item="item" :size="96" />
                <span
                  v-if="quantity > 1"
                  class="character-stats-item-dialog__quantity"
                >
                  ×{{ quantity }}
                </span>
              </div>

              <dl class="character-stats-item-dialog__metadata">
                <template v-for="row in metadataRows" :key="row.key">
                  <dt>{{ row.label }}</dt>
                  <dd>
                    <CurrencyDisplay
                      v-if="row.type === 'currency'"
                      :brass="row.value"
                      :currency-code="currencyCode"
                      variant="inline"
                    />
                    <span v-else>{{ row.value }}</span>
                  </dd>
                </template>
              </dl>
            </div>

            <section
              v-if="weaponRows.length"
              class="character-stats-item-dialog__section"
            >
              <h4>
                {{ $t("vtt.table.characterStats.inventory.itemDialog.weapon") }}
              </h4>
              <dl class="character-stats-item-dialog__weapon">
                <template v-for="row in weaponRows" :key="row.key">
                  <dt>{{ row.label }}</dt>
                  <dd>{{ row.value }}</dd>
                </template>
              </dl>
            </section>

            <section class="character-stats-item-dialog__section">
              <h4>
                {{
                  $t(
                    "vtt.table.characterStats.inventory.itemDialog.description",
                  )
                }}
              </h4>
              <p
                v-for="(paragraph, index) in descriptionParts"
                :key="`${paragraph}-${index}`"
              >
                {{ paragraph }}
              </p>
              <p v-if="!descriptionParts.length" class="is-empty">
                {{ $t("vtt.table.characterStats.inventory.noDescription") }}
              </p>
            </section>

            <section
              v-if="consumption"
              class="character-stats-item-dialog__section"
            >
              <h4>
                {{
                  $t(
                    "vtt.table.characterStats.inventory.itemDialog.consumption.title",
                  )
                }}
              </h4>
              <p class="character-stats-item-dialog__consumption-help">
                {{
                  $t(
                    "vtt.table.characterStats.inventory.itemDialog.consumption.mechanicsHelp",
                  )
                }}
              </p>
              <dl class="character-stats-item-dialog__metadata">
                <dt>
                  {{
                    $t(
                      "vtt.table.characterStats.inventory.itemDialog.consumption.portions",
                    )
                  }}
                </dt>
                <dd>{{ consumption.portions }}</dd>
                <template v-if="consumption.effect">
                  <dt>
                    {{
                      $t(
                        "vtt.table.characterStats.inventory.itemDialog.consumption.effect",
                      )
                    }}
                  </dt>
                  <dd>{{ consumption.effect }}</dd>
                </template>
                <template v-if="consumption.effectWindow">
                  <dt>
                    {{
                      $t(
                        "vtt.table.characterStats.inventory.itemDialog.consumption.effectWindow",
                      )
                    }}
                  </dt>
                  <dd>{{ consumption.effectWindow }}</dd>
                </template>
                <dt>
                  {{
                    $t(
                      "vtt.table.characterStats.inventory.itemDialog.consumption.nutrition",
                    )
                  }}
                </dt>
                <dd>
                  {{ consumption.satietyHours }} h /
                  {{ consumption.hydrationHours }} h
                </dd>
                <dt>
                  {{
                    $t(
                      "vtt.table.characterStats.inventory.itemDialog.consumption.time",
                    )
                  }}
                </dt>
                <dd>{{ consumption.consumeTime || "—" }}</dd>
                <dt>
                  {{
                    $t(
                      "vtt.table.characterStats.inventory.itemDialog.consumption.combat",
                    )
                  }}
                </dt>
                <dd>
                  {{
                    consumption.usableInCombat
                      ? $t(
                          "vtt.table.characterStats.inventory.itemDialog.consumption.combatYes",
                        )
                      : $t(
                          "vtt.table.characterStats.inventory.itemDialog.consumption.combatNo",
                        )
                  }}
                </dd>
                <template v-if="consumption.risk">
                  <dt>
                    {{
                      $t(
                        "vtt.table.characterStats.inventory.itemDialog.consumption.risk",
                      )
                    }}
                  </dt>
                  <dd>{{ consumption.risk }}</dd>
                </template>
                <dt>
                  {{
                    $t(
                      "vtt.table.characterStats.inventory.itemDialog.consumption.basePrice",
                    )
                  }}
                </dt>
                <dd>{{ consumption.basePricePennies }} p</dd>
              </dl>
            </section>
          </div>

          <footer
            class="character-stats-item-dialog__footer"
            :class="{
              'character-stats-item-dialog__footer--with-consumption':
                consumeActionLabel,
            }"
          >
            <button
              v-if="consumeActionLabel"
              type="button"
              class="character-stats-item-dialog__consume-action"
              :disabled="consumeActionDisabled || actionBusy"
              :title="consumeActionHint || null"
              @click="$emit('consume-action')"
            >
              {{ consumeActionLabel }}
            </button>
            <button
              type="button"
              :disabled="actionDisabled || actionBusy"
              :title="actionHint || null"
              @click="$emit('primary-action')"
            >
              {{ actionLabel }}
            </button>
          </footer>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<script>
import CurrencyDisplay from "@/components/trade/CurrencyDisplay.vue";
import ItemIcon from "@/components/shop/common/ItemIcon.vue";

const textValue = (value) => String(value ?? "").trim();

const readableValue = (value) => {
  if (value && typeof value === "object") {
    return textValue(value.label || value.name || value.code || value.value);
  }
  return textValue(value);
};

const hasOwnValue = (source, key) =>
  Object.prototype.hasOwnProperty.call(source || {}, key) &&
  source[key] !== null &&
  source[key] !== undefined &&
  textValue(source[key]) !== "";

export default {
  name: "CharacterStatsItemDialog",
  components: { CurrencyDisplay, ItemIcon },
  props: {
    item: { type: Object, default: null },
    actionLabel: { type: String, default: "" },
    actionDisabled: { type: Boolean, default: false },
    actionHint: { type: String, default: "" },
    actionBusy: { type: Boolean, default: false },
    consumeActionLabel: { type: String, default: "" },
    consumeActionDisabled: { type: Boolean, default: false },
    consumeActionHint: { type: String, default: "" },
  },
  emits: ["close", "primary-action", "consume-action"],
  computed: {
    displayName() {
      return textValue(this.item?.NAME || this.item?.PERSONAL_PSEU) || "—";
    },
    quantity() {
      const value = Number(this.item?.QUANTITY);
      return Number.isFinite(value) ? Math.max(1, Math.round(value)) : 1;
    },
    currencyCode() {
      return textValue(
        this.item?.ACTIVE_CURRENCY ||
          this.item?.CURRENCY ||
          this.item?.currency ||
          "wfrp_empire",
      );
    },
    metadataRows() {
      if (!this.item) return [];
      const rows = [];
      const add = (key, label, value) => {
        const formatted = readableValue(value);
        if (formatted) rows.push({ key, label, value: formatted });
      };

      add(
        "class",
        this.$t("vtt.table.characterStats.inventory.itemDialog.itemClass"),
        this.item.ITEM_CLASS || this.item.itemClass,
      );
      add(
        "genre",
        this.$t("vtt.table.characterStats.inventory.itemDialog.genre"),
        this.item.ITEM_GENRE || this.item.itemGenre,
      );
      if (this.quantity > 1) {
        add(
          "quantity",
          this.$t("vtt.table.characterStats.inventory.itemDialog.quantity"),
          this.quantity,
        );
      }
      if (
        hasOwnValue(this.item, "CHARGE") ||
        hasOwnValue(this.item, "charge")
      ) {
        add(
          "charge",
          this.$t("vtt.table.characterStats.inventory.itemDialog.charge"),
          this.item.CHARGE ?? this.item.charge,
        );
      }
      const rawPrice = Number(
        this.item.ACTIVE_PRICE ??
          this.item.PERSONAL_COST ??
          this.item.PRIZE ??
          this.item.price,
      );
      if (Number.isFinite(rawPrice)) {
        rows.push({
          key: "value",
          label: this.$t("vtt.table.characterStats.inventory.itemDialog.value"),
          value: Math.max(0, rawPrice),
          type: "currency",
        });
      }
      const attributes = [
        ...(Array.isArray(this.item.ATTRIBUTES) ? this.item.ATTRIBUTES : []),
        ...(Array.isArray(this.item.attributes) ? this.item.attributes : []),
      ]
        .map(readableValue)
        .filter(Boolean);
      if (attributes.length) {
        add(
          "attributes",
          this.$t("vtt.table.characterStats.inventory.itemDialog.attributes"),
          [...new Set(attributes)].join(", "),
        );
      }
      return rows;
    },
    weaponRows() {
      const weapon = this.item?.WEAPON || this.item?.weapon || {};
      if (!weapon || typeof weapon !== "object") return [];
      const rows = [];
      const add = (key, label, value) => {
        const formatted = readableValue(value);
        if (formatted) rows.push({ key, label, value: formatted });
      };
      add(
        "type",
        this.$t("vtt.table.characterStats.inventory.itemDialog.weaponType"),
        weapon.TYPE || weapon.type,
      );
      add(
        "handed",
        this.$t("vtt.table.characterStats.inventory.itemDialog.handed"),
        weapon.HANDED || weapon.handed,
      );
      add(
        "category",
        this.$t("vtt.table.characterStats.inventory.itemDialog.category"),
        weapon.CATEGORY || weapon.category,
      );
      const damage = [
        weapon.DAMAGE || weapon.damage,
        weapon.DICE || weapon.dice,
      ]
        .map(textValue)
        .filter(Boolean)
        .join(" ");
      add(
        "damage",
        this.$t("vtt.table.characterStats.inventory.itemDialog.damage"),
        damage,
      );
      add(
        "range",
        this.$t("vtt.table.characterStats.inventory.itemDialog.range"),
        weapon.RANGE || weapon.range,
      );
      add(
        "reload",
        this.$t("vtt.table.characterStats.inventory.itemDialog.reload"),
        weapon.RELOAD || weapon.reload,
      );
      add(
        "qualities",
        this.$t("vtt.table.characterStats.inventory.itemDialog.qualities"),
        weapon.QUALITIES || weapon.qualities,
      );
      add(
        "load",
        this.$t("vtt.table.characterStats.inventory.itemDialog.load"),
        weapon.LOAD || weapon.load,
      );
      return rows;
    },
    descriptionParts() {
      const values = [
        this.item?.DESCRIPTION,
        this.item?.DETAILS,
        this.item?.PERSONAL_DESC,
        this.item?.description,
        this.item?.details,
        this.item?.personalDesc,
      ]
        .map(textValue)
        .filter(Boolean);
      return [...new Set(values)];
    },
    consumption() {
      const value = this.item?.CONSUMPTION || this.item?.consumption;
      return value && typeof value === "object" ? value : null;
    },
  },
  watch: {
    item(value) {
      if (value) this.$nextTick(() => this.$refs.closeButton?.focus());
    },
  },
  mounted() {
    document.addEventListener("keydown", this.handleKeydown);
  },
  beforeUnmount() {
    document.removeEventListener("keydown", this.handleKeydown);
  },
  methods: {
    handleKeydown(event) {
      if (this.item && event.key === "Escape") {
        event.preventDefault();
        this.$emit("close");
      }
    },
  },
};
</script>
