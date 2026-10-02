#!/bin/sh
# Regenerates the homepage title cards: imgs/tr-title-1..4.mp4 plus their -poster.jpg stills.
# Needs Google Chrome, node (>= 22) and ffmpeg. The words and the four looks live in hero-card.html
# (LINES, and the V1..V4 functions); every card loops seamlessly (hold, leave, empty, arrive, hold).
#   sh tools/title-cards/make.sh               write into imgs/
#   OUT=/some/folder sh tools/title-cards/make.sh   write somewhere else (to look first)
set -e
cd "$(dirname "$0")"
OUT="${OUT:-../../imgs}"
mkdir -p "$OUT"
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT
for v in 1 2 3 4; do
  node render.mjs "$v" "$WORK/v$v"
  ffmpeg -v error -y -framerate 30 -i "$WORK/v$v/%04d.png" -c:v libx264 -preset slow -tune animation \
    -crf 18 -profile:v high -level 4.0 -pix_fmt yuv420p -movflags +faststart -an "$OUT/tr-title-$v.mp4"
  # the poster is a held frame (t = 5.0 s), so it matches the first and last frame of the loop
  ffmpeg -v error -y -i "$WORK/v$v/0150.png" -q:v 2 -pix_fmt yuvj420p "$OUT/tr-title-$v-poster.jpg"
done
echo "title cards written to $OUT"
