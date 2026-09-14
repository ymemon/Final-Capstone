# AI Visibility (AEO) data — integration spec for the SEO portal

## What this is

Three client WordPress sites now each run a small mu-plugin
(`ai-crawler-logger.php`) that logs every real request from a known AI/
answer-engine crawler (GPTBot, ClaudeBot, PerplexityBot, Amazonbot, Bingbot,
meta-externalagent, OAI-SearchBot, ChatGPT-User, CCBot, etc.) plus every
request to `/llms.txt`, to a per-day JSONL file on that site's own server.

A second mu-plugin (`ai-crawler-summary-api.php`) exposes that log data as a
small authenticated REST endpoint, so the portal can pull it the same way it
already pulls GSC/GA4 data from Google — poll an API on a schedule, store the
result, render it per tenant. Nothing new architecturally, just a third data
source alongside GSC and GA4.

This is a **leading indicator, not a citation/mention metric** — it tells you
whether AI crawlers are actually visiting a site, not whether that site gets
cited in an AI-generated answer. Worth labeling as such in the UI so it isn't
confused with a paid tool like Ahrefs Brand Radar (not currently on our plan)
if that's ever added later.

## The three live endpoints

| Site | Endpoint |
|---|---|
| azwebcorp.com | `https://azwebcorp.com/wp-json/azwc/v1/ai-crawler-summary` |
| everythingit.ie | `https://everythingit.ie/wp-json/azwc/v1/ai-crawler-summary` |
| prestigewindowsaz.com | `https://prestigewindowsaz.com/wp-json/azwc/v1/ai-crawler-summary` |

**Method:** `GET`, query params `?days=14&limit=25` (both optional — `days`
defaults to 14, capped at 90; `limit` caps `recent_hits`, defaults to 25,
capped at 100).

**Auth:** header `X-AZWC-API-Key: <key>`, one distinct key per site. No key
or a wrong key returns `401` (fails closed, never fails open). **The actual
key values are not in this document** — they're in
`~/.claude-tools/ai-crawler-summary-api-keys.json` on Yasir's machine; pull
them from there when wiring up secrets/env vars, and never expose them
client-side — this call must happen from the portal's backend only.

**Response shape** (real example, azwebcorp.com):

```json
{
  "site": "azwebcorp.com",
  "days": 14,
  "generated_at": "2026-09-03T01:39:47+00:00",
  "total_hits": 112,
  "llms_txt_hits": 0,
  "by_bot": {
    "meta-externalagent": 38,
    "Amazonbot": 34,
    "Bingbot": 24,
    "GPTBot": 6,
    "ClaudeBot": 4,
    "PerplexityBot": 3,
    "Claude-User": 2,
    "CCBot": 1
  },
  "recent_hits": [
    {
      "time": "2026-09-03T01:32:14+00:00",
      "bot": "meta-externalagent",
      "uri": "/2025/12/05/small-business-web-design-arizona/",
      "method": "GET",
      "is_llms_txt": false
    }
  ]
}
```

Note: `bot` is `null` for a request that matched only `/llms.txt` (no known
crawler user-agent) — see `is_llms_txt`.

## What to build on the portal side

1. **New table**, same shape as the existing `gsc_daily_totals` /
   `ga4_daily_totals` pattern — something like `ai_crawler_daily_totals`
   (tenant_id, date, total_hits, llms_txt_hits, by_bot JSON, synced_at).
2. **New `data_sources` entries** (or reuse the existing pattern) per tenant
   holding that tenant's endpoint URL + API key reference — don't hardcode
   the three URLs/keys in application code, store them the same way
   `google_connections` stores per-tenant Google credentials.
3. **New `sync_runs` job type** that polls each tenant's endpoint on a
   schedule (daily is fine — data only changes a few times a day per site),
   upserts into the new table. Same retry/error-logging convention as the
   existing GSC/GA4 sync jobs.
4. **UI**: a per-tenant "AI Visibility" section —
   - total crawler hits over time (reuse whatever chart component already
     renders GSC clicks-over-time),
   - breakdown by bot (bar chart or simple table),
   - `/llms.txt` hit count as its own stat,
   - a "0 hits in 30 days" flag/warning state, same visual language as
     however zero-click GSC pages already get flagged.
5. **Tenant mapping** — map each endpoint to the correct existing tenant:
   AZ Web Corp → azwebcorp.com, Everything IT → everythingit.ie, Prestige
   Windows → prestigewindowsaz.com. PaloVerde has no endpoint yet (that site
   doesn't run the crawler logger — it's still on a temp domain).

## One dependency worth flagging

The known tenant-scoping gap (dashboard doesn't yet filter by `memberships`)
applies to this new data too — don't ship the AI Visibility tab in a way
that's reachable before that access-control fix is in, or it inherits the
same cross-client exposure risk as everything else on the dashboard.
