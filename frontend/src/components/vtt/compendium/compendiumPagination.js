export const nextCompendiumPage = (currentPage, append) =>
  append ? Math.max(1, Number(currentPage) || 1) + 1 : 1;

export const shouldLoadNextCompendiumPage = (list, threshold = 160) => {
  if (!list || list.clientHeight < 1) return false;
  const remaining = list.scrollHeight - list.scrollTop - list.clientHeight;
  return remaining <= threshold;
};
