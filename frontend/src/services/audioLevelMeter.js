const clamp = (value, minimum = 0, maximum = 1) =>
  Math.max(minimum, Math.min(maximum, Number(value) || 0));

const DEFAULT_FLOOR_DB = -60;
const DEFAULT_DECAY = 0.78;

export const rmsToLevel = (rms, floorDb = DEFAULT_FLOOR_DB) => {
  const amplitude = Math.max(0, Number(rms) || 0);
  if (amplitude <= 0) return 0;
  const decibels = 20 * Math.log10(amplitude);
  return clamp((decibels - floorDb) / Math.abs(floorDb));
};

export const createAudioLevelMeter = (context, options = {}) => {
  if (typeof context?.createAnalyser !== "function") return null;
  const analyser = context.createAnalyser();
  analyser.fftSize = Number(options.fftSize) || 512;
  analyser.smoothingTimeConstant = 0;
  return {
    analyser,
    samples: new Float32Array(analyser.fftSize),
    bytes: null,
    level: 0,
    floorDb: Number(options.floorDb) || DEFAULT_FLOOR_DB,
    decay: clamp(options.decay ?? DEFAULT_DECAY),
  };
};

const readRms = (meter) => {
  if (typeof meter.analyser.getFloatTimeDomainData === "function") {
    meter.analyser.getFloatTimeDomainData(meter.samples);
    let sum = 0;
    for (const sample of meter.samples) sum += sample * sample;
    return Math.sqrt(sum / meter.samples.length);
  }
  if (typeof meter.analyser.getByteTimeDomainData === "function") {
    meter.bytes ||= new Uint8Array(meter.analyser.fftSize);
    meter.analyser.getByteTimeDomainData(meter.bytes);
    let sum = 0;
    for (const sample of meter.bytes) {
      const normalized = (sample - 128) / 128;
      sum += normalized * normalized;
    }
    return Math.sqrt(sum / meter.bytes.length);
  }
  return 0;
};

export const sampleAudioLevel = (meter) => {
  if (!meter?.analyser) return 0;
  let target = 0;
  try {
    target = rmsToLevel(readRms(meter), meter.floorDb);
  } catch (_error) {
    target = 0;
  }
  const next = target >= meter.level ? target : meter.level * meter.decay;
  meter.level = next < 0.005 ? 0 : clamp(next);
  return meter.level;
};
