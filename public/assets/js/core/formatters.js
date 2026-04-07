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
