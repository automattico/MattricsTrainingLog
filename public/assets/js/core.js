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
    recoveryThresholdRatio: 0.4,
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
      gluteal: 1.9,
      adductors: 1.5,
      quadriceps: 2.25,
      hamstrings: 2.05,
      calves: 1.55,
    },
  };
  Mattrics.MUSCLE_FATIGUE_BODY_MAP = Mattrics.MUSCLE_FATIGUE_BODY_MAP || {};

  Mattrics.state = {
    allData: [],
    windowDays: 7,
    typeFilter: "All",
    groupBy: "week",
    feedMode: "list",
    recent: [],
    currentFatigue: null,
  };

  Mattrics.normalizeDateValue = function normalizeDateValue(value) {
    if (value instanceof Date && !Number.isNaN(value.getTime())) {
      return Mattrics.toIsoDate(value);
    }

    const text = String(value || "").trim();
    if (!text) return "";

    const isoMatch = text.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (isoMatch) {
      return `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]}`;
    }

    const slashMatch = text.match(/^(\d{4})\/(\d{2})\/(\d{2})/);
    if (slashMatch) {
      return `${slashMatch[1]}-${slashMatch[2]}-${slashMatch[3]}`;
    }

    const nativeDate = new Date(text);
    if (!Number.isNaN(nativeDate.getTime())) {
      return Mattrics.toIsoDate(nativeDate);
    }

    return "";
  };

  Mattrics.parseDate = function parseDate(ds) {
    const normalized = Mattrics.normalizeDateValue(ds);
    const [year, month, day] = normalized.split("-").map(Number);
    return new Date(year || 1970, ((month || 1) - 1), day || 1);
  };

  Mattrics.startOfDay = function startOfDay(date = new Date()) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  };

  Mattrics.toIsoDate = function toIsoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  };

  Mattrics.shiftDate = function shiftDate(ds, deltaDays) {
    const date = typeof ds === "string" ? Mattrics.parseDate(ds) : new Date(ds);
    date.setDate(date.getDate() + deltaDays);
    return Mattrics.toIsoDate(date);
  };

  Mattrics.diffDays = function diffDays(later, earlier) {
    const laterDay = Mattrics.startOfDay(later);
    const earlierDay = Mattrics.startOfDay(earlier);
    const laterUtc = Date.UTC(laterDay.getFullYear(), laterDay.getMonth(), laterDay.getDate());
    const earlierUtc = Date.UTC(earlierDay.getFullYear(), earlierDay.getMonth(), earlierDay.getDate());
    return Math.round((laterUtc - earlierUtc) / 86400000);
  };

  Mattrics.tc = function tc(type) {
    return Mattrics.TYPES[type] || { icon: "⚡", color: "var(--muted)", label: type };
  };

  Mattrics.canonicalType = function canonicalType(type) {
    if (type === "Canoe") return "Canoeing";
    if (type === "WaterSport") return "Rowing";
    return type;
  };

  Mattrics.escAttr = function escAttr(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  };

  Mattrics.fmt = function fmt(min) {
    const rounded = Math.round(parseFloat(min));
    if (!rounded) return "—";
    return rounded < 60 ? `${rounded}m` : `${Math.floor(rounded / 60)}h${rounded % 60 ? ` ${rounded % 60}m` : ""}`;
  };

  Mattrics.fmtDate = function fmtDate(ds) {
    return Mattrics.parseDate(ds).toLocaleDateString("en-GB", {
      weekday: "short",
      day: "numeric",
      month: "short",
      year: "numeric",
    });
  };

  Mattrics.fmtShort = function fmtShort(ds) {
    return Mattrics.parseDate(ds).toLocaleDateString("en-GB", { day: "numeric", month: "short" });
  };

  Mattrics.formatContextRange = function formatContextRange(startDs, endDs) {
    if (!startDs || !endDs) return "";

    const start = Mattrics.parseDate(startDs);
    const end = Mattrics.parseDate(endDs);

    if (start.toDateString() === end.toDateString()) {
      return start.toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" });
    }

    const sameYear = start.getFullYear() === end.getFullYear();
    const sameMonth = sameYear && start.getMonth() === end.getMonth();

    if (sameMonth) {
      return `${start.getDate()}-${end.toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" })}`;
    }

    if (sameYear) {
      return `${start.toLocaleDateString("en-GB", { day: "numeric", month: "short" })} to ${end.toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" })}`;
    }

    return `${start.toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" })} to ${end.toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" })}`;
  };

  Mattrics.formatWeekRange = function formatWeekRange(startIso) {
    const start = Mattrics.parseDate(startIso);
    const end = Mattrics.parseDate(Mattrics.shiftDate(startIso, 6));
    return `${start.toLocaleDateString("en-GB", { day: "numeric", month: "short" })} - ${end.toLocaleDateString("en-GB", { day: "numeric", month: "short" })}`;
  };

  Mattrics.weekStart = function weekStart(ds) {
    const date = Mattrics.parseDate(ds);
    const day = date.getDay();
    const diff = date.getDate() - day + (day === 0 ? -6 : 1);
    const start = new Date(date);
    start.setDate(diff);
    return Mattrics.toIsoDate(start);
  };

  Mattrics.getActivityId = function getActivityId(activity) {
    return String(activity["Activity ID raw"] || activity["Activity ID"] || activity.Name || "");
  };

  Mattrics.getWindowRange = function getWindowRange() {
    if (!Mattrics.state.allData.length) return { start: "", end: "" };

    if (Mattrics.state.windowDays === 0) {
      return {
        start: Mattrics.state.allData[Mattrics.state.allData.length - 1].Date,
        end: Mattrics.state.allData[0].Date,
      };
    }

    const end = Mattrics.startOfDay(new Date());
    const start = new Date(end);
    start.setDate(start.getDate() - (Mattrics.state.windowDays - 1));
    return {
      start: Mattrics.toIsoDate(start),
      end: Mattrics.toIsoDate(end),
    };
  };

  Mattrics.getRollingPeriod = function getRollingPeriod(ds, periodDays) {
    const today = Mattrics.startOfDay(new Date());
    const activityDate = Mattrics.parseDate(ds);
    const distance = Math.max(0, Mattrics.diffDays(today, activityDate));
    const bucket = Math.floor(distance / periodDays);
    const end = new Date(today);
    end.setDate(end.getDate() - (bucket * periodDays));
    const start = new Date(end);
    start.setDate(start.getDate() - (periodDays - 1));
    return {
      key: Mattrics.toIsoDate(start),
      start: Mattrics.toIsoDate(start),
      end: Mattrics.toIsoDate(end),
    };
  };

  Mattrics.esc = function esc(value) {
    return (value || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
  };

  Mattrics.applyTypeFilter = function applyTypeFilter(data) {
    if (Mattrics.state.typeFilter === "All") return data;
    return data.filter((activity) => Mattrics.canonicalType(activity.Type) === Mattrics.state.typeFilter);
  };

  Mattrics.getWindowedData = function getWindowedData() {
    if (Mattrics.state.windowDays === 0) return Mattrics.state.allData;
    const { start } = Mattrics.getWindowRange();
    return Mattrics.state.allData.filter((activity) => activity.Date >= start);
  };

  Mattrics.getActivityMix = function getActivityMix(activities, limit = 5) {
    const counts = {};
    activities.forEach((activity) => {
      const type = Mattrics.canonicalType(activity.Type);
      counts[type] = (counts[type] || 0) + 1;
    });

    const sorted = Object.entries(counts)
      .sort((a, b) => b[1] - a[1])
      .map(([type, count]) => {
        const cfg = Mattrics.tc(type);
        return {
          type,
          label: cfg.label,
          icon: cfg.icon,
          color: cfg.color,
          count,
        };
      });

    const visible = sorted.slice(0, limit);
    const otherCount = sorted.slice(limit).reduce((sum, item) => sum + item.count, 0);
    if (otherCount) {
      visible.push({
        type: "Other",
        label: "Other",
        icon: "•",
        color: "var(--muted)",
        count: otherCount,
      });
    }

    const total = activities.length || 1;
    const segments = visible.map((item) => ({
      ...item,
      pct: item.count / total,
      percentLabel: `${Math.round(item.count / total * 100)}%`,
    }));

    return {
      total: activities.length,
      segments,
      dominant: segments[0] || null,
      counts,
    };
  };

  Mattrics.getActiveDayStats = function getActiveDayStats(activities) {
    const days = [...new Set(activities.map((activity) => activity.Date.slice(0, 10)))].sort();
    if (!days.length) {
      return { activeDays: 0, maxStreak: 0, lastDate: "", days: [] };
    }

    let maxStreak = 1;
    let currentStreak = 1;
    for (let i = 1; i < days.length; i += 1) {
      const diff = (new Date(days[i]) - new Date(days[i - 1])) / 86400000;
      currentStreak = diff === 1 ? currentStreak + 1 : 1;
      if (currentStreak > maxStreak) maxStreak = currentStreak;
    }

    return {
      activeDays: days.length,
      maxStreak,
      lastDate: days[days.length - 1],
      days,
    };
  };

  Mattrics.getFixedRecentActivities = function getFixedRecentActivities(activities, days = Mattrics.MUSCLE_FATIGUE_CONFIG.windowDays) {
    const end = Mattrics.startOfDay(new Date());
    const start = new Date(end);
    start.setDate(start.getDate() - (days - 1));
    const startIso = Mattrics.toIsoDate(start);

    return activities.filter((activity) => activity.Date >= startIso);
  };

  Mattrics.getExerciseMuscleMapping = function getExerciseMuscleMapping(exerciseName) {
    const normalized = String(exerciseName || "").toLowerCase().replace(/[^a-z0-9]+/g, " ").trim();
    const exerciseMappings = [
      { patterns: ["bench", "push up", "pushup", "chest fly", "pec deck", "dip"], weights: { chest: 1, deltoids: 0.45, triceps: 0.52, abs: 0.08, trapezius: 0.06 } },
      { patterns: ["incline press", "incline bench", "chest press"], weights: { chest: 0.95, deltoids: 0.5, triceps: 0.46, trapezius: 0.08, abs: 0.08 } },
      { patterns: ["row", "face pull"], weights: { upperBack: 0.82, trapezius: 0.58, biceps: 0.34, deltoids: 0.22, lowerBack: 0.12 } },
      { patterns: ["pulldown", "pull up", "pullup", "chin up", "chinup"], weights: { upperBack: 0.74, trapezius: 0.28, biceps: 0.48, deltoids: 0.14, lowerBack: 0.08 } },
      { patterns: ["deadlift", "rdl", "romanian deadlift"], weights: { hamstrings: 0.92, gluteal: 0.72, lowerBack: 0.46, upperBack: 0.28, trapezius: 0.18, quadriceps: 0.18, abs: 0.2 } },
      { patterns: ["shoulder press", "overhead press", "arnold press", "lateral raise", "front raise", "rear delt"], weights: { deltoids: 1, triceps: 0.42, trapezius: 0.24, chest: 0.15, upperBack: 0.12 } },
      { patterns: ["curl", "hammer"], weights: { biceps: 1, deltoids: 0.08 } },
      { patterns: ["triceps", "pushdown", "skull crusher"], weights: { triceps: 1, deltoids: 0.08 } },
      { patterns: ["plank", "crunch", "twist", "dead bug", "hollow", "sit up", "leg raise", "russian twist"], weights: { abs: 0.78, obliques: 0.52, lowerBack: 0.18 } },
      { patterns: ["hip thrust", "glute bridge"], weights: { gluteal: 1, hamstrings: 0.35, abs: 0.14, lowerBack: 0.12, adductors: 0.1 } },
      { patterns: ["calf raise"], weights: { calves: 1, quadriceps: 0.12 } },
      { patterns: ["squat", "lunge", "step up", "stepup", "leg press", "split squat"], weights: { quadriceps: 0.95, gluteal: 0.55, hamstrings: 0.42, adductors: 0.24, abs: 0.18, lowerBack: 0.08, calves: 0.14 } },
    ];

    return exerciseMappings.find((mapping) => mapping.patterns.some((pattern) => normalized.includes(pattern))) || null;
  };

  Mattrics.parseHevySetLine = function parseHevySetLine(setText, exerciseName) {
    const fatigueConfig = Mattrics.MUSCLE_FATIGUE_CONFIG;
    const estimatedBodyweightKg = fatigueConfig.estimatedBodyweightKg || 75;
    const bodyweightLoadFactor = fatigueConfig.bodyweightLoadFactor || 0.4;
    const defaultRpe = fatigueConfig.defaultRpe || 7;
    const rawText = String(setText || "").trim();
    const text = rawText.toLowerCase();
    const exercise = String(exerciseName || "").toLowerCase();
    const isBodyweightExercise = /(push ?up|pull ?up|chin ?up|dip|sit ?up|crunch|leg raise|bodyweight|bw|air squat|pistol squat)/i.test(exercise);
    const isTimeBased = /\b\d+\s*(?:sec|secs|second|seconds|min|mins|minute|minutes|hr|hrs|hour|hours)\b/i.test(text)
      || /\b\d{1,2}:\d{2}\b/.test(text)
      || /\bfor time\b/i.test(text)
      || /\btime\b/i.test(text);

    if (!rawText) return { kind: "unknown" };
    if (isTimeBased) return { kind: "time" };

    const parseNumber = (value) => parseFloat(String(value || "").replace(",", "."));
    const convertWeightKg = (value, unit) => {
      const amount = parseNumber(value);
      if (!Number.isFinite(amount)) return 0;
      return /^lb/.test(unit || "") ? amount * 0.453592 : amount;
    };
    const rpeMatch = rawText.match(/(?:\brpe\b\s*[:@]?\s*|@\s*)(\d+(?:[.,]\d+)?)/i);
    const rpe = Number.isFinite(parseNumber(rpeMatch && rpeMatch[1])) ? parseNumber(rpeMatch[1]) : defaultRpe;
    const effortFactor = 0.5 + (rpe / 10);
    const bodyweightTag = /\b(?:bw|body ?weight)\b/i.test(rawText);

    let weightKg = 0;
    let reps = 0;

    const weightFirst = rawText.match(/(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs)\s*(?:x|×)\s*(\d+)/i);
    const repsFirst = rawText.match(/(\d+)\s*(?:reps?)?\s*(?:x|×|@)\s*(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs)/i);
    const weightOnly = rawText.match(/(\d+(?:[.,]\d+)?)\s*(kg|kgs|lb|lbs)/i);
    const repsOnly = rawText.match(/(?:^|[^\d])(\d+)\s*(?:reps?|x)?(?:$|[^\d])/i);

    if (weightFirst) {
      weightKg = convertWeightKg(weightFirst[1], weightFirst[2]);
      reps = parseInt(weightFirst[3], 10) || 0;
    } else if (repsFirst) {
      reps = parseInt(repsFirst[1], 10) || 0;
      weightKg = convertWeightKg(repsFirst[2], repsFirst[3]);
    } else if ((bodyweightTag || isBodyweightExercise) && repsOnly) {
      reps = parseInt(repsOnly[1], 10) || 0;
      weightKg = estimatedBodyweightKg * bodyweightLoadFactor;
    } else if (weightOnly && /\bx\b|×|\breps?\b/i.test(rawText) && repsOnly) {
      reps = parseInt(repsOnly[1], 10) || 0;
      weightKg = convertWeightKg(weightOnly[1], weightOnly[2]);
    }

    if (!Number.isFinite(weightKg) || !Number.isFinite(reps) || weightKg <= 0 || reps <= 0) {
      return { kind: "unknown" };
    }

    return {
      kind: "parsed",
      reps,
      weightKg,
      rpe,
      effortFactor,
      load: weightKg * reps * effortFactor,
    };
  };

  Mattrics.getFatigueTierMeaning = function getFatigueTierMeaning(region) {
    const tier = typeof region === "string" ? region : Mattrics.getFatigueDisplayTier(region);
    switch (tier) {
      case "Highly fatigued":
        return "high fatigue load right now";
      case "Fatigued":
        return "fatigue is still clearly present";
      case "Recovering":
        return "recovering but not fully fresh yet";
      case "Fresh":
        return "light fatigue only";
      default:
        return "no recent load recorded";
    }
  };

  Mattrics.getRecoveryLabel = function getRecoveryLabel(recoveryHours) {
    const hours = Math.max(0, Math.ceil(recoveryHours || 0));
    if (!hours) return "can likely be trained today";
    if (hours < 24) return `can likely be trained in ${hours} hour${hours === 1 ? "" : "s"}`;
    if (hours < 48) return "can likely be trained tomorrow";
    const days = Math.ceil(hours / 24);
    return `can likely be trained in ${days} day${days === 1 ? "" : "s"}`;
  };

  Mattrics.getActivityMuscleStimulus = function getActivityMuscleStimulus(activity) {
    const clamp = (value, min, max) => Math.max(min, Math.min(max, value));
    const strengthFactor = (min) => clamp((min || 0) / 45, 0.85, 1.6);
    const sportFactor = (min) => clamp((min || 0) / 60, 0.7, 1.45);
    const scaleWeights = (weights, factor) => Object.fromEntries(
      Object.entries(weights).map(([key, value]) => [key, value * factor])
    );
    const typeMappings = {
      Run: { quadriceps: 0.58, hamstrings: 0.55, calves: 0.78, gluteal: 0.42, abs: 0.18, obliques: 0.08 },
      Walk: { quadriceps: 0.32, hamstrings: 0.24, calves: 0.36, gluteal: 0.15, abs: 0.08 },
      Hike: { quadriceps: 0.76, hamstrings: 0.66, calves: 0.7, gluteal: 0.48, adductors: 0.18, abs: 0.2 },
      Ride: { quadriceps: 0.96, hamstrings: 0.34, calves: 0.22, gluteal: 0.36, abs: 0.14 },
      Canoeing: { upperBack: 0.56, trapezius: 0.42, deltoids: 0.72, biceps: 0.46, triceps: 0.1, abs: 0.22, obliques: 0.1, lowerBack: 0.1 },
      Canoe: { upperBack: 0.56, trapezius: 0.42, deltoids: 0.72, biceps: 0.46, triceps: 0.1, abs: 0.22, obliques: 0.1, lowerBack: 0.1 },
      WaterSport: { upperBack: 0.6, trapezius: 0.46, deltoids: 0.8, biceps: 0.58, triceps: 0.12, abs: 0.22, obliques: 0.13, lowerBack: 0.12 },
      Rowing: { upperBack: 0.58, trapezius: 0.34, deltoids: 0.22, biceps: 0.34, abs: 0.2, obliques: 0.1, lowerBack: 0.16, quadriceps: 0.34, hamstrings: 0.26, gluteal: 0.22, calves: 0.16 },
      Yoga: { abs: 0.38, obliques: 0.26, deltoids: 0.18, upperBack: 0.14, lowerBack: 0.14, quadriceps: 0.15, hamstrings: 0.12, gluteal: 0.14, calves: 0.08 },
      Surfing: { abs: 0.52, obliques: 0.26, deltoids: 0.68, upperBack: 0.28, trapezius: 0.18, biceps: 0.16, triceps: 0.14, lowerBack: 0.12, quadriceps: 0.12, hamstrings: 0.1, gluteal: 0.1 },
      WeightTraining: { chest: 0.28, deltoids: 0.24, trapezius: 0.16, upperBack: 0.22, triceps: 0.16, biceps: 0.16, abs: 0.16, obliques: 0.08, lowerBack: 0.12, gluteal: 0.14, adductors: 0.08, quadriceps: 0.16, hamstrings: 0.12, calves: 0.08 },
      Workout: { chest: 0.28, deltoids: 0.24, trapezius: 0.16, upperBack: 0.22, triceps: 0.16, biceps: 0.16, abs: 0.16, obliques: 0.08, lowerBack: 0.12, gluteal: 0.14, adductors: 0.08, quadriceps: 0.16, hamstrings: 0.12, calves: 0.08 },
    };
    const min = parseFloat(activity["Duration (min)"]) || 0;
    const type = activity.Type;
    const hevy = Mattrics.parseHevyDescription(activity.Description || "");
    const stimulus = Object.fromEntries(
      Mattrics.MUSCLE_REGIONS.map((region) => [region.key, 0])
    );
    const configFatigue = Mattrics.MUSCLE_FATIGUE_CONFIG;
    const setLoadDivisor = configFatigue.strengthLoadUnitDivisor || 1500;
    const addStimulus = (weights) => {
      Object.entries(weights).forEach(([key, value]) => {
        if (!(key in stimulus) || !value) return;
        stimulus[key] += value;
      });
    };

    if (hevy && hevy.length) {
      let matchedExercise = false;
      hevy.forEach((exercise) => {
        const match = Mattrics.getExerciseMuscleMapping(exercise.name);
        if (!match) return;
        matchedExercise = true;
        const mappedNormalizations = Object.keys(match.weights)
          .map((key) => configFatigue.normalizationLoad[key] || 0)
          .filter(Boolean);
        const smallThreshold = (Math.max(...mappedNormalizations, 0) || 0) * (configFatigue.smallThresholdRatio || 0.02);
        let parsedExerciseLoad = 0;
        let parsedSetCount = 0;
        let ambiguousSetFound = false;
        let nonTimeSetFound = false;

        exercise.sets.forEach((setText) => {
          const parsed = Mattrics.parseHevySetLine(setText, exercise.name);
          if (parsed.kind === "time") return;
          nonTimeSetFound = true;
          if (parsed.kind !== "parsed") {
            ambiguousSetFound = true;
            return;
          }

          const scaledSetLoad = parsed.load / setLoadDivisor;
          if (scaledSetLoad < smallThreshold) return;
          parsedExerciseLoad += scaledSetLoad;
          parsedSetCount += 1;
        });

        if (parsedSetCount && !ambiguousSetFound) {
          addStimulus(scaleWeights(match.weights, parsedExerciseLoad));
          return;
        }

        if (!parsedSetCount && !nonTimeSetFound) {
          return;
        }

        const setCount = exercise.sets.length || 0;
        const setFactor = clamp(setCount / 3, 0.8, 1.6);
        addStimulus(scaleWeights(match.weights, strengthFactor(min) * setFactor));
      });
      if (matchedExercise) return stimulus;
    }

    const base = typeMappings[type];
    if (!base) return stimulus;
    const factor = ["WeightTraining", "Workout"].includes(type) ? strengthFactor(min) : sportFactor(min);
    addStimulus(scaleWeights(base, factor));
    return stimulus;
  };

  Mattrics.getMuscleLoadAnalysis = function getMuscleLoadAnalysis(activities) {
    const regions = Object.fromEntries(
      Mattrics.MUSCLE_REGIONS.map((region) => [region.key, { ...region, load: 0, hits: 0 }])
    );

    activities.forEach((activity) => {
      const stimulus = Mattrics.getActivityMuscleStimulus(activity);
      Object.entries(stimulus).forEach(([key, value]) => {
        if (!regions[key] || !value) return;
        regions[key].load += value;
        regions[key].hits += 1;
      });
    });

    const ranked = Object.values(regions).sort((a, b) => b.load - a.load);
    const maxLoad = ranked[0]?.load || 0;
    const totalLoad = ranked.reduce((sum, region) => sum + region.load, 0);

    if (!maxLoad) {
      return {
        regions: ranked.map((region) => ({ ...region, pct: 0, share: 0, hitLabel: "0 hits" })),
        strongest: [],
        weakest: ranked,
        summary: "No worked-body signal yet",
        detail: "Add more sessions to map which regions are carrying the work.",
      };
    }

    const withMetrics = ranked.map((region) => ({
      ...region,
      pct: Math.round((region.load / maxLoad) * 100),
      share: totalLoad ? region.load / totalLoad : 0,
      hitLabel: `${region.hits} hit${region.hits === 1 ? "" : "s"}`,
    }));
    const strongest = withMetrics.filter((region) => region.load >= maxLoad * 0.8);
    const minNonZero = withMetrics.filter((region) => region.load > 0).slice(-1)[0]?.load || 0;
    const weakest = minNonZero
      ? withMetrics.filter((region) => region.load === minNonZero || region.load === 0)
      : withMetrics;
    const strongestLabel = strongest.slice(0, 2).map((region) => region.label).join(" + ");
    const weakestLabel = weakest.filter((region) => region.load === 0).length
      ? weakest.filter((region) => region.load === 0).slice(0, 2).map((region) => region.label).join(" + ")
      : weakest.slice(0, 2).map((region) => region.label).join(" + ");

    return {
      regions: withMetrics,
      strongest,
      weakest,
      summary: strongest.length > 1 ? `${strongestLabel} carried the work` : `${strongest[0].label} took the load`,
      detail: weakest.some((region) => region.load === 0)
        ? `${weakestLabel} were barely touched.`
        : `${weakestLabel} got the least work.`,
    };
  };

  Mattrics.getMuscleFatigueTier = function getMuscleFatigueTier(score) {
    if (score >= 75) return "Highly fatigued";
    if (score >= 50) return "Fatigued";
    if (score >= 25) return "Recovering";
    return "Fresh";
  };

  Mattrics.getFatigueVisualState = function getFatigueVisualState(region) {
    if (!region || !region.rawLoad) return "none";
    if (region.fatigueScore >= 75) return "high";
    if (region.fatigueScore >= 50) return "fatigued";
    if (region.fatigueScore >= 25) return "recovering";
    return "fresh";
  };

  Mattrics.getFatigueDisplayTier = function getFatigueDisplayTier(region) {
    return region && region.rawLoad ? region.tier : "No recent load";
  };

  Mattrics.getRelativeDayLabel = function getRelativeDayLabel(ds) {
    if (!ds) return "no recent hit";
    const daysAgo = Math.max(0, Mattrics.diffDays(new Date(), Mattrics.parseDate(ds)));
    if (daysAgo === 0) return "hit today";
    if (daysAgo === 1) return "1 day ago";
    return `${daysAgo} days ago`;
  };

  Mattrics.getMuscleFatigueAnalysis = function getMuscleFatigueAnalysis(activities) {
    const configFatigue = Mattrics.MUSCLE_FATIGUE_CONFIG;
    const recent = Mattrics.getFixedRecentActivities(activities, configFatigue.windowDays);
    const now = new Date();
    const regions = Object.fromEntries(
      Mattrics.MUSCLE_REGIONS.map((region) => [region.key, {
        ...region,
        rawLoad: 0,
        fatigueScore: 0,
        tier: "Fresh",
        lastWorkedDate: "",
        lastWorkedLabel: "no recent hit",
        recoveryHours: 0,
        recoveryDate: "",
        recoveryLabel: "can likely be trained today",
      }])
    );

    recent.forEach((activity) => {
      const activityDate = Mattrics.parseDate(activity.Date);
      activityDate.setHours(12, 0, 0, 0);
      const hoursAgo = Math.max(0, (now - activityDate) / 3600000);
      const stimulus = Mattrics.getActivityMuscleStimulus(activity);

      Object.entries(stimulus).forEach(([key, value]) => {
        if (!regions[key] || !value) return;
        const halfLifeHours = configFatigue.halfLifeHours[key] || 60;
        const remaining = value * Math.pow(0.5, hoursAgo / halfLifeHours);
        regions[key].rawLoad += remaining;
        if (!regions[key].lastWorkedDate || activity.Date > regions[key].lastWorkedDate) {
          regions[key].lastWorkedDate = activity.Date;
        }
      });
    });

    const withMetrics = Object.values(regions)
      .map((region) => {
        const normalizationLoad = configFatigue.normalizationLoad[region.key] || 5;
        const threshold = normalizationLoad * (configFatigue.recoveryThresholdRatio || 0.4);
        const halfLifeHours = configFatigue.halfLifeHours[region.key] || 60;
        const recoveryHours = region.rawLoad > threshold && threshold > 0
          ? Math.max(0, Math.ceil(halfLifeHours * Math.log2(region.rawLoad / threshold)))
          : 0;
        const recoveryDate = recoveryHours
          ? new Date(now.getTime() + (recoveryHours * 3600000)).toISOString()
          : now.toISOString();
        const fatigueScore = Math.max(0, Math.min(100, Math.round((region.rawLoad / normalizationLoad) * 100)));
        return {
          ...region,
          rawLoad: Number(region.rawLoad.toFixed(2)),
          fatigueScore,
          tier: Mattrics.getMuscleFatigueTier(fatigueScore),
          lastWorkedLabel: Mattrics.getRelativeDayLabel(region.lastWorkedDate),
          recoveryHours,
          recoveryDate,
          recoveryLabel: Mattrics.getRecoveryLabel(recoveryHours),
        };
      })
      .sort((a, b) => b.fatigueScore - a.fatigueScore || b.rawLoad - a.rawLoad);

    const highestScore = withMetrics[0]?.fatigueScore || 0;
    const lowestScore = withMetrics[withMetrics.length - 1]?.fatigueScore || 0;
    const highestFatigue = highestScore
      ? withMetrics.filter((region) => region.fatigueScore >= Math.max(25, highestScore - 10))
      : [];
    const lowestFatigue = withMetrics.filter((region) => region.fatigueScore === lowestScore);

    if (!recent.length || !highestScore) {
      return {
        regions: withMetrics,
        highestFatigue: [],
        lowestFatigue: withMetrics,
        summary: "Fresh across the board",
        detail: `No meaningful muscle load in the last ${configFatigue.windowDays} days yet.`,
      };
    }

    const highestLabel = highestFatigue.slice(0, 2).map((region) => region.label).join(" + ");
    const lowestLabel = lowestFatigue.slice(0, 2).map((region) => region.label).join(" + ");
    const topTier = withMetrics[0].tier;

    return {
      regions: withMetrics,
      highestFatigue,
      lowestFatigue,
      summary: `${highestLabel} ${topTier === "Highly fatigued" ? "are carrying the most fatigue" : "need the most recovery"}`,
      detail: `${lowestLabel} look freshest right now.`,
    };
  };

  Mattrics.getOverviewMetrics = function getOverviewMetrics(activities) {
    const totalKm = activities.reduce((sum, activity) => sum + (parseFloat(activity["Distance (km)"]) || 0), 0);
    const totalMin = activities.reduce((sum, activity) => sum + (parseFloat(activity["Duration (min)"]) || 0), 0);
    const active = Mattrics.getActiveDayStats(activities);
    const mix = Mattrics.getActivityMix(activities);
    const fatigue = Mattrics.getMuscleFatigueAnalysis(Mattrics.state.allData);
    const avgSessionMin = activities.length ? totalMin / activities.length : 0;
    const longestSessionMin = activities.reduce((max, activity) => Math.max(max, parseFloat(activity["Duration (min)"]) || 0), 0);
    const byDay = {};
    const byMonth = {};

    activities.forEach((activity) => {
      const day = activity.Date.slice(0, 10);
      const month = activity.Date.slice(0, 7);
      byDay[day] = (byDay[day] || 0) + 1;
      byMonth[month] = (byMonth[month] || 0) + 1;
    });

    const multiSessionDays = Object.values(byDay).filter((count) => count > 1).length;
    const bestMonth = Object.entries(byMonth).sort((a, b) => b[1] - a[1])[0] || null;
    const windowDays = active.days.length
      ? Math.max(1, Math.round((new Date(active.days[active.days.length - 1]) - new Date(active.days[0])) / 86400000) + 1)
      : 0;
    const weeklyAverageMin = windowDays ? (totalMin / windowDays) * 7 : 0;
    const distanceTypes = [];
    if (activities.some((activity) => ["Run"].includes(activity.Type) && parseFloat(activity["Distance (km)"]) > 0)) distanceTypes.push("run");
    if (activities.some((activity) => ["Canoeing", "Canoe", "WaterSport", "Rowing"].includes(activity.Type) && parseFloat(activity["Distance (km)"]) > 0)) distanceTypes.push("paddle");
    if (activities.some((activity) => ["Hike", "Walk", "Ride"].includes(activity.Type) && parseFloat(activity["Distance (km)"]) > 0)) distanceTypes.push("outdoor");

    return {
      totalSessions: activities.length,
      totalKm,
      totalMin,
      avgSessionMin,
      weeklyAverageMin,
      longestSessionMin,
      multiSessionDays,
      ...active,
      mix,
      fatigue,
      bestMonth,
      distanceSummary: totalKm > 0 ? `${totalKm.toFixed(0)} km across ${distanceTypes.slice(0, 2).join(" + ") || "distance work"}` : "",
    };
  };

  Mattrics.cardMetrics = function cardMetrics(activity) {
    const km = parseFloat(activity["Distance (km)"]) || 0;
    const min = parseFloat(activity["Duration (min)"]) || 0;
    const hr = parseFloat(activity["Avg HR"]) || 0;
    const elev = parseFloat(activity["Elevation Gain (m)"]) || 0;
    const pace = parseFloat(activity["Avg Pace (min/km)"]) || 0;
    const cad = parseFloat(activity["Avg Cadence"]) || 0;
    const speed = parseFloat(activity["Avg Speed (km/h)"]) || 0;
    const metrics = [];

    switch (activity.Type) {
      case "Canoeing":
      case "Canoe":
        if (km) metrics.push({ val: km.toFixed(1), lab: "km", color: "var(--canoe)" });
        if (min) metrics.push({ val: Mattrics.fmt(min), lab: "time", color: "var(--text)" });
        if (elev) metrics.push({ val: `${elev}m`, lab: "elev", color: "var(--muted)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
        break;
      case "Run":
        if (km) metrics.push({ val: km.toFixed(2), lab: "km", color: "var(--run)" });
        if (pace) metrics.push({ val: pace.toFixed(1), lab: "min/km", color: "var(--text)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
        if (elev) metrics.push({ val: `${elev}m`, lab: "elev", color: "var(--muted)" });
        break;
      case "Hike":
        if (km) metrics.push({ val: km.toFixed(1), lab: "km", color: "var(--hike)" });
        if (elev) metrics.push({ val: `${elev}m`, lab: "gain", color: "var(--hike)" });
        if (min) metrics.push({ val: Mattrics.fmt(min), lab: "time", color: "var(--text)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
        break;
      case "Walk":
        if (km) metrics.push({ val: km.toFixed(1), lab: "km", color: "var(--walk)" });
        if (min) metrics.push({ val: Mattrics.fmt(min), lab: "time", color: "var(--text)" });
        if (elev) metrics.push({ val: `${elev}m`, lab: "gain", color: "var(--muted)" });
        break;
      case "Ride":
        if (km) metrics.push({ val: km.toFixed(1), lab: "km", color: "var(--ride)" });
        if (speed) metrics.push({ val: speed.toFixed(1), lab: "km/h", color: "var(--text)" });
        if (min) metrics.push({ val: Mattrics.fmt(min), lab: "time", color: "var(--muted)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
        break;
      case "Rowing":
      case "WaterSport":
        if (km) metrics.push({ val: km.toFixed(2), lab: "km", color: "var(--row)" });
        if (min) metrics.push({ val: Mattrics.fmt(min), lab: "time", color: "var(--text)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
        if (cad) metrics.push({ val: cad.toFixed(0), lab: "s/m", color: "var(--muted)" });
        break;
      case "Yoga":
        if (min) metrics.push({ val: Mattrics.fmt(min), lab: "time", color: "var(--yoga)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
        break;
      case "Surfing":
        if (min) metrics.push({ val: Mattrics.fmt(min), lab: "time", color: "var(--surf)" });
        break;
      default:
        if (min) metrics.push({ val: Mattrics.fmt(min), lab: "time", color: "var(--lift)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
    }

    return metrics;
  };

  Mattrics.detailFacts = function detailFacts(activity) {
    const facts = [];
    const add = (val, lab) => {
      if (val !== "" && val !== null && val !== undefined) facts.push({ val, lab });
    };
    const km = parseFloat(activity["Distance (km)"]) || 0;
    const min = parseFloat(activity["Duration (min)"]) || 0;
    const elev = parseFloat(activity["Elevation Gain (m)"]) || 0;
    const hr = parseFloat(activity["Avg HR"]) || 0;
    const maxHr = parseFloat(activity["Max HR"]) || 0;
    const pace = parseFloat(activity["Avg Pace (min/km)"]) || 0;
    const speed = parseFloat(activity["Avg Speed (km/h)"]) || 0;
    const cad = parseFloat(activity["Avg Cadence"]) || 0;

    if (km) add(`${km.toFixed(km >= 10 ? 1 : 2)} km`, "Distance");
    if (min) add(Mattrics.fmt(min), "Duration");
    if (elev) add(`${elev} m`, "Elevation");
    if (hr) add(hr.toFixed(0), "Avg HR");
    if (maxHr) add(maxHr.toFixed(0), "Max HR");
    if (pace) add(pace.toFixed(1), "Avg pace");
    if (speed) add(`${speed.toFixed(1)} km/h`, "Avg speed");
    if (cad) add(cad.toFixed(0), "Cadence");
    if (activity["Device Name"]) add(activity["Device Name"], "Device");

    return facts;
  };

  Mattrics.parseHevyDescription = function parseHevyDescription(desc) {
    const text = (desc || "").trim();
    if (!text.startsWith("Logged with Hevy")) return null;

    const blocks = text
      .replace(/^Logged with Hevy\s*/, "")
      .trim()
      .split(/\n\s*\n/)
      .map((block) => block.trim())
      .filter(Boolean);

    return blocks
      .map((block) => {
        const lines = block.split("\n").map((line) => line.trim()).filter(Boolean);
        if (!lines.length) return null;
        return { name: lines[0], sets: lines.slice(1) };
      })
      .filter(Boolean);
  };

  window.Mattrics = Mattrics;
}());
