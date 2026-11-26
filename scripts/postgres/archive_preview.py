"""Bounded, read-only archive diagnostics and local Postgres import frontend."""

import argparse
import collections
import csv
import datetime as dt
import gzip
import hashlib
import io
import json
import math
import re
import stat
import subprocess
import sys
import xml.etree.ElementTree as ET
import zipfile
import zlib
from pathlib import Path

from fit_activity import EPOCH, InvalidFitBoundary, Rejected, SPORTS, UTC, decode_fit, fit_sport

MIB = 1024 * 1024
FILE_LIMIT = 24 * MIB
ZIP_LIMIT = 144 * MIB
TOTAL_LIMIT = 768 * MIB
MAX_ENTRIES = 100_000
MAX_RECORDS = 20_000
GARMIN_UPLOADS = "DI_CONNECT/DI-Connect-Uploaded-Files/"
SPORT_LABELS = {
    "weighttraining": "WeightTraining", "strengthtraining": "WeightTraining",
    "running": "Run", "indoorrunning": "Run", "treadmillrunning": "Run",
    "trailrunning": "Run", "trackrunning": "Run", "cycling": "Ride",
    "indoorcycling": "Ride", "roadbiking": "Ride", "mountainbiking": "Ride",
    "walking": "Walk", "hiking": "Hike", "lapswim": "Swim",
    "openwaterswimming": "Swim", "swimming": "Swim",
    "indoorrowing": "Rowing", "indoorcardio": "Workout", "elliptical": "Workout",
    "cardio": "Workout", "fitnessequipment": "Workout", "crossfit": "Workout",
    "canoeing": "Canoe", "watersport": "WaterSport", "padeltennis": "Padel",
    "paddelball": "Padel", "lapswimming": "Swim", "yoga": "Yoga", "hiit": "Workout",
    "breathwork": "Breathwork", "mobility": "Mobility", "soccer": "Soccer",
    "football(soccer)": "Soccer", "skatingws": "Skate",

}


CANONICAL_SPORTS = set(SPORTS.values()) | {
    "Canoe", "Swim", "Yoga", "Other", "Multisport", "Workout", "EBikeRide",
    "Snowshoe", "RockClimbing", "StandUpPaddling", "Kayaking", "Pilates", "Golf",
    "Tennis", "Pickleball", "VirtualRide", "VirtualRun", "IceSkate",
}


def sport_label(value):
    value = value.strip()
    key = re.sub(r"[ _-]+", "", value.lower())
    return SPORT_LABELS.get(key, value if value in CANONICAL_SPORTS else "Other")


def iso(value):
    stamp = dt.datetime.fromisoformat(value.replace("Z", "+00:00"))
    if stamp.tzinfo is None:
        raise ValueError("offset required")
    return stamp.astimezone(UTC)


def number(value):
    try:
        result = float(value)
        return result if math.isfinite(result) and 0 <= result < 1e9 else None
    except (TypeError, ValueError):
        return None


def safe_name(name):
    parts = name.rstrip("/").split("/")
    return not any(c in name for c in ("\\", ":", "\0")) and all(p not in ("", ".", "..") for p in parts)


