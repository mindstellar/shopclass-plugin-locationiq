# Changelog

## 0.1.1

### Fixed
- The settings page shows when an API key is saved.

## 0.1.0

### New
- Address lookup through LocationIQ for listings with a street address.
- Settings for API key and region (us1 or eu1).
- Answers are kept up to a year, so an address is looked up once.
- Calls stay under the free plan's 2 requests a second.
- A daily request limit (5,000 by default); when it or LocationIQ's own limit is used up, lookups wait in the queue until it renews.
- Look up older listings with a street address from the settings page, in the background.
- Translation template in `languages/locationiq.pot`.
