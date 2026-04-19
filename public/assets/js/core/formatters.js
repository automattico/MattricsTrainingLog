(function () {
  const M = window.Mattrics;

  M.tc = function tc(type) {
    return M.TYPES[type] || { icon: "⚡", color: "var(--muted)", label: type };
  };

  M.canonicalType = function canonicalType(type) {
    if (type === "Canoe") return "Canoeing";
    if (type === "WaterSport") return "Rowing";
    return type;
  };

  M.escAttr = function escAttr(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  };

  M.esc = function esc(value) {
    return (value || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
  };

  M.fmt = function fmt(min) {
    const rounded = Math.round(parseFloat(min));
    if (!rounded) return "—";
    return rounded < 60 ? `${rounded}m` : `${Math.floor(rounded / 60)}h${rounded % 60 ? ` ${rounded % 60}m` : ""}`;
  };

  M.fmtDate = function fmtDate(ds) {
    return M.parseDate(ds).toLocaleDateString("en-GB", {
      weekday: "short",
      day: "numeric",
      month: "short",
      year: "numeric",
    });
  };

  M.fmtShort = function fmtShort(ds) {
    return M.parseDate(ds).toLocaleDateString("en-GB", { day: "numeric", month: "short" });
  };

  M.fmtDateTime = function fmtDateTime(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return "";
    return date.toLocaleString("en-GB", {
      day: "numeric",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  };

  M.parseTimestamp = function parseTimestamp(value) {
    if (!value) return null;
    const date = value instanceof Date ? value : new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
  };

  M.fmtRelativeAge = function fmtRelativeAge(value, nowMs = Date.now()) {
    const date = M.parseTimestamp(value);
    if (!date) return "";

    const diffMs = Math.max(0, nowMs - date.getTime());
    const diffSec = Math.floor(diffMs / 1000);

    if (diffSec < 60) return `${diffSec} ${diffSec === 1 ? "sec" : "secs"} ago`;

    const diffMin = Math.floor(diffSec / 60);
    if (diffMin < 60) return `${diffMin} ${diffMin === 1 ? "min" : "mins"} ago`;

    const hours = Math.floor(diffMin / 60);
    const mins = diffMin % 60;
    return `${hours}h${mins}m ago`;
  };

  M.fmtBerlinDate = function fmtBerlinDate(value) {
    const date = M.parseTimestamp(value);
    if (!date) return "";

    const parts = new Intl.DateTimeFormat("en-GB", {
      timeZone: "Europe/Berlin",
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    }).formatToParts(date);

    const get = (type) => parts.find((part) => part.type === type)?.value || "";
    return `${get("year")}-${get("month")}-${get("day")}`;
  };

  M.fmtBerlinTime = function fmtBerlinTime(value) {
    const date = M.parseTimestamp(value);
    if (!date) return "";

    const parts = new Intl.DateTimeFormat("en-GB", {
      timeZone: "Europe/Berlin",
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
      hour12: false,
      timeZoneName: "short",
    }).formatToParts(date);

    const get = (type) => parts.find((part) => part.type === type)?.value || "";
    return `${get("hour")}:${get("minute")}:${get("second")} ${get("timeZoneName")}`;
  };

  M.resolveHeaderTimestamp = function resolveHeaderTimestamp(meta) {
    if (!meta) return null;

    return M.parseTimestamp(
      meta.lastObservedAt ||
      meta.observedAt ||
      meta.bucketTimestamp ||
      meta.dataBucketTimestamp ||
      meta.lastSuccessfulSyncAt ||
      meta.lastFetchAt
    );
  };

  M.formatContextRange = function formatContextRange(startDs, endDs) {
    if (!startDs || !endDs) return "";

    const start = M.parseDate(startDs);
    const end = M.parseDate(endDs);

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

  M.getActivityId = function getActivityId(activity) {
    return String(activity["Activity ID raw"] || activity["Activity ID"] || activity.Name || "");
  };
}());
