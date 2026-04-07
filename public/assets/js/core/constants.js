(function () {
  const Mattrics = window.Mattrics || {};
  const config = window.MATTRICS_CONFIG || {};
  const isHttp = typeof window !== "undefined" && /^https?:$/i.test(window.location.protocol);

  Mattrics.DATA_URL = config.DATA_URL || (isHttp ? "api/data.php" : "");
  Mattrics.AI_PROXY_URL = config.AI_PROXY_URL || (isHttp ? "api/ai.php" : "");
  Mattrics.SHEET_URL = config.SHEET_URL || "";
  Mattrics.SHEET_TOKEN = config.SHEET_TOKEN || "";
  Mattrics.API_KEY = config.API_KEY || "";
  Mattrics.AI_ENABLED = typeof config.AI_ENABLED === "boolean"
    ? config.AI_ENABLED
    : Boolean(Mattrics.AI_PROXY_URL || Mattrics.API_KEY);

  Mattrics.TYPES = {
    Canoeing: { icon: "🛶", color: "var(--canoe)", label: "Canoeing" },
    Canoe: { icon: "🛶", color: "var(--canoe)", label: "Canoeing" },
    Run: { icon: "🏃", color: "var(--run)", label: "Run" },
    WeightTraining: { icon: "🏋️", color: "var(--lift)", label: "Weights" },
    Workout: { icon: "💪", color: "var(--workout)", label: "Workout" },
    Yoga: { icon: "🧘", color: "var(--yoga)", label: "Yoga" },
    Ride: { icon: "🚴", color: "var(--ride)", label: "Ride" },
    Walk: { icon: "🚶", color: "var(--walk)", label: "Walk" },
    Hike: { icon: "⛰️", color: "var(--hike)", label: "Hike" },
    WaterSport: { icon: "🚣", color: "var(--water)", label: "Water" },
    Rowing: { icon: "🚣", color: "var(--row)", label: "Rowing" },
    Surfing: { icon: "🏄", color: "var(--surf)", label: "Surf" },
  };

  Mattrics.MUSCLE_REGIONS = [
    { key: "chest", slug: "chest", label: "Chest", color: "var(--workout)", views: ["front"], order: 10 },
    { key: "deltoids", slug: "deltoids", label: "Deltoids", color: "var(--surf)", views: ["front", "back"], order: 20 },
    { key: "trapezius", slug: "trapezius", label: "Trapezius", color: "var(--ride)", views: ["front", "back"], order: 30 },
    { key: "upperBack", slug: "upper-back", label: "Upper back", color: "var(--row)", views: ["back"], order: 40 },
    { key: "triceps", slug: "triceps", label: "Triceps", color: "var(--canoe)", views: ["front", "back"], order: 50 },
    { key: "biceps", slug: "biceps", label: "Biceps", color: "var(--canoe)", views: ["front"], order: 60 },
    { key: "abs", slug: "abs", label: "Abs", color: "var(--yoga)", views: ["front"], order: 70 },
    { key: "obliques", slug: "obliques", label: "Obliques", color: "var(--yoga)", views: ["front"], order: 80 },
    { key: "lowerBack", slug: "lower-back", label: "Lower back", color: "var(--ride)", views: ["back"], order: 90 },
    { key: "gluteal", slug: "gluteal", label: "Gluteal", color: "var(--walk)", views: ["back"], order: 100 },
    { key: "adductors", slug: "adductors", label: "Adductors", color: "var(--hike)", views: ["front", "back"], order: 110 },
    { key: "quadriceps", slug: "quadriceps", label: "Quadriceps", color: "var(--hike)", views: ["front"], order: 120 },
    { key: "hamstrings", slug: "hamstring", label: "Hamstrings", color: "var(--walk)", views: ["back"], order: 130 },
    { key: "calves", slug: "calves", label: "Calves", color: "var(--water)", views: ["front", "back"], order: 140 },
  ];

  Mattrics.MUSCLE_FATIGUE_CONFIG = {
    windowDays: 10,
    estimatedBodyweightKg: 75,
    bodyweightLoadFactor: 0.4,
    defaultRpe: 7,
    smallThresholdRatio: 0.02,
    recoveryThresholdRatio: 0.25,
    strengthLoadUnitDivisor: 1500,
    halfLifeHours: {
      chest: 72,
      deltoids: 60,
      trapezius: 60,
      upperBack: 72,
      triceps: 48,
      biceps: 48,
      abs: 48,
      obliques: 48,
      lowerBack: 72,
      gluteal: 72,
      adductors: 72,
      quadriceps: 72,
      hamstrings: 72,
      calves: 60,
    },
    normalizationLoad: {
      chest: 2.4,
      deltoids: 2.2,
      trapezius: 1.9,
      upperBack: 2.45,
      triceps: 1.65,
      biceps: 1.55,
      abs: 1.7,
      obliques: 1.45,
      lowerBack: 1.85,
      gluteal: 2.3,
      adductors: 1.5,
      quadriceps: 2.25,
      hamstrings: 2.05,
      calves: 1.55,
    },
  };
  Mattrics.MUSCLE_FATIGUE_BODY_MAP = Mattrics.MUSCLE_FATIGUE_BODY_MAP || {};

  window.Mattrics = Mattrics;
}());
