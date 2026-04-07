(function () {
  const M = window.Mattrics;

  M.normalizeDateValue = function normalizeDateValue(value) {
    if (value instanceof Date && !Number.isNaN(value.getTime())) {
      return M.toIsoDate(value);
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
      return M.toIsoDate(nativeDate);
    }

    return "";
  };

  M.parseDate = function parseDate(ds) {
    const normalized = M.normalizeDateValue(ds);
    const [year, month, day] = normalized.split("-").map(Number);
    return new Date(year || 1970, ((month || 1) - 1), day || 1);
  };

  M.startOfDay = function startOfDay(date = new Date()) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  };

  M.toIsoDate = function toIsoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  };

  M.shiftDate = function shiftDate(ds, deltaDays) {
    const date = typeof ds === "string" ? M.parseDate(ds) : new Date(ds);
    date.setDate(date.getDate() + deltaDays);
    return M.toIsoDate(date);
  };

  M.diffDays = function diffDays(later, earlier) {
    const laterDay = M.startOfDay(later);
    const earlierDay = M.startOfDay(earlier);
    const laterUtc = Date.UTC(laterDay.getFullYear(), laterDay.getMonth(), laterDay.getDate());
    const earlierUtc = Date.UTC(earlierDay.getFullYear(), earlierDay.getMonth(), earlierDay.getDate());
    return Math.round((laterUtc - earlierUtc) / 86400000);
  };

  M.weekStart = function weekStart(ds) {
    const date = M.parseDate(ds);
    const day = date.getDay();
    const diff = date.getDate() - day + (day === 0 ? -6 : 1);
    const start = new Date(date);
    start.setDate(diff);
    return M.toIsoDate(start);
  };

  M.formatWeekRange = function formatWeekRange(startIso) {
    const start = M.parseDate(startIso);
    const end = M.parseDate(M.shiftDate(startIso, 6));
    return `${start.toLocaleDateString("en-GB", { day: "numeric", month: "short" })} - ${end.toLocaleDateString("en-GB", { day: "numeric", month: "short" })}`;
  };
}());
