# ipswich-jaffa-events-results
Wordpress Plugin for displaying JAFFA results with shortcodes and retrieval via an API

## Usage

- Hard code list of events
- Get list of meetings from api
- Get list of meeting races from api
- Click on race to view results

## Methods

# /events

Get list of events hosted by Ipswich JAFFA RC.

# /events/<eventId>/meetings

Get list of meetings for a given events. Information includes venue and meeting date.

# /events/<eventId>/meetings/<meetingId>/races

Get list of races for a event meeting. In most circumstances this will just be the one race but may include multiples e.g. Fun Run and Main race.

# /events/<eventId>/meetings/<meetingId>/races/<raceId>/results

The results for the given race. Contents is JSON format (generaetd from csv style data) and dynamic.

## Configuration / Environment

This plugin connects to a separate results database. Set these environment variables (or define as constants in a deployment-specific config) before activating the plugin:

- `EVENTS_RESULTS_DB_HOST` — database host (default: `DB_HOST` or `localhost`)
- `EVENTS_RESULTS_DB_NAME` — results database name
- `EVENTS_RESULTS_DB_USER` — results database user
- `EVENTS_RESULTS_DB_PASSWORD` — results database password
- `EVENTS_RESULTS_DB_PREFIX` — table prefix used in the results DB (default: `wp_`)

After installing/updating the plugin, activate it (or visit Settings → Permalinks) to flush rewrite rules so pretty URLs work.
