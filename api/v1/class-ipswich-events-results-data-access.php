<?php
/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace IpswichEventResultsAPI\V1;

require_once plugin_dir_path(__FILE__) . '/../config.php';

class Ipswich_Events_Results_Data_Access
{
	private $rdb;

	public function __construct()
	{
		// Do not open DB connection at plugin load. Lazy init when needed.
		$this->rdb = null;
	}

	private function get_rdb()
	{
		if ($this->rdb instanceof \wpdb) {
			return $this->rdb;
		}

		$host = defined('EVENTS_RESULTS_DB_HOST') ? EVENTS_RESULTS_DB_HOST : (defined('DB_HOST') ? DB_HOST : 'localhost');
		$this->rdb = new \wpdb(EVENTS_RESULTS_DB_USER, EVENTS_RESULTS_DB_PASSWORD, EVENTS_RESULTS_DB_NAME, $host);
		$this->rdb->show_errors();

		return $this->rdb;
	}

	public function get_race_results($race_id)
	{
		$rdb = $this->get_rdb();
		$race_table = 'wp_ije_race_results';
		$meetings_table = 'wp_ije_meetings';
		$events_table = 'wp_ije_events';
		$sql = $rdb->prepare(
			"SELECT r.id, r.results, r.name, r.meeting_id, m.event_id, m.name AS meeting_name, m.date, m.venue, r.type, e.name AS event_name, e.info AS event_info
                                FROM `{$race_table}` r
                                INNER JOIN `{$meetings_table}` m ON m.id = r.meeting_id
                                LEFT JOIN `{$events_table}` e ON e.id = m.event_id
                                WHERE r.id=%d",
			$race_id
		);

		return $this->get_results($sql, 'get_race_results');
	}

	public function get_races($meeting_id)
	{
		$rdb = $this->get_rdb();
		$sql = $rdb->prepare("SELECT r.id, r.name, r.type FROM `wp_ije_race_results` r WHERE r.meeting_id=%d", $meeting_id);

		return $this->get_results($sql, 'get_races');
	}

	public function get_meetings($event_id)
	{
		$rdb = $this->get_rdb();

		// LENGTH(r.results) lets us tell a race with no result content apart
		// from one that genuinely has results, without pulling the (potentially
		// large) blob itself just to check it's non-empty.
		$sql = $rdb->prepare(
			"SELECT m.id AS meetingId, m.name AS meetingName, m.date AS meetingDate, m.venue AS meetingVenue, r.id as resultId, r.name as resultName, r.type as resultType, LENGTH(r.results) as resultLength
                                FROM `wp_ije_meetings` m
                                LEFT JOIN `wp_ije_race_results` r on r.meeting_id = m.id
                                WHERE m.event_id=%d
                                ORDER BY m.date ASC, m.name ASC, r.name ASC;",
			$event_id
		);

		$results = $this->get_results($sql, 'get_meetings');

		if ($results == null)
			return null;

		$meetings = [];
		$csvRaceIds = [];

		foreach ($results as $row) {
			if (!isset($meetings[$row->meetingId])) {
				$meetings[$row->meetingId] = [
					'meetingId' => $row->meetingId,
					'meetingName' => $row->meetingName,
					'meetingDate' => $row->meetingDate,
					'meetingVenue' => $row->meetingVenue,
					'results' => []
				];
			}

			// Include every race that exists for this meeting, even ones
			// with no result content — 'hasResults' tells the template
			// whether to render a link or a plain "no results" label.
			if (!empty($row->resultId)) {
				$hasResults = !empty($row->resultLength);

				$meetings[$row->meetingId]['results'][] = [
					'id' => $row->resultId,
					'name' => $row->resultName,
					'type' => $row->resultType,
					'hasResults' => $hasResults,
					'resultCount' => null
				];

				// Only CSV races have a meaningful "number of results";
				// queue them up for a targeted follow-up fetch below.
				if ($hasResults && strtoupper((string) $row->resultType) === 'CSV') {
					$csvRaceIds[] = (int) $row->resultId;
				}
			}
		}

		if (!empty($csvRaceIds)) {
			$counts = $this->get_csv_row_counts($csvRaceIds);
			foreach ($meetings as &$meeting) {
				foreach ($meeting['results'] as &$result) {
					if (isset($counts[$result['id']])) {
						$result['resultCount'] = $counts[$result['id']];

						// Non-empty blob that parsed down to zero usable
						// rows (header-only, or every row column-mismatched)
						// is functionally "no data" — treat it the same way.
						if ($result['resultCount'] === 0) {
							$result['hasResults'] = false;
						}
					}
				}
				unset($result);
			}
			unset($meeting);
		}

		return array_values($meetings);
	}