class Scan:
    def __init__(self):
        self.counts = collections.Counter()
        self.issues = []
        self.records = []
        self.bytes_left = TOTAL_LIMIT
        self.entries_left = MAX_ENTRIES

    def issue(self, reason, locator=None):
        self.counts[reason] += 1
        if locator is not None:
            self.issues.append({"reason": reason, "locator": locator})

    def members(self, archive, parents=()):
        entries = archive.infolist()
        self.entries_left -= len(entries)
        if self.entries_left < 0:
            raise Rejected("archive_entry_limit")
        names = collections.Counter(i.orig_filename for i in entries)
        for entry in entries:
            name = entry.orig_filename
            locator = [*parents, name]
            if not safe_name(name) or stat.S_ISLNK(entry.external_attr >> 16):
                self.issue("unsafe_zip_member", locator)
            elif names[name] != 1:
                self.issue("duplicate_zip_member", locator)
            elif not entry.is_dir():
                yield name, entry

    def bounded(self, stream, limit):
        if self.bytes_left < 0:
            raise Rejected("archive_decompressed_limit")
        data = stream.read(min(limit, self.bytes_left) + 1)
        self.bytes_left -= len(data)
        if self.bytes_left < 0:
            raise Rejected("archive_decompressed_limit")
        if len(data) > limit:
            raise Rejected("member_size_limit")
        return data

    def read(self, archive, entry, limit=FILE_LIMIT):
        if entry.file_size > limit:
            raise Rejected("member_size_limit")
        try:
            with archive.open(entry) as stream:
                return self.bounded(stream, limit)
        except (zipfile.BadZipFile, RuntimeError, NotImplementedError, EOFError, OSError, zlib.error):
            raise Rejected("unreadable_zip_member") from None

    def recording(self, archive, entry):
        data = self.read(archive, entry)
        name = entry.orig_filename.lower()
        if name.endswith(".gz"):
            try:
                with gzip.GzipFile(fileobj=io.BytesIO(data)) as stream:
                    data = self.bounded(stream, FILE_LIMIT)
            except (EOFError, OSError, zlib.error):
                raise Rejected("corrupt_gzip") from None
            name = name[:-3]
        format_name = name.rsplit(".", 1)[-1]
        if format_name == "fit":
            sessions = decode_fit(data)
            self.counts["fit_ends_from_elapsed"] += sum("boundary_evidence" in session for session in sessions)
            return format_name, hashlib.sha256(data).hexdigest(), sessions
        if format_name in ("gpx", "tcx"):
            return format_name, None, xml_sessions(data, format_name)
        raise Rejected("unsupported_original_format")

    def add(self, record):
        if len(self.records) >= MAX_RECORDS:
            raise Rejected("archive_record_limit")
        self.records.append(record)

    def finish(self):
        keys = [fit_key(r) for r in self.records if r.get("fit_sha256")]
        frequency = collections.Counter(keys)
        for record in self.records:
            if record.get("fit_sha256") and frequency[fit_key(record)] > 1:
                record["archive_exact_ambiguous"] = True
                self.issue("nonunique_exact_fingerprint", record["archive_evidence"]["locator"])
        dates = [r["session"]["start_time"][:10] for r in self.records]
        coverage = collections.Counter((date[:4], r["sport"]) for date, r in zip(dates, self.records))
        return {
            "records": len(dates), "oldest_day": min(dates, default=None),
            "newest_day": max(dates, default=None),
            "year_sport": [{"year": y, "sport": s, "count": n} for (y, s), n in sorted(coverage.items())],
            "diagnostics": dict(sorted(self.counts.items())),
        }


def xml_sessions(data, kind):
    # This preview accepts UTF-8 XML with no DTD or entity declarations.
    try:
        document = data.decode("utf-8-sig")
        if "\0" in document or re.search(r"<!\s*(DOCTYPE|ENTITY)", document, re.I):
            raise Rejected("unsupported_xml")
        root = ET.fromstring(document)
        for node in root.iter():
            node.tag = node.tag.rsplit("}", 1)[-1]
        if kind == "gpx" and root.tag == "gpx":
            groups = [(track.findtext("type"), track.findall("trkseg/trkpt/time"), [], None)
                      for track in root.findall("trk")]
        elif kind == "tcx" and root.tag == "TrainingCenterDatabase":
            groups = [(activity.get("Sport"), activity.findall("Lap/Track/Trackpoint/Time"),
                       activity.findall("Lap"), activity.findtext("Id"))
                      for activity in root.findall("Activities/Activity")]
        else:
            raise Rejected("unsupported_xml")
        sessions = []
        for sport, points, laps, start_text in groups:
            times = [iso(node.text) for node in points if node.text]
            elapsed = [number(lap.findtext("TotalTimeSeconds")) for lap in laps]
            distance = [number(lap.findtext("DistanceMeters")) for lap in laps]
            starts = [iso(lap.attrib["StartTime"]) for lap in laps if "StartTime" in lap.attrib]
            start = min(starts) if starts else iso(start_text) if start_text else min(times)
            end = max(times) if times else start + dt.timedelta(seconds=sum(v or 0 for v in elapsed))
            if end < start:
                raise Rejected("invalid_xml_boundary")
            session = {"start_time": start.isoformat(), "timestamp": end.isoformat(),
                       "total_elapsed_time": (end - start).total_seconds(), "recorded_sport": sport}
            if distance and all(v is not None for v in distance):
                session["total_distance"] = sum(distance)
            sessions.append(session)
        if not sessions:
            raise Rejected("missing_xml_activity")
        return sessions
    except (ET.ParseError, UnicodeError, OverflowError, ValueError) as error:
        if isinstance(error, Rejected):
            raise
        raise Rejected("corrupt_or_incomplete_xml") from None


