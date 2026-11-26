"""Expose the shared FIT decoder to the archive tools."""
from pathlib import Path
import importlib.util

spec = importlib.util.spec_from_file_location("mattrics_fit", Path(__file__).resolve().parents[2] / "lib/fit_activity.py")
decoder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(decoder)
from_types = ("EPOCH", "InvalidFitBoundary", "Rejected", "SPORTS", "UTC", "crc", "decode_fit", "fit_sport")
globals().update({name: getattr(decoder, name) for name in from_types})