	/**
	 * Fetches and parses CSV blobs only for the given race ids — deliberately
	 * a separate, targeted query rather than pulling every blob in the main
	 * get_meetings() query, since PDF blobs in particular can be large and
	 * don't need a "count" at all.
	 */
	private function get_csv_row_counts(array $race_ids)
	{
		$counts = [];
		$idsToFetch = [];

		// Serve from cache where we can — avoids re-fetching and
		// re-parsing potentially large, unchanged blobs on every
		// page view. 12 hours is a reasonable default since these
		// are past results that rarely change once imported.
		foreach ($race_ids as $race_id) {
			$cached = get_transient('ije_csv_count_' . $race_id);
			if ($cached !== false) {
				$counts[$race_id] = (int) $cached;
			} else {
				$idsToFetch[] = $race_id;
			}
		}

		if (empty($idsToFetch)) {
			return $counts;
		}

		$rdb = $this->get_rdb();
		$placeholders = implode(',', array_fill(0, count($idsToFetch), '%d'));
		$sql = $rdb->prepare(
			"SELECT id, results FROM `wp_ije_race_results` WHERE id IN ({$placeholders})",
			$idsToFetch
		);

		$rows = $rdb->get_results($sql, OBJECT);

		if ($rows) {
			foreach ($rows as $row) {
				$count = count($this->parse_csv_rows((string) $row->results));
				$counts[(int) $row->id] = $count;
				set_transient('ije_csv_count_' . (int) $row->id, $count, 12 * HOUR_IN_SECONDS);
			}
		}

		return $counts;
	}

	/**
	 * Parses a CSV blob into an array of associative rows (header row as
	 * keys). Shared by the REST controller (to build the /results response)
	 * and get_csv_row_counts() above, so the count shown in the meetings
	 * list is always guaranteed to match what /results actually returns.
	 */
	public function parse_csv_rows($csv)
	{
		$csv = preg_replace('/^\xEF\xBB\xBF/', '', (string) $csv);

		$handle = fopen('php://memory', 'r+');
		fwrite($handle, $csv);
		rewind($handle);

		$header = null;
		$jsonArray = array();

		while (($row = fgetcsv($handle)) !== false) {
			// Skip rows that are entirely blank — either a single
			// empty-string column (a genuinely blank CSV line) or a
			// row with the correct column count where every field is
			// empty/whitespace (e.g. a fully-blank data row: ",,,,,,,").
			$isBlankRow = true;
			foreach ($row as $cell) {
				if (trim((string) $cell) !== '') {
					$isBlankRow = false;
					break;
				}
			}
			if ($isBlankRow) {
				continue;
			}

			if ($header === null) {
				$header = $row;
				continue;
			}

			if (count($row) !== count($header)) {
				continue;
			}

			$jsonArray[] = array_combine($header, $row);
		}

		fclose($handle);

		return $jsonArray;
	}

	public function get_events()
	{
		$sql = "SELECT id, name, info FROM `wp_ije_events` ORDER BY name ASC";

		return $this->get_results($sql, 'get_events');
	}

	private function get_results($sql, $method_name)
	{
		$rdb = $this->get_rdb();
		$results = $rdb->get_results($sql, OBJECT);

		if ($rdb->num_rows == 0)
			return null;

		if ($results === false) {
			return new \WP_Error(
				'ipswich_events_results_api_' . $method_name,
				'Unknown error in reading results from the database',
				array('status' => 500)
			);
		}

		return $results;
	}
}
