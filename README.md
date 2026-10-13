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

## Plans

| Plan | Limit | Answers are kept |
|---|---|---|
| Free | 2 requests a second | 48 hours, as the free terms require |
| Paid | per your plan | for good |

The plugin waits for the next second when the free limit is reached. A rate limit (HTTP 429), a
server error or a refused key makes the lookup fail, and Shopclass tries again later. An
address LocationIQ cannot find is remembered for a day.

## Attribution

The free plan requires a visible link to LocationIQ wherever its data is shown. Print it in
your theme with `osc_geocode_attribution()`.

## Tests

```
./tests/run.sh
```

They need no network. Core is read from `../osclass`, or from `SHOPCLASS_CORE`.

## License

GPL-3.0-or-later.
