"""Exercise the archive parsers and emit only synthetic manifests for Postgres tests."""

import csv
import datetime as dt
import gzip
import hashlib
import io
import json
import pathlib
import stat
import struct
import sys
import zipfile

sys.dont_write_bytecode = True
sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[2] / "scripts/postgres"))

import archive_preview as preview
from fit_activity import EPOCH, Rejected, crc, decode_fit

START = dt.datetime(2020, 1, 2, 12, tzinfo=preview.UTC)


def fit(*sessions, file_type=4):
    def definition(local, message, fields):
        return bytes([0x40 | local, 0, 0]) + struct.pack("<H", message) + bytes([len(fields)]) + b"".join(map(bytes, fields))

    payload = definition(0, 0, [(0, 1, 0)]) + bytes([0, file_type])
    payload += definition(1, 18, [(2, 4, 0x86), (253, 4, 0x86), (7, 4, 0x86),
                                   (8, 4, 0x86), (9, 4, 0x86), (5, 1, 0)])
    for start, ending, sport in sessions:
        origin = int((START - EPOCH).total_seconds()) + start
        payload += bytes([1]) + struct.pack("<IIIIIB", origin, origin + ending, 600000, 585000, 250000, sport)
    header = struct.pack("<BBHI4s", 12, 0x20, 0, len(payload), b".FIT")
    return header + payload + struct.pack("<H", crc(header + payload))


def zipped(entries):
    handle = io.BytesIO()
    with zipfile.ZipFile(handle, mode="w", compression=zipfile.ZIP_DEFLATED) as archive:
        for path, value in entries:
            archive.writestr(path, value)
    return handle.getvalue()


def build():
    # One exact recording with two separate legs, plus time/sport controls.
    exact = fit((0, 600, 1), (600, 600, 2))
    distinct = fit((3600, 600, 1))
    ambiguous_a = fit((7200, 600, 1))
    ambiguous_b = fit((7250, 600, 1))
    link = zipfile.ZipInfo(preview.GARMIN_UPLOADS + "symlink.fit")
    link.create_system = 3
    link.external_attr = (stat.S_IFLNK | 0o777) << 16
    drift = fit((9000, 0, 1))
    assert decode_fit(drift)[0]["boundary_evidence"] == "start_plus_elapsed"
    garmin = zipped([
        (preview.GARMIN_UPLOADS + "history.zip", zipped([
            ("a.fit", exact), ("b.fit", distinct), ("c.fit", ambiguous_a), ("d.fit", ambiguous_b),
            ("bad.fit", b".FIT"), ("crc.fit", exact[:-1] + bytes([exact[-1] ^ 1])),
            ("wellness.fit", fit(file_type=9)), ("../escape.fit", exact),
            ("round-two.zip", zipped([("too-deep.zip", zipped([("ignored.fit", exact)]))])),
        ])),
        (link, "ignored.fit"),
        ("outside/ignored.fit", exact),
    ])
    output = io.StringIO(newline="")
    writer = csv.writer(output)
    writer.writerow(["Activity ID", "Activity Date", "Activity Name", "Activity Type",
                     "Filename", "Elapsed Time", "Distance", "Elapsed Time", "Distance", "Moving Time"])
    rows = [
        (1, 0, "Edited archive label", "Workout", "activities/exact.fit.gz", "2.5", "2500"),
        (2, 3620, "Probable", "Run", "activities/probable.gpx", "2.5", "2500"),
        (3, 7230, "Ambiguous", "Run", "activities/ambiguous.tcx.gz", "2.5", "2500"),
        (4, 15000, "Manual only", "Weight Training", "", "1.2", "1.2"),
        (5, 17000, "Missing original", "Walk", "activities/missing.fit", "", ""),
        (6, 19000, "Corrupt original", "Walk", "activities/corrupt.gpx", "", ""),
        (7, 21000, "Opaque original", "Walk", "activities/unknown.xyz", "", ""),
        (8, 23000, "Unsafe original", "Walk", "../private.fit", "", ""),
    ]
    rows.append((9, 40009, "Synthetic soccer", "Football (Soccer)", "", "", ""))
    rows += [(i, 40000 + i, "Synthetic extra", "Yoga", "", "", "") for i in range(10, 105)]
    for i, offset, title, sport, path, km, meters in rows:
        date = (START + dt.timedelta(seconds=offset)).strftime("%b %d, %Y, %I:%M:%S %p")
        writer.writerow([i, date, title, sport, path, 600, km, 590, meters, 585])

    def stamp(delta):
        return (START + dt.timedelta(seconds=delta)).isoformat()

    gpx = (
        '<gpx xmlns="http://www.topografix.com/GPX/1/1"><trk><trkseg>'
        f'<trkpt><time>{stamp(3620)}</time></trkpt><trkpt><time>{stamp(4220)}</time></trkpt>'
        '</trkseg></trk></gpx>'
    )
    tcx = (
        '<TrainingCenterDatabase><Activities><Activity Sport="Running">'
        f'<Lap StartTime="{stamp(7230)}"><TotalTimeSeconds>600</TotalTimeSeconds>'
        '<DistanceMeters>2500</DistanceMeters></Lap>'
        '</Activity></Activities></TrainingCenterDatabase>'
    )
    strava = zipped([
        ("activities.csv", output.getvalue()), ("activities/exact.fit.gz", gzip.compress(exact, mtime=0)),
        ("activities/probable.gpx", gpx), ("activities/ambiguous.tcx.gz", gzip.compress(tcx.encode(), mtime=0)),
        ("activities/corrupt.gpx", "<gpx"), ("activities/unknown.xyz", b"?"),
        ("activities/orphan.fit", exact),
    ])
    return {"garmin": garmin, "strava": strava}


