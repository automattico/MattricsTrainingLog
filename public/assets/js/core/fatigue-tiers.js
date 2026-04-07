(function () {
  const M = window.Mattrics;

  M.getMuscleFatigueTier = function getMuscleFatigueTier(score) {
    if (score >= 75) return "Highly fatigued";
    if (score >= 50) return "Fatigued";
    if (score >= 25) return "Recovering";
    return "Fresh";
  };

  M.getFatigueVisualState = function getFatigueVisualState(region) {
    if (!region || !region.rawLoad) return "none";
    if (region.fatigueScore >= 75) return "high";
    if (region.fatigueScore >= 50) return "fatigued";
    if (region.fatigueScore >= 25) return "recovering";
    return "fresh";
  };

  M.getFatigueDisplayTier = function getFatigueDisplayTier(region) {
    return region && region.rawLoad ? region.tier : "No recent load";
  };

  M.getFatigueTierMeaning = function getFatigueTierMeaning(region) {
    const tier = typeof region === "string" ? region : M.getFatigueDisplayTier(region);
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

  M.getRecoveryLabel = function getRecoveryLabel(recoveryHours) {
    const hours = Math.max(0, Math.ceil(recoveryHours || 0));
    if (!hours) return "can likely be trained today";
    if (hours < 24) return `can likely be trained in ${hours} hour${hours === 1 ? "" : "s"}`;
    if (hours < 48) return "can likely be trained tomorrow";
    const days = Math.ceil(hours / 24);
    return `can likely be trained in ${days} day${days === 1 ? "" : "s"}`;
  };

  M.getRelativeDayLabel = function getRelativeDayLabel(ds) {
    if (!ds) return "no recent hit";
    const daysAgo = Math.max(0, M.diffDays(new Date(), M.parseDate(ds)));
    if (daysAgo === 0) return "hit today";
    if (daysAgo === 1) return "1 day ago";
    return `${daysAgo} days ago`;
  };
}());
