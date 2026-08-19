export const createContentComputedPart3 = () => ({
  buyTypeFilterOptions() {
    return this.buyTradeTypeModel.options;
  },
  sellTypeFilterOptions() {
    return this.sellTradeTypeModel.options;
  },
  filteredBuyItems() {
    return this.sortTradeItems(
      this.filterTradeItemsByTypeAndSearch(
        this.buyItems,
        this.buyTypeFilter,
        this.buyTypeSearch,
      ),
      this.buySortMode,
    );
  },
  filteredSellItems() {
    return this.sortTradeItems(
      this.filterTradeItemsByTypeAndSearch(
        this.sellItems,
        this.sellTypeFilter,
        this.sellTypeSearch,
      ),
      this.sellSortMode,
    );
  },
  selectedIconTypeOptions() {
    return this.imgClassTypeOptions.map((option) => ({
      value: option.value,
      label: this.labelForType(option.value, "pl"),
      active: this.selectedIconTypeKeys.includes(option.value),
    }));
  },
  selectedIconSubtypeOptionsForChips() {
    return this.selectedIconSubtypeOptions.map((option) => ({
      value: option.value,
      label: this.labelForSubtype(option.value, "pl"),
      active: this.selectedIconSubtypeKeys.includes(option.value),
    }));
  },
  shopRemainingRecommendations() {
    const suggestionsSet = new Set(
      (this.shopSuggestions || []).map((entry) =>
        String(entry?.suggestionId || ""),
      ),
    );
    return (this.shopTemplateRecommendations || []).filter(
      (entry) => !suggestionsSet.has(String(entry?.suggestionId || "")),
    );
  },
});
