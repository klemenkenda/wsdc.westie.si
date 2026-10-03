#!/usr/bin/env bash
# Sync slo_wsdc_ids.csv with the dancer list on the live site.
#
# The CSV lives above the docroot on the server, so it is not downloadable. Every dancer in
# it does get a /dancer/<id> link on the public ranking pages, so the ID list is rebuilt
# from those, and names for new IDs come from each dancer page's <h1>.
#
# Usage: fetch_ids.sh [--apply] [base_url]
#   without --apply: report only; with --apply: append new dancers to slo_wsdc_ids.csv.
# Dancers missing from the site are reported, never removed — a dancer whose scrape failed
# can be absent from the pages while still being in the server's CSV.
set -euo pipefail

APPLY=0
if [ "${1:-}" = "--apply" ]; then APPLY=1; shift; fi
BASE="${1:-https://wsdc.westie.si}"
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
CSV="$ROOT/slo_wsdc_ids.csv"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

for p in / /ranking/leaders/all /ranking/followers/all /ranking/absolute /history /analysis; do
  curl -sSf --max-time 30 "$BASE$p" || { echo "ERROR: could not fetch $BASE$p" >&2; exit 1; }
done | grep -oE '/dancer/[0-9]+' | sed 's#/dancer/##' | sort -u > "$TMP/live"

if [ ! -s "$TMP/live" ]; then
  echo "ERROR: no dancer links found on $BASE — site down, or data not scraped yet." >&2
  exit 1
fi

tail -n +2 "$CSV" | cut -d, -f1 | tr -d '\r' | sort -u > "$TMP/local"
comm -23 "$TMP/live" "$TMP/local" > "$TMP/new"
comm -13 "$TMP/live" "$TMP/local" > "$TMP/missing"

echo "Site: $BASE — $(wc -l < "$TMP/live") dancers; local CSV: $(wc -l < "$TMP/local")"

if [ -s "$TMP/new" ]; then
  echo "New on site ($(wc -l < "$TMP/new")):"
  while read -r id; do
    name=$(curl -sS --max-time 30 "$BASE/dancer/$id" | grep -oE '<h1[^>]*>[^<]+' | head -1 \
      | sed -E 's/<h1[^>]*>//; s/^[[:space:]]+//; s/[[:space:]]+$//' \
      | sed -e 's/&amp;/\&/g' -e "s/&#0\?39;/'/g" -e 's/&quot;//g')
    echo "  $id,\"$name\""
    echo "$id,\"$name\"" >> "$TMP/rows"
  done < "$TMP/new"
else
  echo "No new dancers."
fi

if [ -s "$TMP/missing" ]; then
  echo "In local CSV but not linked on site (not removed — check /admin):"
  sed 's/^/  /' "$TMP/missing"
fi

if [ "$APPLY" = 1 ] && [ -s "$TMP/rows" ]; then
  # Keep the file's newline convention and make sure the last line is terminated.
  [ -n "$(tail -c1 "$CSV")" ] && echo >> "$CSV"
  cat "$TMP/rows" >> "$CSV"
  echo "Appended $(wc -l < "$TMP/rows") rows to slo_wsdc_ids.csv"
fi
