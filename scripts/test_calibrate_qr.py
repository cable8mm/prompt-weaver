#!/usr/bin/env python3
"""Tests for the OpenCV QR frame detector."""

import importlib.util
import tempfile
import unittest
from pathlib import Path

import cv2
import numpy as np


SCRIPT_PATH = Path(__file__).with_name("calibrate_qr.py")
SPEC = importlib.util.spec_from_file_location("calibrate_qr", SCRIPT_PATH)
MODULE = importlib.util.module_from_spec(SPEC)
assert SPEC.loader is not None
SPEC.loader.exec_module(MODULE)


class CalibrateQrTest(unittest.TestCase):
    def test_detects_outer_square_frame(self) -> None:
        image = np.full((800, 1000, 3), 35, dtype=np.uint8)
        cv2.rectangle(image, (280, 180), (680, 580), (255, 255, 255), -1)
        cv2.rectangle(image, (295, 195), (665, 565), (20, 20, 20), 12)
        cv2.rectangle(image, (80, 650), (920, 730), (255, 255, 255), -1)

        with tempfile.TemporaryDirectory() as directory:
            image_path = Path(directory) / "sign.png"
            self.assertTrue(cv2.imwrite(str(image_path), image))

            result = MODULE.detect_frame(str(image_path), 10, 20, 10)

        self.assertAlmostEqual(result["center_x"], 480, delta=15)
        self.assertAlmostEqual(result["center_y"], 380, delta=15)
        self.assertGreater(result["width"], 350)
        self.assertAlmostEqual(result["width"], result["height"], delta=15)

    def test_prefers_inner_qr_area_over_decorative_outer_frame(self) -> None:
        image = np.full((1216, 864, 3), 220, dtype=np.uint8)
        cv2.rectangle(image, (291, 784), (598, 1092), (70, 45, 25), 8)
        cv2.rectangle(image, (326, 821), (539, 1031), (255, 255, 255), -1)
        cv2.rectangle(image, (326, 821), (539, 1031), (70, 45, 25), 5)

        with tempfile.TemporaryDirectory() as directory:
            image_path = Path(directory) / "decorated-sign.png"
            self.assertTrue(cv2.imwrite(str(image_path), image))

            result = MODULE.detect_frame(str(image_path), 50, 80, 28)

        self.assertAlmostEqual(result["center_x"], 432.5, delta=10)
        self.assertAlmostEqual(result["center_y"], 926, delta=10)
        self.assertLess(result["width"], 250)

    def test_rejects_image_without_square_frame(self) -> None:
        image = np.full((800, 1000, 3), 35, dtype=np.uint8)

        with tempfile.TemporaryDirectory() as directory:
            image_path = Path(directory) / "blank.png"
            self.assertTrue(cv2.imwrite(str(image_path), image))

            with self.assertRaises(ValueError):
                MODULE.detect_frame(str(image_path), 50, 50, 30)


if __name__ == "__main__":
    unittest.main()