def get_manifests():
    manifests = {}
    for provider, data in build().items():
        scan = preview.Scan()
        with zipfile.ZipFile(io.BytesIO(data)) as archive:
            getattr(preview, provider)(archive, scan)
        scan.records.sort(key=lambda record: record["id"])
        manifests[provider] = {"provider": provider, "archive_sha256": hashlib.sha256(data).hexdigest(),
                               "records": scan.records, "report": scan.finish(), "issues": scan.issues}
    return manifests


def validate(payloads):
    garmin = payloads["garmin"]
    strava = payloads["strava"]
    assert garmin["report"]["records"] == 5
    assert garmin["report"]["diagnostics"] == {
        "archive_depth_limit": 1, "corrupt_fit": 1, "decoded_activity_fit_files": 4,
        "fit_ends_from_elapsed": 0, "fit_checksum": 1, "fit_files": 7,
        "fit_without_unique_summary": 4, "non_activity_fit": 1, "unsafe_zip_member": 2,
    }
    assert strava["report"]["records"] == 105
    stats = strava["report"]["diagnostics"]
    assert stats["index_rows"] == 104 and stats["metadata_only_rows"] == 101
    assert stats["decoded_fit_rows"] == stats["decoded_gpx_rows"] == stats["decoded_tcx_rows"] == 1
    assert stats["missing_original"] == stats["unsafe_original_reference"] == stats["unreferenced_original"] == 1
    assert stats["corrupt_or_incomplete_xml"] == stats["unsupported_original_format"] == 1
    assert stats["unverified_csv_distance_units"] == 1
    manual = next(r for r in strava["records"] if r["id"] == "4")
    assert manual["sport"] == "WeightTraining" and manual["session"]["total_elapsed_time"] == 600
    assert "total_distance" not in manual["session"] and manual["archive_evidence"]["distance_columns"] == ["1.2", "1.2"]
    soccer = next(r for r in strava["records"] if r["id"] == "9")
    assert soccer["sport"] == "Soccer" and soccer["archive_evidence"]["source_sport"] == "Football (Soccer)"
    tiny = preview.Scan()
    tiny.bytes_left = 2
    for scan, limit, message in [(preview.Scan(), 2, "member_size_limit"), (tiny, 4, "archive_decompressed_limit")]:
        try:
            scan.bounded(io.BytesIO(b"data"), limit)
        except Rejected as error:
            assert str(error) == message
        else:
            raise AssertionError("invalid size accepted")
    try:
        preview.xml_sessions(b'<!DOCTYPE gpx [<!ENTITY x "expanded">]><gpx>&x;</gpx>', "gpx")
    except Rejected as error:
        assert str(error) == "unsupported_xml"
    else:
        raise AssertionError("XML with entities accepted")


if __name__ == "__main__" and sys.argv[1:] == ["--fit-download"]:
    sys.stdout.buffer.write(zipped([("synthetic.fit", fit((0, 600, 1)))]))
    raise SystemExit(0)

if __name__ == "__main__":
    payloads = get_manifests()
    validate(payloads)
    print(json.dumps(payloads) if sys.argv[1:] == ["--manifest"] else "PASS bounded Garmin and Strava archive diagnostics")
