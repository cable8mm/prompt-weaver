#!/usr/bin/env python3
"""Detect the outer QR placeholder frame and print its pixel geometry as JSON."""

import argparse
import json
import sys


def detect_frame(image_path: str, expected_x: float, expected_y: float, expected_width: float) -> dict[str, float]:
    import cv2

    image = cv2.imread(image_path, cv2.IMREAD_COLOR)
    if image is None:
        raise ValueError(f"Unable to read image: {image_path}")

    height, width = image.shape[:2]
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    edges = cv2.Canny(blurred, 40, 140)
    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (7, 7))
    edges = cv2.morphologyEx(edges, cv2.MORPH_CLOSE, kernel, iterations=2)

    contours, _ = cv2.findContours(edges, cv2.RETR_LIST, cv2.CHAIN_APPROX_SIMPLE)
    expected_x = expected_x / 100 * width
    expected_y = expected_y / 100 * height
    expected_width = max(1.0, expected_width / 100 * width)
    candidates = []

    for contour in contours:
        perimeter = cv2.arcLength(contour, True)
        if perimeter <= 0:
            continue

        polygon = cv2.approxPolyDP(contour, perimeter * 0.03, True)
        # A generated QR frame may have rounded corners. OpenCV then returns
        # more than four vertices (and, for the double edge of a thick frame,
        # may report the contour as non-convex), so do not require an exact
        # convex quadrilateral here. The size/position priors below and the
        # near-square check still keep unrelated contours out of contention.
        if len(polygon) < 4 or len(polygon) > 12:
            continue

        x, y, box_width, box_height = cv2.boundingRect(polygon)
        if box_width < max(40, expected_width * 0.4) or box_height < max(40, expected_width * 0.4):
            continue

        aspect = box_width / max(1, box_height)
        if aspect < 0.8 or aspect > 1.25:
            continue

        # Reject contours that only happen to have a square bounding box but
        # occupy very little of it. This is especially useful when accepting
        # rounded or thick frames, whose contour approximation is less exact.
        contour_area = cv2.contourArea(contour)
        bounding_area = float(box_width * box_height)
        if contour_area / max(1.0, bounding_area) < 0.45:
            continue

        center_x = x + box_width / 2
        center_y = y + box_height / 2
        horizontal_distance = abs(center_x - expected_x) / width
        vertical_distance = abs(center_y - expected_y) / height
        # The model may move the frame horizontally, so use x only as a
        # scoring prior rather than a hard search boundary.
        if vertical_distance > 0.35:
            continue

        # Generated artwork often wraps the actual white QR area in a larger
        # decorative frame. Prefer the square whose size is closest to the
        # configured QR width, while retaining position as a secondary prior.
        size_distance = abs(box_width - expected_width) / expected_width
        score = (
            size_distance * 2
            + horizontal_distance
            + vertical_distance
            + abs(aspect - 1)
        )
        candidates.append((score, box_width * box_height, x, y, box_width, box_height))

    if not candidates:
        raise ValueError("QR frame contour not found")

    candidates.sort(key=lambda item: (item[0], -item[1]))
    _, _, x, y, box_width, box_height = candidates[0]
    return {
        "left": x,
        "top": y,
        "width": box_width,
        "height": box_height,
        "center_x": x + box_width / 2,
        "center_y": y + box_height / 2,
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--image", required=True)
    parser.add_argument("--x", type=float, required=True)
    parser.add_argument("--y", type=float, required=True)
    parser.add_argument("--width", type=float, required=True)
    args = parser.parse_args()

    try:
        result = detect_frame(args.image, args.x, args.y, args.width)
    except ImportError:
        print("opencv-python is not installed", file=sys.stderr)
        return 2
    except ValueError as error:
        print(str(error), file=sys.stderr)
        return 3

    print(json.dumps(result))

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
