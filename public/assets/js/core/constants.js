(function () {
  const Mattrics = window.Mattrics || {};
  const config = window.MATTRICS_CONFIG || {};
  const isHttp = typeof window !== "undefined" && /^https?:$/i.test(window.location.protocol);

  Mattrics.DATA_URL = config.DATA_URL || (isHttp ? "api/data.php" : "");
  Mattrics.EXERCISE_CONFIG_URL = config.EXERCISE_CONFIG_URL || (isHttp ? "api/exercises.php" : "");
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

  Mattrics.EXERCISE_MUSCLE_UI_LEVELS = [
    {
      key: "stabilizer",
      index: 0,
      label: "Stabilizer",
      meaning: "Supports movement, mainly for stability",
      weight: 0.08,
    },
    {
      key: "minor",
      index: 1,
      label: "Minor",
      meaning: "Contributes slightly, not limiting",
      weight: 0.20,
    },
    {
      key: "secondary",
      index: 2,
      label: "Secondary",
      meaning: "Clearly involved but not a main driver",
      weight: 0.45,
    },
    {
      key: "strongSecondary",
      index: 3,
      label: "Strong secondary",
      meaning: "Major assisting role, close to primary",
      weight: 0.65,
    },
    {
      key: "primary",
      index: 4,
      label: "Primary",
      meaning: "Main muscle driving the movement",
      weight: 1.00,
    },
  ];

  Mattrics.EXERCISE_MUSCLE_UI_THRESHOLDS = {
    stabilizerMax: 0.14,
    minorMax: 0.32,
    secondaryMax: 0.55,
    strongSecondaryMax: 0.82,
  };
  Mattrics.EXERCISE_MUSCLE_DEFAULT_LEVEL = "secondary";

  Mattrics.MUSCLE_FATIGUE_CONFIG = {
    windowDays: 10,
    estimatedBodyweightKg: 75,
    bodyweightLoadFactor: 0.4,
    defaultRpe: 7.5,
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
      lowerBack: 60,
      gluteal: 48,
      adductors: 60,
      quadriceps: 48,
      hamstrings: 48,
      calves: 60,
    },
    normalizationLoad: {
      chest: 3.5,
      deltoids: 2.8,
      trapezius: 2.3,
      upperBack: 3.5,
      triceps: 2.5,
      biceps: 2.5,
      abs: 5.0,
      obliques: 3.5,
      lowerBack: 2.5,
      gluteal: 7.5,
      adductors: 3.2,
      quadriceps: 5.5,
      hamstrings: 4.0,
      calves: 1.55,
    },
  };
  Mattrics.MUSCLE_FATIGUE_BODY_MAP = Mattrics.MUSCLE_FATIGUE_BODY_MAP || {};

  window.Mattrics = Mattrics;
}());
