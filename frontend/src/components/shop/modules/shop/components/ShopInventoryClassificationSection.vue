<!-- Komponent modułu Sklep. Ten plik renderuje klasyfikację, parametry dodatkowe i walidację pomocniczą dla pozycji sklepowej. -->
<template>
  <div class="col-md-12 trade-form-section">
    <div class="trade-form-section-title">
      {{ $t("shop.shopView.classificationOwnershipSection") }}
    </div>

    <div class="row">
      <div class="col-md-3">
        <label class="trade-label" for="buy-ITEM_CLASS">ITEM_CLASS</label>
      </div>
      <div class="form-row align-items-center col-md-3 p-0 m-0">
        <input
          id="buy-ITEM_CLASS"
          v-model="ctx.localInventoryForm.ITEM_CLASS"
          :type="ctx.fieldInputType('ITEM_CLASS')"
          class="form-control-sm col-md-10 trade-input"
          name="ITEM_CLASS"
        />
        <TradeFieldTriggerButton field="ITEM_CLASS" target="inventory" />
      </div>

      <div class="col-md-3">
        <label class="trade-label" for="buy-IMG_CLASS">IMG_CLASS</label>
      </div>
      <div class="form-row align-items-center col-md-3 p-0 m-0">
        <input
          id="buy-IMG_CLASS"
          v-model="ctx.localInventoryForm.IMG_CLASS"
          :type="ctx.fieldInputType('IMG_CLASS')"
          class="form-control-sm col-md-10 trade-input"
          name="IMG_CLASS"
        />
        <TradeFieldTriggerButton field="IMG_CLASS" target="inventory" />
      </div>

      <div class="col-md-3">
        <label class="trade-label" for="buy-OWNER_OPT">OWNER_OPT</label>
      </div>
      <div class="form-row align-items-center col-md-3 p-0 m-0">
        <input
          id="buy-OWNER_OPT"
          v-model="ctx.localInventoryForm.OWNER_OPT"
          :type="ctx.fieldInputType('OWNER_OPT')"
          class="form-control-sm col-md-10 trade-input"
          name="OWNER_OPT"
        />
        <TradeFieldTriggerButton field="OWNER_OPT" target="inventory" />
      </div>
    </div>

    <div class="row">
      <div class="col-md-3">
        <label class="trade-label" for="buy-ITEM_PLACE">ITEM_PLACE</label>
      </div>
      <div class="form-row align-items-center col-md-3 p-0 m-0">
        <input
          id="buy-ITEM_PLACE"
          v-model="ctx.localInventoryForm.ITEM_PLACE"
          :type="ctx.fieldInputType('ITEM_PLACE')"
          class="form-control-sm col-md-10 trade-input"
          name="ITEM_PLACE"
        />
        <TradeFieldTriggerButton field="ITEM_PLACE" target="inventory" />
      </div>
    </div>

    <div
      v-if="
        String(ctx.localInventoryForm.ITEM_CLASS || '').toUpperCase() ===
        'WEAPON'
      "
      class="row"
    >
      <div class="col-md-3">
        <label class="trade-label" for="buy-ITEM_ID">ITEM_ID</label>
      </div>
      <div class="form-row align-items-center col-md-3 p-0 m-0">
        <input
          id="buy-ITEM_ID"
          v-model="ctx.localInventoryForm.ITEM_ID"
          :type="ctx.fieldInputType('ITEM_ID')"
          class="form-control-sm col-md-10 trade-input"
          name="ITEM_ID"
        />
        <TradeFieldTriggerButton field="ITEM_ID" target="inventory" />
      </div>
    </div>

    <div class="row">
      <div class="col-md-3">
        <label class="trade-label" for="buy-QUANTITY">QUANTITY</label>
      </div>
      <div class="form-row col-md-3 p-0 m-0">
        <input
          id="buy-QUANTITY"
          v-model="ctx.localInventoryForm.QUANTITY"
          :type="ctx.fieldInputType('QUANTITY')"
          class="form-control-sm w-100 trade-input"
          name="QUANTITY"
          :class="{ 'is-invalid': hasInventoryError('QUANTITY') }"
        />
        <div v-if="hasInventoryError('QUANTITY')" class="trade-field-error">
          {{ inventoryError("QUANTITY") }}
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-12 trade-form-section">
    <div class="trade-form-section-title">
      {{ $t("shop.shopView.economySection") }}
    </div>
    <div class="row">
      <div class="col-md-3">
        <label class="trade-label" for="buy-PERSONAL_COST">PERSONAL_COST</label>
      </div>
      <div class="form-row col-md-3 p-0 m-0">
        <input
          id="buy-PERSONAL_COST"
          v-model="ctx.localInventoryForm.PERSONAL_COST"
          :type="ctx.fieldInputType('PERSONAL_COST')"
          class="form-control-sm w-100 trade-input"
          name="PERSONAL_COST"
          :class="{ 'is-invalid': hasInventoryError('PERSONAL_COST') }"
        />
        <div
          v-if="hasInventoryError('PERSONAL_COST')"
          class="trade-field-error"
        >
          {{ inventoryError("PERSONAL_COST") }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import TradeFieldTriggerButton from "@/components/shop/common/TradeFieldTriggerButton.vue";
import { useTradeModalContext } from "@/components/shop/shopContext";

const ctx = useTradeModalContext();
const inventoryError = (field) =>
  String(ctx.inventoryFormErrors?.[field] || "");
const hasInventoryError = (field) => Boolean(inventoryError(field));
</script>
