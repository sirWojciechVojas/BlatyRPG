const validIndex = (items, index) =>
  Array.isArray(items) &&
  Number.isInteger(index) &&
  index >= 0 &&
  index < items.length;

export const cloneRegionPolygons = (polygons = []) =>
  (Array.isArray(polygons) ? polygons : []).map((polygon) =>
    (Array.isArray(polygon) ? polygon : []).map((point) => ({
      x: Number(point?.x) || 0,
      y: Number(point?.y) || 0,
    })),
  );

export const replaceRegionVertex = (
  polygons,
  polygonIndex,
  pointIndex,
  point,
) => {
  const next = cloneRegionPolygons(polygons);
  if (
    !validIndex(next, polygonIndex) ||
    !validIndex(next[polygonIndex], pointIndex)
  ) {
    return next;
  }
  next[polygonIndex][pointIndex] = {
    x: Number(point?.x) || 0,
    y: Number(point?.y) || 0,
  };
  return next;
};

export const insertRegionVertex = (
  polygons,
  polygonIndex,
  edgeIndex,
  point,
) => {
  const next = cloneRegionPolygons(polygons);
  if (
    !validIndex(next, polygonIndex) ||
    !validIndex(next[polygonIndex], edgeIndex)
  ) {
    return next;
  }
  next[polygonIndex].splice(edgeIndex + 1, 0, {
    x: Number(point?.x) || 0,
    y: Number(point?.y) || 0,
  });
  return next;
};

export const removeRegionVertex = (polygons, polygonIndex, pointIndex) => {
  const next = cloneRegionPolygons(polygons);
  if (
    !validIndex(next, polygonIndex) ||
    !validIndex(next[polygonIndex], pointIndex) ||
    next[polygonIndex].length <= 3
  ) {
    return next;
  }
  next[polygonIndex].splice(pointIndex, 1);
  return next;
};

export const regionEdgeHandles = (polygon = []) =>
  (Array.isArray(polygon) ? polygon : []).map((point, edgeIndex, points) => {
    const next = points[(edgeIndex + 1) % points.length] || point;
    return {
      edgeIndex,
      x: (Number(point?.x) + Number(next?.x)) / 2,
      y: (Number(point?.y) + Number(next?.y)) / 2,
    };
  });
