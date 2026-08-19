import {
  resolveCharacterAvatar,
  resolveCharacterPortrait,
} from "@/lib/trade/characterAvatar";

export const createCoreComputedPart7 = () => {
  return {
    activeBgProfile() {
      const actor = this.actorByOwnerCode[this.activeBgOwner];
      return {
        name: actor?.name || this.activeBgOwner,
        avatar: resolveCharacterPortrait(
          actor?.assets?.portrait,
          actor?.assets?.avatar ?? actor?.avatar ?? actor?.avatarUrl,
          actor?.name || this.activeBgOwner,
        ),
      };
    },
    activeBgName() {
      return this.activeBgProfile?.name || this.activeBgOwner;
    },
    activeBgAvatar() {
      return (
        this.activeBgProfile?.avatar ||
        resolveCharacterAvatar("", this.activeBgName)
      );
    },
  };
};
