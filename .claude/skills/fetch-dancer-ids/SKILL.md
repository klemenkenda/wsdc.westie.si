---
name: fetch-dancer-ids
description: Pull the current dancer list (slo_wsdc_ids.csv) from the live site wsdc.westie.si into the local repo. Use when asked to fetch, sync or update the dancer IDs / slo_wsdc_ids.csv from the server, or before a deploy that publishes the ID list.
---

# Fetch dancer IDs from wsdc.westie.si

Dancers are added and removed on the live site through `/admin`, which edits the server's
`slo_wsdc_ids.csv`. That file sits above the docroot, so it cannot be downloaded; the
script rebuilds the list from the `/dancer/<id>` links on the public ranking pages.

## Steps

1. Report first (no changes):
   ```bash
   bash .claude/skills/fetch-dancer-ids/fetch_ids.sh
   ```
2. If there are new dancers, append them:
   ```bash
   bash .claude/skills/fetch-dancer-ids/fetch_ids.sh --apply
   ```
   An optional last argument overrides the site URL (default `https://wsdc.westie.si`).
3. Show the user the added rows (`git diff slo_wsdc_ids.csv`). Names come from the site's
   page headings and may lack diacritics (č, š, ž, ć) — point that out so they can fix them.
4. Commit only if the user asks.

## Caveats

- **Removals are never applied.** IDs in the local CSV but not on the site are listed only:
  a dancer whose scrape failed is also absent from the pages. Ask the user before removing.
- A dancer added in `/admin` appears on the site only after the next scraper run.
- No dancer links at all means the site is down or `data/` has not been scraped yet — the
  script exits with an error rather than reporting every dancer as missing.
- Publishing the local CSV back to the server is a separate, destructive step: the deploy
  workflow's manual `publish_ids` input overwrites the server's list.
