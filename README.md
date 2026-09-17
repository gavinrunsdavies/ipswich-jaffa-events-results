# ipswich-jaffa-events-results
WordPress plugin to surface JAFFA event results from a separate results database.

## Overview
This plugin exposes a small read-only REST API, shortcodes, and a set of pretty URLs to view meetings, races and race results (CSV → JSON) and to download PDFs stored as blobs in the results DB.

## Configuration
Set the following environment variables (or define them as constants before activating the plugin):

- `EVENTS_RESULTS_DB_HOST` — database host (default: `DB_HOST` or `localhost`)
- `EVENTS_RESULTS_DB_NAME` — results database name
- `EVENTS_RESULTS_DB_USER` — results database user
- `EVENTS_RESULTS_DB_PASSWORD` — results database password

After installing or updating the plugin, visit **Settings → Permalinks** (or reactivate the plugin) to flush rewrite rules so the pretty URLs work.

## Shortcodes

- `[ipswich-jaffa-events-results event-id="<id>"]` — renders a table of meetings for the given event, with links to race results (PDF or HTML results page).

## REST API
Base path: `/wp-json/ipswich-events-api/v1`

- `GET /events`
	- Returns: array of events `{ id, name, info }`.

- `GET /events/{eventId}/meetings`
	- Returns: array of meetings. Each meeting object contains `meetingId`, `meetingName`, `meetingDate`, `meetingVenue`, and `results` (array of `{ id, name, type }`).

- `GET /events/{eventId}/meetings/{meetingId}/races`
	- Returns: array of races for the meeting (`id`, `name`, `type`).

- `GET /events/{eventId}/meetings/{meetingId}/races/{raceId}/results`
	- If the stored result `type` is `CSV`, the plugin parses the CSV payload and returns a JSON array (rows as objects using the CSV header). If no results are present or the type is not `CSV`, an empty array is returned.
	- The endpoint validates that the provided `meetingId` and `eventId` match the race record; otherwise a `404` error is returned.

Notes: the API is read-only; there is no authentication implemented in this plugin.

## Pretty URLs / Templates
- Results HTML page (DataTables) is served via a pretty URL and the plugin template:
	- `/events/{eventId}/meetings/{meetingId}/races/{raceId}/results`
	- This route renders `html/DisplayRaceResults.php` and loads the client assets (DataTables / ag-grid) via WordPress enqueues.

- PDF download route (streams PDF binary from DB):
	- `/events/{eventId}/meetings/{meetingId}/races/{raceId}/results/pdf`
	- This route renders `html/DisplayRacePdf.php` which streams the PDF with `Content-Type: application/pdf`.

## Development / CI
- A simple GitHub Actions workflow is included at `.github/workflows/php-lint.yml` to run `php -l` on repository PHP files.
- `composer.json` contains a classmap autoload for the `api/` classes.

## Notes / Troubleshooting
- If you change rewrite rules or install the plugin, flush rewrite rules by visiting **Settings → Permalinks**.
- Ensure the results DB is reachable from your WordPress host and that the `wp_ije_*` tables exist and contain expected columns:
	- `wp_ije_events` (id, name, info)
	- `wp_ije_meetings` (id, event_id, name, date, venue)
	- `wp_ije_race_results` (id, meeting_id, name, type, results)
