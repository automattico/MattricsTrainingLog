(function () {
  const M = window.Mattrics;

  M.cardMetrics = function cardMetrics(activity) {
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
        if (min) metrics.push({ val: M.fmt(min), lab: "time", color: "var(--text)" });
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
        if (min) metrics.push({ val: M.fmt(min), lab: "time", color: "var(--text)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
        break;
      case "Walk":
        if (km) metrics.push({ val: km.toFixed(1), lab: "km", color: "var(--walk)" });
        if (min) metrics.push({ val: M.fmt(min), lab: "time", color: "var(--text)" });
        if (elev) metrics.push({ val: `${elev}m`, lab: "gain", color: "var(--muted)" });
        break;
      case "Ride":
        if (km) metrics.push({ val: km.toFixed(1), lab: "km", color: "var(--ride)" });
        if (speed) metrics.push({ val: speed.toFixed(1), lab: "km/h", color: "var(--text)" });
        if (min) metrics.push({ val: M.fmt(min), lab: "time", color: "var(--muted)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
        break;
      case "Rowing":
      case "WaterSport":
        if (km) metrics.push({ val: km.toFixed(2), lab: "km", color: "var(--row)" });
        if (min) metrics.push({ val: M.fmt(min), lab: "time", color: "var(--text)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
        if (cad) metrics.push({ val: cad.toFixed(0), lab: "s/m", color: "var(--muted)" });
        break;
      case "Yoga":
        if (min) metrics.push({ val: M.fmt(min), lab: "time", color: "var(--yoga)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
        break;
      case "Surfing":
        if (min) metrics.push({ val: M.fmt(min), lab: "time", color: "var(--surf)" });
        break;
      default:
        if (min) metrics.push({ val: M.fmt(min), lab: "time", color: "var(--lift)" });
        if (hr) metrics.push({ val: hr.toFixed(0), lab: "avg♥", color: "var(--muted)" });
    }

    return metrics;
  };

  M.detailFacts = function detailFacts(activity) {
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
    if (min) add(M.fmt(min), "Duration");
    if (elev) add(`${elev} m`, "Elevation");
    if (hr) add(hr.toFixed(0), "Avg HR");
    if (maxHr) add(maxHr.toFixed(0), "Max HR");
    if (pace) add(pace.toFixed(1), "Avg pace");
    if (speed) add(`${speed.toFixed(1)} km/h`, "Avg speed");
    if (cad) add(cad.toFixed(0), "Cadence");
    if (activity["Device Name"]) add(activity["Device Name"], "Device");

    return facts;
  };
}());
