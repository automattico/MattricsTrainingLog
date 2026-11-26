"""Decode the FIT messages used by the historical workout preview."""

import datetime as dt
import struct

UTC = dt.timezone.utc
EPOCH = dt.datetime(1989, 12, 31, tzinfo=UTC)
# Garmin FIT profile Sport and SubSport enums.
SPORTS = {
    1: "Run", 2: "Ride", 3: "Transition", 4: "Workout", 5: "Swim", 7: "Soccer",
    8: "Tennis", 10: "Workout", 11: "Walk", 12: "CrossCountrySkiing",
    13: "AlpineSkiing", 15: "Rowing", 17: "Hike", 18: "Multisport", 19: "WaterSport",
    21: "EBikeRide", 25: "Golf", 31: "RockClimbing", 33: "IceSkate", 35: "Snowshoe",
    37: "StandUpPaddling", 38: "Surfing", 41: "Kayaking", 53: "Dive",
    62: "Workout", 64: "Racket", 78: "WaterSport", 86: "Mobility", 88: "Canoe",
}

SUBSPORTS = {(4, 14): "Rowing", (10, 20): "WeightTraining", (64, 84): "Pickleball", (64, 85): "Padel"}


def fit_sport(session, fallback="Workout"):
    sport = session.get("fit_sport")
    return SUBSPORTS.get((sport, session.get("fit_subsport")), SPORTS.get(sport, fallback))


# FIT profile field number, scale, normalized workout field.
SESSION_FIELDS = {
    7: (1000, "total_elapsed_time"), 8: (1000, "total_timer_time"),
    9: (100, "total_distance"), 16: (1, "avg_heart_rate"),
    17: (1, "max_heart_rate"), 22: (1, "total_ascent"),
    25: (1, "first_lap_index"), 26: (1, "num_laps"), 254: (1, "message_index"),
}


class Rejected(ValueError):
    """Reason codes only. Exception messages may be included in a report."""


class InvalidFitBoundary(Rejected):
    def __init__(self, start):
        super().__init__("invalid_fit_session_boundary")
        self.start = start


def timestamp(value):
    return (EPOCH + dt.timedelta(seconds=value)).isoformat()


def crc_entry(value):
    for _ in range(8):
        value = (value >> 1) ^ (0xA001 if value & 1 else 0)
    return value


CRC_TABLE = tuple(map(crc_entry, range(256)))


def crc(data):
    value = 0
    for byte in data:
        value = (value >> 8) ^ CRC_TABLE[(value ^ byte) & 255]
    return value


def messages(data):
    pos = 0
    definitions = {}
    clock = None

    def take(size):
        nonlocal pos
        end = pos + size
        if end > len(data):
            raise Rejected("corrupt_fit")
        part = data[pos:end]
        pos = end
        return part

    while pos < len(data):
        header = take(1)[0]
        compressed = bool(header & 128)
        local = (header >> 5) & 3 if compressed else header & 15
        if not compressed and header & 64:
            _, architecture = take(2)
            if architecture not in (0, 1):
                raise Rejected("corrupt_fit")
            order = "big" if architecture else "little"
            number = int.from_bytes(take(2), order)
            fields = [tuple(take(3)) for _ in range(take(1)[0])]
            developer = [take(3)[1] for _ in range(take(1)[0])] if header & 32 else []
            definitions[local] = (number, order, fields, developer)
            continue
        if local not in definitions:
            raise Rejected("corrupt_fit")
        number, order, fields, developer = definitions[local]
        values = {}
        if compressed:
            if clock is None or not fields or fields[0][:2] != (253, 4):
                raise Rejected("corrupt_fit")
            offset = header & 31
            clock = (clock & ~31) + offset + (32 if offset < (clock & 31) else 0)
            values[253] = clock
            fields = fields[1:]
        for field, size, base in fields:
            part = take(size)
            if number not in (0, 18, 19) and field != 253:
                continue
            if (base & 31, size) not in {(0, 1), (2, 1), (4, 2), (6, 4)}:
                continue
            value = int.from_bytes(part, order)
            if value != (1 << (8 * size)) - 1:
                values[field] = value
        take(sum(developer))
        clock = values.get(253, clock)
        if number in (0, 18, 19):
            yield number, values


def decode_fit(data):
    if len(data) < 14 or data[0] not in (12, 14) or data[8:12] != b".FIT":
        raise Rejected("corrupt_fit")
    end = data[0] + struct.unpack_from("<I", data, 4)[0]
    if end + 2 != len(data):
        raise Rejected("unsupported_fit_layout" if end + 2 < len(data) else "corrupt_fit")
    payload = messages(memoryview(data)[data[0]:end])
    first = next(payload, None)
    if first is None or first[0] != 0 or 0 not in first[1]:
        raise Rejected("missing_fit_file_id")
    if first[1][0] != 4:
        raise Rejected("non_activity_fit")
    if crc(data):
        raise Rejected("fit_checksum")
    sessions = []
    laps = []
    for number, fields in payload:
        if number == 19 and 2 in fields and 253 in fields:
            laps.append({"start_time": timestamp(fields[2]), "timestamp": timestamp(fields[253]),
                         "message_index": fields.get(254)})
        if number != 18:
            continue
        # Relative FIT timestamps have no absolute UTC epoch.
        if fields.get(2, 0) < 0x10000000:
            raise Rejected("invalid_fit_session_boundary")
        session = {name: fields[key] / scale for key, (scale, name) in SESSION_FIELDS.items() if key in fields}
        start = timestamp(fields[2])
        end = fields.get(253)
        if end is not None and end > fields[2]:
            ended_at = timestamp(end)
        elif fields.get(7, 0) > 0:
            ended_at = (dt.datetime.fromisoformat(start) + dt.timedelta(milliseconds=fields[7])).isoformat()
            session.update(fit_original_end=end, boundary_evidence="start_plus_elapsed")
        else:
            raise InvalidFitBoundary(fields[2])
        session.update(start_time=start, timestamp=ended_at, fit_start=fields[2],
                       fit_sport=fields.get(5), fit_subsport=fields.get(6))
        sessions.append(session)
    if not sessions:
        raise Rejected("missing_fit_session")
    for session in sessions:
        session["laps"] = [lap for lap in laps if
                           session["start_time"] <= lap["start_time"] < lap["timestamp"] <= session["timestamp"]]
    return sessions


def decode_download(data):
    import hashlib
    import io
    import zipfile

    if data.startswith(b"PK"):
        with zipfile.ZipFile(io.BytesIO(data)) as archive:
            files = [entry for entry in archive.infolist() if entry.filename.lower().endswith(".fit")]
            if len(files) != 1 or files[0].file_size > 32 * 1024 * 1024:
                raise Rejected("unexpected_download")
            data = archive.read(files[0])
    sessions = decode_fit(data)
    return {"digest": hashlib.sha256(data).hexdigest(),
            "sessions": [{"sport": fit_sport(s), "session": s} for s in sessions]}


if __name__ == "__main__":
    import json
    import sys
    try:
        print(json.dumps(decode_download(sys.stdin.buffer.read(32 * 1024 * 1024 + 1))))
    except Exception:
        sys.exit(1)
