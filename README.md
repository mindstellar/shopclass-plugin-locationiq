# LocationIQ Geocoder

Finds the exact map position of a Shopclass listing from its street address, using
[LocationIQ](https://locationiq.com/). Distance search then uses that point instead of the
city or region centre.

## Requirements

- Shopclass 7.0.0 or later
- PHP 8.0 or later, with the curl extension
- A LocationIQ access token (the free plan is enough to start)

It works on shared hosting. Nothing runs on a normal page load; the plugin is built only when
an address is looked up.

## Setup

1. Install and activate the plugin, then open **Plugins → LocationIQ Geocoder**.
2. Paste your access token into *API key*, pick the *Region* and the *Plan*, and save.
3. Open **Settings → General → Maps** and choose *LocationIQ* under *Address lookup*.

The saved key is never shown again. Leave the box blank to keep it.

## When lookups run

A lookup runs from the job queue after a listing with a street address is saved, not while the
visitor waits. Cron (or auto-cron) must be running; that is enough on shared hosting, and a
queue worker makes it faster.

## Plans

| Plan | Limit | Answers are kept |
|---|---|---|
| Free | 2 requests a second | 48 hours, as the free terms require |
| Paid | per your plan | up to a year |

The plugin waits for the next second when the free limit is reached. A server error or a refused
key makes the lookup fail, and Shopclass tries again later. An address LocationIQ cannot find is
remembered for a day.

## Daily limit

**Daily request limit** (default 5,000, the free plan's allowance; 0 is no limit) counts lookups
from midnight UTC. When it is used up, or LocationIQ answers with a rate limit (HTTP 429), the
lookups stay queued and run again once the limit renews: the next day for the daily limit, a
minute later for the per-second and per-minute ones. Nobody waits for them on the site.
The settings page shows how many listings wait, and when lookups go on.

## Older listings

Tick **Older listings** and save to look up every listing that has a street address and no map
position yet. One background job queues them 50 at a time, within the daily limit.
It needs Shopclass 7.0.0 or later.

## Attribution

The free plan requires a visible link to LocationIQ wherever its data is shown. Print it in
your theme with `osc_geocode_attribution()`.

## Uninstall

The plugin's settings are removed. If *Address lookup* in Settings → General → Maps named this
plugin, it is reset to none.

## Tests

```
./tests/run.sh
```

They need no network. Core is read from `../osclass`, or from `SHOPCLASS_CORE`.

## License

GPL-3.0-or-later.
