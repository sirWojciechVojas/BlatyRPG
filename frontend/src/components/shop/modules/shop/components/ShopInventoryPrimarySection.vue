<!-- Komponent modułu Sklep. Ten plik renderuje podstawowe pola formularza i główne dane operacyjne dla pozycji sklepowej. -->
<template>
  <div class="col-md-12 trade-form-section">
    <div class="trade-form-section-title">
      {{ $t("shop.shopView.identifiersSection") }}
    </div>
    <div class="row">
      <div class="col-md-3">
        <label class="trade-label" for="buy-ID">ID</label>
      </div>
      <div class="form-row col-md-3 p-0 m-0">
        <input
          id="buy-ID"
          v-model="ctx.localInventoryForm.ID"
          :type="ctx.fieldInputType('ID')"
          class="form-control-sm w-100 trade-input"
          name="ID"
          :readonly="ctx.isReadOnlyField('ID', 'inventoryEdit')"
          :class="{
            'is-readonly': ctx.isReadOnlyField('ID', 'inventoryEdit'),
            'is-invalid': hasInventoryError('ID'),
          }"
        />
        <div v-if="hasInventoryError('ID')" class="trade-field-error">
          {{ inventoryError("ID") }}
        </div>
      </div>
      <div class="col-md-3">
        <label class="trade-label" for="buy-INV_ID">INV_ID</label>
      </div>
      <div class="form-row col-md-3 p-0 m-0">
        <input
          id="buy-INV_ID"
          v-model="ctx.localInventoryForm.INV_ID"
          :type="ctx.fieldInputType('INV_ID')"
          class="form-control-sm w-100 trade-input"
          name="INV_ID"
          :readonly="ctx.isReadOnlyField('INV_ID', 'inventoryEdit')"
          :class="{
            'is-readonly': ctx.isReadOnlyField('INV_ID', 'inventoryEdit'),
            'is-invalid': hasInventoryError('INV_ID'),
          }"
        />
        <div v-if="hasInventoryError('INV_ID')" class="trade-field-error">
          {{ inventoryError("INV_ID") }}
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-12 trade-form-section">
    <div class="trade-form-section-title">
      {{ $t("shop.shopView.personalizationSection") }}
    </div>
    <div class="row">
      <div class="col-md-3">
        <label class="trade-label" for="buy-PERSONAL_PSEU">PERSONAL_PSEU</label>
      </div>
      <div class="form-row col-md-9 p-0 m-0">
        <input
          id="buy-PERSONAL_PSEU"
          v-model="ctx.localInventoryForm.PERSONAL_PSEU"
          :type="ctx.fieldInputType('PERSONAL_PSEU')"
          class="form-control-sm w-100 trade-input"
          name="PERSONAL_PSEU"
        />
      </div>
    </div>
    <div class="row">
      <div class="col-md-3">
        <label class="trade-label" for="buy-PERSONAL_DESC">PERSONAL_DESC</label>
      </div>
      <div class="form-row col-md-9 p-0 m-0">
        <textarea
          id="buy-PERSONAL_DESC"
          v-model="ctx.localInventoryForm.PERSONAL_DESC"
          class="form-control w-100 noR trade-textarea"
          name="PERSONAL_DESC"
          rows="5"
        ></textarea>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useTradeModalContext } from "@/components/shop/shopContext";

const ctx = useTradeModalContext();
const inventoryError = (field) =>
  String(ctx.inventoryFormErrors?.[field] || "");
const hasInventoryError = (field) => Boolean(inventoryError(field));
</script>
