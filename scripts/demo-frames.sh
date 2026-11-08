#!/usr/bin/env sh
# Stills from a demo reel for the landing page.
#   demo-frames.sh <slug> "<time>|<label>" ["<time>|<label>" …]
# Reads public/media/demo/<slug>/reel.mp4, writes frames/NN.jpg (1080×1920) plus
# frames/frames.json ([{file,time,label}]) and frames/sheet.jpg (one frame per second,
# to pick the moments from). Run inside the app container after each render.
set -eu
slug="$1"; shift
dir="/app/public/media/demo/$slug"; out="$dir/frames"
mkdir -p "$out"
ffmpeg -loglevel error -y -i "$dir/reel.mp4" -vf "fps=1,scale=180:-1,tile=9x4:padding=4:margin=4" "$out/sheet.jpg"
i=0; json="["
for spec in "$@"; do
  t="${spec%%|*}"; label="${spec#*|}"; i=$((i+1)); f=$(printf '%02d.jpg' "$i")
  ffmpeg -loglevel error -y -ss "$t" -i "$dir/reel.mp4" -frames:v 1 -q:v 2 "$out/$f"
  m=$(echo "$t" | awk -F. '{printf "0:%02d", $1}')
  [ $i -gt 1 ] && json="$json,"
  json="$json{\"file\":\"$f\",\"time\":\"$m\",\"label\":\"$label\"}"
done
printf '%s]\n' "$json" > "$out/frames.json"
cat "$out/frames.json"
