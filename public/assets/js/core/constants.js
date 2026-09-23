(function () {
  const Mattrics = window.Mattrics || {};

  Mattrics.SESSION_URL = "/api/session";
  Mattrics.DATA_URL = "/api/data";
  Mattrics.EXERCISE_CONFIG_URL = "/api/exercises";
  Mattrics.CONNECTORS_URL = "/api/connectors";
  Mattrics.AI_PROXY_URL = "/api/ai";
  Mattrics.AI_ENABLED = true;
  Mattrics.csrfToken = "";

  Mattrics.apiFetch = function apiFetch(url, options = {}) {
    const request = { ...options, credentials: "same-origin" };
    const method = String(request.method || "GET").toUpperCase();
    const headers = new Headers(request.headers || {});
    if (!['GET', 'HEAD'].includes(method)) {
      if (!/^[a-f0-9]{64}$/i.test(Mattrics.csrfToken)) {
        return Promise.reject(new Error("The authenticated session has not finished loading."));
      }
      headers.set("X-CSRF-Token", Mattrics.csrfToken);
    }
    request.headers = headers;
    return fetch(url, request);
  };

  Mattrics.bootstrapSession = async function bootstrapSession() {
    const response = await Mattrics.apiFetch(Mattrics.SESSION_URL);
    if (!response.ok) throw new Error(`Session bootstrap failed. HTTP ${response.status}`);
    const payload = await response.json();
    if (!payload || !/^[a-f0-9]{64}$/i.test(String(payload.csrfToken || ""))) {
      throw new Error("Session bootstrap returned an invalid CSRF token.");
    }
    Mattrics.csrfToken = payload.csrfToken;
    Mattrics.appVersion = String(payload.appVersion || "");
    return payload;
  };

  Mattrics.TYPES = {
    Canoeing: { icon: "🛶", color: "var(--canoe)", label: "Canoeing" },
    Canoe: { icon: "🛶", color: "var(--canoe)", label: "Canoeing" },
    Run: { icon: "🏃", color: "var(--run)", label: "Run" },
    Padel: { icon: "🎾", color: "var(--padel)", label: "Pádel" },
    "Pádel": { icon: "🎾", color: "var(--padel)", label: "Pádel" },
    "Padel Tennis": { icon: "🎾", color: "var(--padel)", label: "Pádel" },
    "Pádel Tennis": { icon: "🎾", color: "var(--padel)", label: "Pádel" },
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
    relativeFactorMin: 0.55,
    relativeFactorMax: 1.35,
    smallThresholdRatio: 0.02,
    recoveryThresholdRatio: 0.25,
    trainableThresholdRatio: 0.5,
    strengthLoadUnitDivisor: 1500,
    systemicBaseHalfLifeHours: 24,
    systemicNormalizationLoad: 2.5,
    systemicPenaltyWeight: 0.25,
    localHalfLifeScaleMin: 0.85,
    localHalfLifeScaleMax: 1.35,
    systemicHalfLifeScaleMin: 0.9,
    systemicHalfLifeScaleMax: 1.5,
    minimumReferenceSetCount: 3,
    experienceStrengthScale: {
      Beginner: 0.85,
      Intermediate: 1.15,
      Advanced: 1.3,
    },
    fatigueArchetypeShares: {
      isolation: { localShare: 0.9, systemicShare: 0.1 },
      machine_compound: { localShare: 0.8, systemicShare: 0.2 },
      freeweight_compound: { localShare: 0.7, systemicShare: 0.3 },
      hinge_squat: { localShare: 0.6, systemicShare: 0.4 },
      conditioning_hybrid: { localShare: 0.55, systemicShare: 0.45 },
    },
    exerciseFamilyReferenceLoadKg: {
      horizontal_press: 60,
      vertical_press: 35,
      horizontal_pull: 55,
      vertical_pull: 50,
      squat: 80,
      hinge: 90,
      hip_dominant: 70,
      knee_isolation: 40,
      hip_isolation: 35,
      arm_isolation: 20,
      shoulder_isolation: 12,
      calf: 45,
      core: 25,
      conditioning_lower: 75,
    },
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
