<!-- Responsibility: TradeModalContent shop interface component. -->
<template>
  <div class="trade-modal-content d-flex flex-column h-100 overflow-hidden">
    <div class="modal-body trade-body d-flex flex-column overflow-hidden">
      <div
        class="trade-grid flex-grow-1 overflow-hidden"
        :class="{
          'trade-grid--assortment': isAssortmentMode,
          'trade-grid--player-buy': !isGM && mobileTradeTab === 'buy',
          'trade-grid--player-sell': !isGM && mobileTradeTab === 'sell',
        }"
      >
        <!-- Main panel switches between shop views and mode-specific workspaces. -->
        <div class="d-flex justify-content-center trade-heading flex-shrink-0">
          <div class="h2 text-light lh-5 trade-name">{{ shopName }}</div>
          <div v-if="!isGM" class="player-mobile-tabs" role="tablist">
            <button
              type="button"
              role="tab"
              :aria-selected="String(mobileTradeTab === 'buy')"
              :class="{ active: mobileTradeTab === 'buy' }"
              @click="mobileTradeTab = 'buy'"
            >
              {{ $t("actions.buy") }}
            </button>
            <button
              type="button"
              role="tab"
              :aria-selected="String(mobileTradeTab === 'sell')"
              :class="{ active: mobileTradeTab === 'sell' }"
              @click="mobileTradeTab = 'sell'"
            >
              {{ $t("actions.sell") }}
            </button>
          </div>
        </div>

        <div
          class="d-flex flex-column flank trade-flank trade-flank--left align-items-center justify-content-evenly h-100 overflow-hidden"
        >
          <FlankActionButtons
            :buttons="leftFlankButtons"
            @action="handleLeftFlankAction"
          />
        </div>

        <div
          class="d-flex flex-column align-items-end trade-column trade-buy h-100 overflow-hidden"
          :class="{
            'trade-assortment-workspace-column': isAssortmentMode,
          }"
        >
          <AssortmentManager v-if="isAssortmentMode" side="workspace" />
          <QuickTransferPreview v-else-if="isAssortmentToolsMode" side="left" />
          <ShopEditor v-else-if="isShopAddEditMode" />
          <ShopView v-else />
        </div>

        <div
          v-if="!isAssortmentMode"
          class="d-flex flex-column align-items-start trade-column trade-sell h-100 overflow-hidden"
        >
          <QuickTransferPreview v-if="isAssortmentToolsMode" side="right" />
          <AssortmentManager v-else-if="isShopAddEditMode" side="editor" />
          <TemplateEditor v-else-if="showSellForm || showSellAddForm" />
          <TrashBinView v-else-if="isTrashMode" />
          <DefaultStackView v-else />
        </div>

        <div
          class="d-flex flex-column flank trade-flank trade-flank--right align-items-center justify-content-evenly h-100 overflow-hidden"
        >
          <template v-for="button in rightFlankButtons" :key="button.label">
            <div v-if="button.type === 'wallet'" class="wallet-stack">
              <div
                class="active-bg-avatar"
                :title="
                  $t('shop.common.activeHeroTitle', { name: activeBgName })
                "
              >
                <div class="active-bg-avatar__frame">
                  <img
                    class="active-bg-avatar__img"
                    :src="activeBgAvatar"
                    :alt="
                      $t('shop.common.activeHeroPortraitAlt', {
                        name: activeBgName,
                      })
                    "
                    loading="lazy"
                  />
                  <span class="active-bg-avatar__badge">{{
                    activeBgName
                  }}</span>
                </div>
              </div>
              <div class="wallet-tile">
                <div class="wallet-title">{{ button.label }}</div>
                <div class="wallet-purses">
                  <div
                    v-for="wallet in visiblePlayerWallets"
                    :key="wallet.currencyCode"
                    class="wallet-purse"
                    :class="{
                      'wallet-purse--active': wallet.isSettlementCurrency,
                    }"
                  >
                    <div class="wallet-purse__label">
                      <span>{{ wallet.label }}</span>
                      <small v-if="wallet.isSettlementCurrency">
                        {{ $t("shop.common.shopSettlementWallet") }}
                      </small>
                    </div>
                    <CurrencyDisplay
                      class="wallet-currency"
                      :brass="wallet.balance"
                      :currency-code="wallet.currencyCode"
                      variant="wallet"
                      :aria-label="`${button.label}: ${wallet.label}`"
                    />
                  </div>
                </div>
              </div>
            </div>
            <div
              v-else-if="button.type === 'encumbrance'"
              class="encumbrance-tile"
              :class="{
                'encumbrance-tile--warn':
                  button.isOverLimit || button.wouldExceedOnBuy,
              }"
              :title="button.unitName || button.label"
            >
              <div class="encumbrance-title">{{ button.label }}</div>
              <div class="encumbrance-total">
                <span class="encumbrance-current">{{ button.current }}</span>
                <span class="encumbrance-separator">/</span>
                <span class="encumbrance-limit">{{ button.limit }}</span>
              </div>
              <div class="encumbrance-unit">
                {{ button.unitShort }} | {{ button.status }}
              </div>
              <div class="encumbrance-meter">
                <span
                  class="encumbrance-meter-fill"
                  :style="{
                    width: `${Math.min(
                      100,
                      Math.round(
                        (Number(button.current || 0) * 100) /
                          Math.max(1, Number(button.limit || 1)),
                      ),
                    )}%`,
                  }"
                ></span>
              </div>
              <div class="encumbrance-meta">
                <span
                  >{{ $t("shop.common.freePrefix") }}:
                  {{ button.remaining }}</span
                >
                <span v-if="button.selection">+{{ button.selection }}</span>
              </div>
              <div v-if="button.overload" class="encumbrance-over">
                {{ $t("shop.common.overloadPrefix") }}: {{ button.overload }}
              </div>
              <div v-if="button.wouldExceedOnBuy" class="encumbrance-warning">
                {{ $t("shop.common.buyLimitWarning") }}
              </div>
            </div>
            <button
              v-else
              type="button"
              class="btn square justify-content-center"
              :class="[
                button.variantClass,
                button.extraClass,
                { active: button.active },
              ]"
              :disabled="button.disabled"
              :aria-label="button.label"
              :title="button.title || button.label"
              @click="handleRightFlankAction(button)"
            >
              {{ button.label }}
            </button>
          </template>
        </div>
      </div>
      <details v-if="!isGM && lastTradeReceipt" class="player-trade-receipt">
        <summary>
          {{ $t("shop.workspace.cart.lastTrade") }}:
          <strong>
            {{
              lastTradeReceipt.transactionType === "SELL"
                ? $t("actions.sell")
                : $t("actions.buy")
            }}
            ·
            <CurrencyDisplay
              :brass="lastTradeReceipt.totalBrass"
              :currency-code="
                lastTradeReceipt.currency || activeSettlementCurrencyCode
              "
              variant="inline"
            />
          </strong>
          · {{ $t("shop.workspace.cart.walletAfter") }}
          <CurrencyDisplay
            :brass="lastTradeReceipt.walletBrass"
            :currency-code="
              lastTradeReceipt.currency || activeSettlementCurrencyCode
            "
            variant="inline"
          />
        </summary>
        <div class="player-trade-receipt__history">
          <span v-for="entry in playerTransactions.slice(0, 5)" :key="entry.id">
            {{ entry.createdAt }} · {{ entry.transactionType }} ·
            {{ entry.itemName || "—" }} ·
            <CurrencyDisplay
              :brass="entry.totalBrass"
              :currency-code="entry.currency || activeSettlementCurrencyCode"
              variant="inline"
            />
          </span>
        </div>
      </details>
    </div>
    <TradeModalDialogs />
  </div>
</template>

<script src="./TradeModalContent.options.js"></script>

<style scoped src="./TradeModalContent.css"></style>