def fit_key(record):
    return (record["fit_sha256"], record["session"]["start_time"], record["session"]["timestamp"])


def garmin(archive, scan):
    summaries = collections.defaultdict(list)
    files = []
    failed_fit = collections.defaultdict(list)

    def visit(container, parents):
        for name, entry in scan.members(container, parents):
            locator = [*parents, name]
            try:
                if not parents and re.search(r"summarizedActivities[^/]*\.json$", name):
                    for group in json.loads(scan.read(container, entry)):
                        for row in group["summarizedActivitiesExport"]:
                            scan.counts["summary_rows"] += 1
                            # The account export stores Unix millisecond numbers.
                            stamp = row.get("startTimeGmt")
                            if not isinstance(stamp, (int, float)) or not 1e12 <= stamp < 1e13 or not row.get("activityId"):
                                scan.issue("invalid_summary_row", locator)
                                continue
                            summaries[int(stamp // 1000)].append((row, locator))
                elif name.lower().endswith(".zip") and (parents or name.startswith(GARMIN_UPLOADS)):
                    if len(parents) >= 2:
                        raise Rejected("archive_depth_limit")
                    with zipfile.ZipFile(io.BytesIO(scan.read(container, entry, ZIP_LIMIT))) as nested:
                        visit(nested, tuple(locator))
                elif name.lower().endswith(".fit") and (parents or name.startswith(GARMIN_UPLOADS)):
                    scan.counts["fit_files"] += 1
                    _, digest, sessions = scan.recording(container, entry)
                    scan.counts["decoded_activity_fit_files"] += 1
                    files.append((digest, sessions, locator))
            except InvalidFitBoundary as error:
                failed_fit[error.start + int(EPOCH.timestamp())].append(locator)
                scan.issue(str(error), locator)
            except Rejected as error:
                if str(error) in ("archive_decompressed_limit", "archive_entry_limit"):
                    raise
                scan.issue(str(error), None if str(error) == "non_activity_fit" else locator)
            except (zipfile.BadZipFile, UnicodeError, ValueError, KeyError, TypeError, RecursionError):
                scan.issue("invalid_garmin_index_or_zip", locator)

    visit(archive, ())
    unique_files = {}
    for digest, sessions, locator in sorted(files, key=lambda item: item[2]):
        if digest in unique_files:
            scan.issue("duplicate_garmin_fit_file", locator)
        else:
            unique_files[digest] = (sessions, locator)
    starts = collections.Counter(int(iso(s[0]["start_time"]).timestamp()) for s, _ in unique_files.values())
    for digest, (sessions, locator) in unique_files.items():
        stamp = int(iso(sessions[0]["start_time"]).timestamp())
        summary = None
        if starts[stamp] == 1 and len(summaries[stamp]) == 1:
            summary, _ = summaries.pop(stamp)[0]
            scan.counts["linked_garmin_summaries"] += 1
        else:
            scan.issue("fit_without_unique_summary", locator)
        for i, session in enumerate(sessions):
            # FIT identity is stable even if a later export supplies a summary row.
            label = summary.get("activityType") if summary and len(sessions) == 1 else None
            sport = sport_label(label) if isinstance(label, str) else fit_sport(session)
            title = ((summary or {}).get("name") or sport) if len(sessions) == 1 else sport
            scan.add({
                "id": f"{digest}:{i + 1}", "fit_sha256": digest,
                "title": title, "sport": sport, "session": session,
                "archive_evidence": {"locator": locator, "format": "fit", "session_number": i + 1,
                                     "session_count": len(sessions), "garmin_summary": summary},
            })
    fit_starts = set(starts)
    summary_ids = collections.Counter(row["activityId"] for items in summaries.values() for row, _ in items)
    for stamp, items in summaries.items():
        for summary, locator in items:
            source_id = summary["activityId"]
            scan.issue("summary_without_unique_fit", [*locator, str(source_id)])
            if len(items) != 1 or summary_ids[source_id] != 1 or any(stamp + d in fit_starts for d in range(-90, 91)):
                scan.issue("unresolved_garmin_summary", [*locator, str(source_id)])
                continue
            elapsed_ms = number(summary.get("elapsedDuration"))
            timer_ms = number(summary.get("duration"))
            distance_cm = number(summary.get("distance"))
            start = dt.datetime.fromtimestamp(summary["startTimeGmt"] / 1000, UTC)
            end = start + dt.timedelta(milliseconds=elapsed_ms) if elapsed_ms is not None else None
            sport = sport_label(summary.get("activityType") or "")
            scan.add({
                "id": f"summary:{source_id}", "title": summary.get("name") or sport, "sport": sport,
                "session": {"start_time": start.isoformat(), "timestamp": end.isoformat() if end else None,
                            "total_elapsed_time": elapsed_ms / 1000 if elapsed_ms is not None else None,
                            "total_timer_time": timer_ms / 1000 if timer_ms is not None else None,
                            "total_distance": distance_cm / 100 if distance_cm is not None else None},
                "archive_evidence": {"locator": locator, "format": "garmin_summary", "garmin_summary": summary,
                                     "rejected_recordings_at_start": failed_fit[stamp]},
            })
            scan.counts["garmin_summary_only"] += 1


def strava_time(value):
    # The export uses English abbreviated months regardless of the machine's locale.
    match = re.fullmatch(r"([A-Za-z]{3}) (\d{1,2}), (\d{4}), (\d{1,2}):(\d\d):(\d\d) ([AP]M)", value)
    if match is None:
        raise ValueError("invalid CSV date")
    month, day, year, hour, minute, second, period = match.groups()
    months = "Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec".split()
    if not 1 <= int(hour) <= 12:
        raise ValueError("invalid CSV date")
    return dt.datetime(int(year), months.index(month) + 1, int(day),
                       int(hour) % 12 + (12 if period == "PM" else 0), int(minute), int(second), tzinfo=UTC)


def strava(archive, scan):
    index = dict(scan.members(archive))
    if "activities.csv" not in index:
        raise Rejected("missing_strava_index")
    data = scan.read(archive, index["activities.csv"])
    reader = csv.reader(io.StringIO(data.decode("utf-8-sig"), newline=""), strict=True)
    headers = [h.strip() for h in next(reader, [])]
    required = ("Activity ID", "Activity Date", "Activity Name", "Activity Type", "Filename")
    if any(headers.count(h) != 1 for h in required):
        raise Rejected("invalid_strava_index_headers")
    positions = collections.defaultdict(list)
    for index_number, header in enumerate(headers):
        positions[header].append(index_number)

    rows = []
    for row in reader:
        if len(rows) >= MAX_RECORDS:
            raise Rejected("archive_record_limit")
        rows.append(row)
    ids = collections.Counter(row[positions["Activity ID"][0]].strip()
                              for row in rows if len(row) == len(headers))
    referenced = set()
    for ordinal, row in enumerate(rows, 2):
        scan.counts["index_rows"] += 1
        locator = ["activities.csv", ordinal]
        if len(row) != len(headers):
            scan.issue("invalid_strava_row_length", locator)
            continue

        def cells(key):
            return [row[i].strip() for i in positions[key]]

        def cell(key):
            return next(iter(cells(key)), "")

        source_id = cell("Activity ID")
        if not re.fullmatch(r"[0-9]+", source_id) or ids[source_id] != 1:
            scan.issue("invalid_or_duplicate_strava_id", locator)
            continue
        filename = cell("Filename")
        sessions, digest, kind = [], None, "metadata"
        if filename:
            try:
                if not safe_name(filename) or not filename.startswith("activities/"):
                    raise Rejected("unsafe_original_reference")
                referenced.add(filename)
                if filename not in index:
                    raise Rejected("missing_original")
                kind, digest, sessions = scan.recording(archive, index[filename])
                scan.counts[f"decoded_{kind}_rows"] += 1
            except Rejected as error:
                if str(error) == "archive_decompressed_limit":
                    raise
                scan.issue(str(error), locator)
        else:
            scan.counts["empty_original_reference"] += 1

        date = cell("Activity Date")
        try:
            start = strava_time(date)
        except ValueError:
            start = None
            scan.issue("invalid_strava_index_date", locator)
        evidence = "csv_utc_assumed"
        if sessions:
            delta = None if start is None else abs((iso(sessions[0]["start_time"]) - start).total_seconds())
            evidence = "recording"
            scan.counts["csv_utc_confirmed" if delta is not None and delta <= 60 else "csv_utc_unconfirmed"] += 1
        else:
            if start is None:
                scan.issue("unrecoverable_index_row", locator)
                continue
            elapsed = number(cell("Elapsed Time"))
            moving = number(cell("Moving Time"))
            sessions = [{"start_time": start.isoformat(),
                         "timestamp": (start + dt.timedelta(seconds=elapsed)).isoformat() if elapsed is not None else None,
                         "total_elapsed_time": elapsed, "total_timer_time": moving}]
            scan.counts["metadata_only_rows"] += 1

        # The repeated fields have distinct positions. Normalize only corroborated
        # distance pairs or use the measured recording; preserve both CSV values.
        distances = cells("Distance")
        display, measured = [number(value) for value in (distances + ["", ""])[:2]]
        comparable = display is not None and measured is not None and abs(display * 1000 - measured) <= max(20, measured * .02)
        if not comparable and any(x for x in (display, measured)):
            scan.issue("unverified_csv_distance_units", locator)
        for i, session in enumerate(sessions):
            if len(sessions) == 1 and comparable and not session.get("total_distance"):
                session["total_distance"] = measured
            label = cell("Activity Type")
            sport = sport_label(label) if len(sessions) == 1 else fit_sport(session, sport_label(session.get("recorded_sport") or label))
            scan.add({
                "id": source_id if len(sessions) == 1 else f"{source_id}:{i + 1}",
                "fit_sha256": digest, "title": cell("Activity Name") or sport, "sport": sport,
                "description": cell("Activity Description"), "session": session,
                "archive_evidence": {"locator": locator, "original": filename, "format": kind,
                                     "source_activity_id": source_id, "source_sport": label,
                                     "index_datetime": date, "timezone_evidence": evidence,
                                     "distance_columns": distances, "elapsed_columns": cells("Elapsed Time"),
                                     "moving_time": cell("Moving Time"), "session_number": i + 1,
                                     "session_count": len(sessions)},
            })
    for name in index:
        if name.startswith("activities/") and name not in referenced:
            scan.issue("unreferenced_original", [name])


def read_archive(path, provider, include_digest):
    scan = Scan()
    with path.open("rb") as stream, zipfile.ZipFile(stream) as archive:
        (garmin if provider == "garmin" else strava)(archive, scan)
        if not scan.records:
            raise Rejected("no_recoverable_workouts")
        scan.records.sort(key=lambda record: record["id"])
        if not include_digest:
            return scan, None
        stream.seek(0)
        digest = hashlib.sha256()
        for block in iter(lambda: stream.read(MIB), b""):
            digest.update(block)
        return scan, digest.hexdigest()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("provider", choices=("garmin", "strava"))
    parser.add_argument("archive", type=Path)
    parser.add_argument("--import-local", action="store_true")
    parser.add_argument("--max-batches", type=int)
    args = parser.parse_args()
    if args.max_batches is not None and (not args.import_local or args.max_batches < 1):
        parser.error("--max-batches requires --import-local and a positive number")
    repo = Path(__file__).resolve().parents[2]
    archive = args.archive.resolve()
    if archive.is_relative_to(repo) or not archive.is_file():
        parser.error("Place the archive in private storage outside the repository.")
    try:
        scan, digest = read_archive(archive, args.provider, args.import_local)
        report = scan.finish()
        print(json.dumps(report, sort_keys=True), flush=True)
        if args.import_local:
            command = [str(repo / "scripts/local-postgres.sh"), "history-import"]
            if args.max_batches is not None:
                command.append(str(args.max_batches))
            payload = {"provider": args.provider, "archive_sha256": digest, "records": scan.records,
                       "issues": scan.issues, "report": report}
            subprocess.run(command, input=json.dumps(payload, allow_nan=False).encode(), check=True)
    except Rejected as error:
        sys.exit(f"Archive scan failed ({error}).")
    except (OSError, ValueError, zipfile.BadZipFile, csv.Error, subprocess.CalledProcessError, RecursionError):
        sys.exit("Archive preview failed. Check the archive and local Postgres setup.")


if __name__ == "__main__":
    main()
