"""Puts an AI-drawn works stage back on the camera of the approved frames.

Usage: alinhar.py OUT IMAGE REF [REF ...]

Each edit by the image AI drifts the framing a little, and along a chain of stages the clip ends up jumping or moving
the camera (construção, 2026-11-28). The parts that do not change (the street, the neighbours, the poles, a room's
window) are matched between IMAGE and each REF (SIFT + RANSAC homography: what the works changed is left out as
outliers); the REF with the most matches wins, IMAGE is warped onto it, and a border the warp leaves empty is filled
from that REF. With no sound match IMAGE is written unchanged. Prints one JSON line.
"""
import json
import sys

import cv2
import numpy as np

LARGURA = 1600          # detection width: plenty of features, fast
MIN_INLIERS = 40        # fewer: no trustworthy match, leave the image as it is
MAX_VAZIO = 0.06        # more of the frame to fill from REF: a bad edit, better redrawn ("grande": the caller decides)
ESCALA = (0.75, 1.33)   # a stage is the same camera: a bigger change is a bad match, not drift


def cinzento(img):
    escala = LARGURA / img.shape[1]
    return cv2.cvtColor(cv2.resize(img, None, fx=escala, fy=escala, interpolation=cv2.INTER_AREA), cv2.COLOR_BGR2GRAY), escala


def homografia(img, ref, sift):
    a, ea = cinzento(img)
    b, eb = cinzento(ref)
    ka, da = sift.detectAndCompute(a, None)
    kb, db = sift.detectAndCompute(b, None)
    if da is None or db is None or len(ka) < MIN_INLIERS or len(kb) < MIN_INLIERS:
        return None, 0
    pares = cv2.BFMatcher(cv2.NORM_L2).knnMatch(da, db, k=2)
    bons = [m for m, n in (p for p in pares if len(p) == 2) if m.distance < 0.75 * n.distance]
    if len(bons) < MIN_INLIERS:
        return None, 0
    pa = np.float32([ka[m.queryIdx].pt for m in bons]) / ea
    pb = np.float32([kb[m.trainIdx].pt for m in bons]) / eb
    h, mascara = cv2.findHomography(pa, pb, cv2.RANSAC, 3.0 / ea)
    if h is None:
        return None, 0
    inliers = int(mascara.sum())
    s = np.sqrt(abs(np.linalg.det(h[:2, :2])))
    if inliers < MIN_INLIERS or not ESCALA[0] < s < ESCALA[1] or abs(h[2, 0]) > 1e-3 or abs(h[2, 1]) > 1e-3:
        return None, inliers
    return h, inliers


def main():
    saida, imagem, refs = sys.argv[1], sys.argv[2], sys.argv[3:]
    img = cv2.imread(imagem, cv2.IMREAD_COLOR)
    sift = cv2.SIFT_create(nfeatures=6000)
    melhor = (None, 0, -1)
    for i, caminho in enumerate(refs):
        h, n = homografia(img, cv2.imread(caminho, cv2.IMREAD_COLOR), sift)
        if h is not None and n > melhor[1]:
            melhor = (h, n, i)
    h, n, i = melhor
    if h is None:
        cv2.imwrite(saida, img)
        print(json.dumps({"ok": False, "inliers": n}))
        return
    ref = cv2.imread(refs[i], cv2.IMREAD_COLOR)
    alto, largo = ref.shape[:2]
    torto = cv2.warpPerspective(img, h, (largo, alto), flags=cv2.INTER_CUBIC)
    cheio = cv2.warpPerspective(np.full(img.shape[:2], 255, np.uint8), h, (largo, alto), flags=cv2.INTER_NEAREST)
    vazio = float((cheio == 0).mean())
    # the empty border filled from REF, blended over ~30 px so no seam shows
    peso = cv2.GaussianBlur(cv2.erode(cheio, np.ones((31, 31), np.uint8)).astype(np.float32) / 255, (0, 0), 10)[..., None]
    torto = (torto.astype(np.float32) * peso + ref.astype(np.float32) * (1 - peso)).astype(np.uint8)
    cv2.imwrite(saida, torto)
    centro = h @ np.array([largo / 2, alto / 2, 1.0])
    print(json.dumps({"ok": True, "grande": vazio > MAX_VAZIO, "vazio": round(vazio, 3), "ref": i, "inliers": n, "escala": round(float(np.sqrt(abs(np.linalg.det(h[:2, :2])))), 4),
                      "desvio_px": [round(float(centro[0] / centro[2] - largo / 2)), round(float(centro[1] / centro[2] - alto / 2))]}))


if __name__ == "__main__":
    main()
