export const sceneErrorMessage = (translate, error) => {
  if (error?.code === "movement_points_depleted") {
    return translate("vtt.scene.errors.movementPointsDepleted");
  }
  if (error?.code === "movement_group_blocked") {
    return translate("vtt.scene.errors.movementGroupBlocked", {
      names: error.details?.names || "—",
    });
  }
  if (error?.network) return translate("vtt.scene.errors.network");
  if (error?.status === 409) return translate("vtt.scene.errors.conflict");
  const details = error?.details;
  if (error?.status === 422 && details) {
    const fields = Object.keys(details).map((field) => {
      const camel = field.replace(/_([a-z])/g, (_match, char) =>
        char.toUpperCase(),
      );
      const key = `vtt.scene.fields.${camel}`;
      const label = translate(key);
      return label === key ? field : label;
    });
    return translate("vtt.scene.errors.validation", {
      fields: fields.join(", "),
    });
  }
  return translate("vtt.scene.errors.generic");
};
