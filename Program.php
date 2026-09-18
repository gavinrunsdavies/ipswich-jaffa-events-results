<?php
/*
Plugin Name: Ipswich JAFFA RC Event Results
Plugin URI:
Description: Display results from blobs (files) held in a database.
Version: 0.1.0
Author: Gavin Davies
Author URI: https://github.com/gavinrunsdavies/
*/

namespace IpswichEventResultsAPI;

$go = new Program();

register_activation_hook(__FILE__, function () {
	// flush rewrite rules to register plugin routes
	flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
	flush_rewrite_rules();
});

class Program
{
	private static $instanceCount = 0;

	function __construct()
	{
		add_action('init', array($this, 'registerShortCodes'));
		add_action('init', array($this, 'registerAssets'));

		require_once plugin_dir_path(__FILE__) . 'api/plugin.php';
	}

	public function registerShortCodes()
	{
		add_shortcode('ipswich-jaffa-events-results', array($this, 'processShortCode'));
		add_shortcode('ipswich-jaffa-events-meetings', array($this, 'processShortCode'));
	}

	public function registerAssets()
	{
		wp_register_style('ipswich-datatables-css', 'https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css', array(), '1.13.7');
		wp_register_script('ipswich-datatables-js', 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js', array('jquery'), '1.13.7', true);

		// Registered only, not enqueued — enqueued in render_event_meetings()
		// so pages without the shortcode don't load DataTables for nothing.
	}

	public function processShortCode($attr, $content = "")
	{
		$atts = shortcode_atts(
			array(
				'event-id' => 0
			),
			$attr
		);

		$eventId = intval($atts['event-id']);

		if ($eventId <= 0) {
			return '<p>Please provide an event-id when using the shortcode.</p>';
		}

		return $this->render_event_meetings($eventId);
	}

	private function render_event_meetings($eventId)
	{
		require_once plugin_dir_path(__FILE__) . 'api/v1/class-ipswich-events-results-data-access.php';

		$dataAccess = new \IpswichEventResultsAPI\V1\Ipswich_Events_Results_Data_Access();
		$meetings = $dataAccess->get_meetings($eventId);

		if (empty($meetings)) {
			return '<p>No meetings were found for this event.</p>';
		}

		$resultsPage = esc_url(add_query_arg(array('ipswich_event_results' => 1, 'title' => rawurlencode('Event Meetings'), 'eventId' => $eventId), home_url('/')));

		// Normalised so appending '/events/...' in the template can never
		// double up a slash, regardless of how home_url() is configured.
		$apiBase = untrailingslashit(home_url());

		// Multiple copies of this shortcode can appear on one page, so
		// each table needs a unique id for DataTables to target it alone.
		self::$instanceCount++;
		$tableId = 'ipswich-meetings-table-' . $eventId . '-' . self::$instanceCount;

		wp_enqueue_style('ipswich-datatables-css');
		wp_enqueue_script('ipswich-datatables-js');

		ob_start();
		include plugin_dir_path(__FILE__) . 'templates/meetings-table.php';
		return ob_get_clean();
	}
}
