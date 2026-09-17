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
		$raw_prefix = defined('EVENTS_RESULTS_DB_PREFIX') ? EVENTS_RESULTS_DB_PREFIX : 'wp_';
		// validate prefix: allow only letters, numbers and underscore
		$prefix = preg_match('/^[A-Za-z0-9_]+$/', $raw_prefix) ? $raw_prefix : 'wp_';
		$rdb = $this->get_rdb();
		$race_table = $prefix . 'ije_race_results';
		$meetings_table = $prefix . 'ije_meetings';
		$sql = $rdb->prepare(
			"SELECT r.id, r.results, r.name, r.meeting_id, m.event_id, m.name AS meeting_name, m.date, m.venue, r.type FROM `{$race_table}` r INNER JOIN `{$meetings_table}` m ON m.id = r.meeting_id WHERE r.id=%d",
			$race_id
		);

		return $this->get_results($sql, 'get_race_results');
	}

	public function get_races($meeting_id)
	{
		$raw_prefix = defined('EVENTS_RESULTS_DB_PREFIX') ? EVENTS_RESULTS_DB_PREFIX : 'wp_';
		$prefix = preg_match('/^[A-Za-z0-9_]+$/', $raw_prefix) ? $raw_prefix : 'wp_';
		$rdb = $this->get_rdb();
		$race_table = $prefix . 'ije_race_results';
		$sql = $rdb->prepare("SELECT r.id, r.name, r.type FROM `{$race_table}` r WHERE r.meeting_id=%d", $meeting_id);

		return $this->get_results($sql, 'get_races');
	}

	public function get_meetings($event_id)
	{
		$raw_prefix = defined('EVENTS_RESULTS_DB_PREFIX') ? EVENTS_RESULTS_DB_PREFIX : 'wp_';
		$prefix = preg_match('/^[A-Za-z0-9_]+$/', $raw_prefix) ? $raw_prefix : 'wp_';
		$rdb = $this->get_rdb();
		$meetings_table = $prefix . 'ije_meetings';
		$race_table = $prefix . 'ije_race_results';
		$sql = $rdb->prepare(
			"SELECT m.id AS meetingId, m.name AS meetingName, m.date AS meetingDate, m.venue AS meetingVenue, r.id as resultId, r.name as resultName, r.type as resultType
				FROM `{$meetings_table}` m
				LEFT JOIN `{$race_table}` r on r.meeting_id = m.id
				WHERE m.event_id=%d
				ORDER BY m.date ASC, m.name ASC, r.name ASC;",
			$event_id
		);

		$results = $this->get_results($sql, 'get_meetings');

		if ($results == null)
			return null;

		$meetings = [];
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

			if (!empty($row->resultId)) {
				$meetings[$row->meetingId]['results'][] = [
					'id' => $row->resultId,
					'name' => $row->resultName,
					'type' => $row->resultType
				];
			}
		}

		return array_values($meetings);
	}

	public function get_events()
	{
		$raw_prefix = defined('EVENTS_RESULTS_DB_PREFIX') ? EVENTS_RESULTS_DB_PREFIX : 'wp_';
		$prefix = preg_match('/^[A-Za-z0-9_]+$/', $raw_prefix) ? $raw_prefix : 'wp_';
		$rdb = $this->get_rdb();
		$events_table = $prefix . 'ije_events';
		$sql = "SELECT id, name, info FROM `{$events_table}` ORDER BY name ASC";

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
