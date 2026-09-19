<?php

namespace IpswichEventResultsAPI\V1;

require_once plugin_dir_path(__FILE__) . 'class-ipswich-events-results-data-access.php';

class Ipswich_Events_Results_WP_REST_API_Controller_V1
{
	private $data_access;

	public function __construct()
	{
		// Do not create DB connection at construction; lazy-init on demand
		$this->data_access = null;
	}

	private function get_data_access()
	{
		if ($this->data_access instanceof Ipswich_Events_Results_Data_Access) {
			return $this->data_access;
		}

		$this->data_access = new Ipswich_Events_Results_Data_Access();
		return $this->data_access;
	}

	public function rest_api_init()
	{

		$namespace = 'ipswich-events-api/v1'; // base endpoint for our custom API

		$this->register_routes_results($namespace);
	}

	public function plugins_loaded()
	{

		// enqueue WP_API_Settings script
		add_action('wp_print_scripts', function () {
			wp_enqueue_script('wp-api');
		});
	}

	private function register_routes_results($namespace)
	{
		register_rest_route($namespace, '/events/(?P<eventId>[\d]+)/meetings/(?P<meetingId>[\d]+)/races/(?P<raceId>[\d]+)/results', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'get_race_results'),
			'args'                => array(
				'raceId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'is_valid_id')
				),
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'is_valid_id')
				)
				,'meetingId' => array(
					'required' => true,
					'validate_callback' => array($this, 'is_valid_id')
				)
			)
		));




		register_rest_route($namespace, '/events/(?P<eventId>[\d]+)/meetings', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'get_meetings'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'is_valid_id')
				)
			)
		));

		register_rest_route($namespace, '/events/(?P<eventId>[\d]+)/meetings/(?P<meetingId>[\d]+)/races', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'get_races'),
			'args'                => array(
				'eventId'           => array(
					'required'          => true,
					'validate_callback' => array($this, 'is_valid_id')
				),
				'meetingId'         => array(
					'required'          => true,
					'validate_callback' => array($this, 'is_valid_id')
				)
			)
		));

		register_rest_route($namespace, '/events', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array($this, 'get_events')
		));
	}

	public function get_race_results(\WP_REST_Request $request)
	{
		$dataAccess = $this->get_data_access();
		$response = $dataAccess->get_race_results($request['raceId']);

		// Validate that race belongs to provided meeting and event (if present)
		if (!empty($response) && isset($response[0]->meeting_id)) {
			if (isset($request['meetingId']) && (int) $request['meetingId'] !== (int) $response[0]->meeting_id) {
				return new \WP_Error('ipswich_events_results_api_not_found', 'Race does not belong to the specified meeting.', array('status' => 404));
			}
			if (isset($request['eventId']) && isset($response[0]->event_id) && (int) $request['eventId'] !== (int) $response[0]->event_id) {
				return new \WP_Error('ipswich_events_results_api_not_found', 'Race does not belong to the specified event.', array('status' => 404));
			}
		}

		if (empty($response) || !isset($response[0]->results)) {
			return rest_ensure_response(array());
		}

		if (strtoupper((string) $response[0]->type) !== 'CSV') {
			return rest_ensure_response(array());
		}

		$csv = preg_replace('/^\xEF\xBB\xBF/', '', (string) $response[0]->results);

		// Parse CSV using a memory stream and fgetcsv to handle quoting properly
		$handle = fopen('php://memory', 'r+');
		fwrite($handle, (string) $csv);
		rewind($handle);

		$header = null;
		$jsonArray = array();
		while (($row = fgetcsv($handle)) !== false) {
			// skip empty rows
			if (count($row) === 1 && trim($row[0]) === '') {
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

		if (empty($jsonArray)) {
			return rest_ensure_response(array());
		}

		return rest_ensure_response($jsonArray);
	}

	public function get_race_results_pdf(\WP_REST_Request $request)
	{
		$dataAccess = $this->get_data_access();
		$response = $dataAccess->get_race_results($request['raceId']);

		if (empty($response) || !isset($response[0]->results)) {
			return new \WP_Error('ipswich_events_results_api_missing_data', 'No result found for this race.', array('status' => 404));
		}

		if (strtoupper((string) $response[0]->type) !== 'PDF') {
			return new \WP_Error('ipswich_events_results_api_wrong_type', 'This result is not a PDF.', array('status' => 400));
		}

		$pdf = $response[0]->results;
		if (is_resource($pdf)) {
			$pdf = stream_get_contents($pdf);
		}

		$filename = preg_replace('/[^a-zA-Z0-9_.-]/', '-', (string) $response[0]->name) . '-' . preg_replace('/[^a-zA-Z0-9_.-]/', '-', (string) $response[0]->date) . '.pdf';

		$rest_response = new \WP_REST_Response($pdf, 200);
		$rest_response->set_headers(array(
			'Content-Type' => 'application/pdf',
			'Content-Disposition' => 'attachment; filename="' . $filename . '"',
			'Content-Length' => (string) strlen((string) $pdf)
		));

		return $rest_response;
	}

	public function get_meetings(\WP_REST_Request $request)
	{
		$dataAccess = $this->get_data_access();
		$response = $dataAccess->get_meetings($request['eventId']);

		return rest_ensure_response($response);
	}

	public function get_races(\WP_REST_Request $request)
	{
		$dataAccess = $this->get_data_access();
		$response = $dataAccess->get_races($request['meetingId']);

		return rest_ensure_response($response);
	}

	public function get_events(\WP_REST_Request $request)
	{
		$dataAccess = $this->get_data_access();
		$response = $dataAccess->get_events();

		return rest_ensure_response($response);
	}

	public function is_valid_id($value, $request, $key)
	{
		if ($value < 1) {
			// can return false or a custom \WP_Error
			return new \WP_Error(
				'rest_invalid_param',
				sprintf('%s %d must be greater than 0', $key, $value),
				array('status' => 400)
			);
		} else {
			return true;
		}
	}
}
