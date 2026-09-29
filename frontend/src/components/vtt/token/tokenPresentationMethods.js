const PRESENTATION_CLICK_DELAY = 500;
const sameTokenId = (left, right) => String(left) === String(right);

export const tokenPresentationMethods = {
  selectToken(event, tokenId) {
    if (!sameTokenId(this.hudTokenId, tokenId)) this.hudTokenId = null;
    const additive =
      event.ctrlKey === true ||
      event.metaKey === true ||
      event.shiftKey === true;
    const alreadySoleSelected =
      !additive &&
      this.effectiveSelectedIds.length === 1 &&
      sameTokenId(this.effectiveSelectedIds[0], tokenId);

    if (!alreadySoleSelected) {
      this.clearPresentationClick();
      this.expandedTokenId = null;
      this.$emit("select", { tokenId, additive });
      return;
    }
    if (Number(event.detail) >= 2 || this.isTokenExpanded(tokenId)) {
      this.clearPresentationClick();
      return;
    }

    this.clearPresentationClick();
    this.presentationClickTokenId = tokenId;
    this.presentationClickTimer = window.setTimeout(() => {
      if (
        sameTokenId(this.presentationClickTokenId, tokenId) &&
        this.effectiveSelectedIds.length === 1 &&
        sameTokenId(this.effectiveSelectedIds[0], tokenId)
      ) {
        this.expandedTokenId = tokenId;
      }
      this.presentationClickTimer = null;
      this.presentationClickTokenId = null;
    }, PRESENTATION_CLICK_DELAY);
  },
  clearPresentationClick() {
    if (this.presentationClickTimer !== null) {
      window.clearTimeout(this.presentationClickTimer);
    }
    this.presentationClickTimer = null;
    this.presentationClickTokenId = null;
  },
  isTokenExpanded(tokenId) {
    return (
      !this.hasMultiSelection &&
      this.effectiveSelectedIds.length === 1 &&
      sameTokenId(this.effectiveSelectedIds[0], tokenId) &&
      this.expandedTokenId !== null &&
      sameTokenId(this.expandedTokenId, tokenId)
    );
  },
  openTokenActorFromDoubleClick(token) {
    this.clearPresentationClick();
    this.openTokenActor(token);
  },
  toggleTokenHud(tokenId) {
    const opening = !sameTokenId(this.hudTokenId, tokenId);
    this.hudTokenId = opening ? tokenId : null;
    if (opening) {
      this.clearPresentationClick();
      this.expandedTokenId = tokenId;
      this.$emit("select", { tokenId, additive: false });
    }
  },
};
