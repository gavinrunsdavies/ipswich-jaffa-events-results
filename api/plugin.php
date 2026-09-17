<?php
/*
Plugin Name: Ipswich Events Results WP REST API
*/

if ( ! defined( 'ABSPATH' ) ) die( 'restricted access' );

require_once plugin_dir_path( __FILE__ ) .'v1/class-ipswich-events-results-wp-rest-api-controller-v1.php';

// hook into the rest_api_init action so we can start registering routes
$api_controller_V1 = new IpswichEventResultsAPI\V1\Ipswich_Events_Results_WP_REST_API_Controller_V1();
add_action( 'rest_api_init', array( $api_controller_V1, 'rest_api_init') );
add_action( 'plugins_loaded', array( $api_controller_V1, 'plugins_loaded') );

// Add a simple rewrite/query var to serve the DisplayRaceResults.php via WP
add_action('init', function() {
	// flag query var
	add_rewrite_tag('%ipswich_event_results%', '([^&]+)');

	// numeric ID pretty URL: /events/{eventId}/meetings/{meetingId}/races/{raceId}/results
	add_rewrite_tag('%ips_event%', '([0-9]+)');
	add_rewrite_tag('%ips_meeting%', '([0-9]+)');
	add_rewrite_tag('%ips_race%', '([0-9]+)');
	add_rewrite_rule('^events/([0-9]+)/meetings/([0-9]+)/races/([0-9]+)/results/?$', 'index.php?ipswich_event_results=1&ips_event=$matches[1]&ips_meeting=$matches[2]&ips_race=$matches[3]', 'top');

	// legacy/generic route (query var) kept for convenience
	add_rewrite_rule('^ipswich-event-results/?', 'index.php?ipswich_event_results=1', 'top');

	// PDF direct link route: /events/{eventId}/meetings/{meetingId}/races/{raceId}/results/pdf
	add_rewrite_rule('^events/([0-9]+)/meetings/([0-9]+)/races/([0-9]+)/results/pdf/?$', 'index.php?ipswich_event_results_pdf=1&ips_event=$matches[1]&ips_meeting=$matches[2]&ips_race=$matches[3]', 'top');
});

// expose custom query vars
add_filter('query_vars', function($vars) {
	$vars[] = 'ipswich_event_results';
	$vars[] = 'ips_event';
	$vars[] = 'ips_meeting';
	$vars[] = 'ips_race';
	$vars[] = 'ipswich_event_results_pdf';
	return $vars;
});

add_action('template_redirect', function() {
	$flag = get_query_var('ipswich_event_results');
	$pdfFlag = get_query_var('ipswich_event_results_pdf');
	if ($pdfFlag) {
		$event = intval(get_query_var('ips_event'));
		$meeting = intval(get_query_var('ips_meeting'));
		$race = intval(get_query_var('ips_race'));
		if ($event) { $_GET['eventId'] = $event; }
		if ($meeting) { $_GET['meetingId'] = $meeting; }
		if ($race) { $_GET['raceId'] = $race; }

		include plugin_dir_path(__FILE__) . '../html/DisplayRacePdf.php';
		exit;
	}
	if ($flag) {
		// populate expected GET params for the template
		$event = intval(get_query_var('ips_event'));
		$meeting = intval(get_query_var('ips_meeting'));
		$race = intval(get_query_var('ips_race'));
		if ($event) { $_GET['eventId'] = $event; }
		if ($meeting) { $_GET['meetingId'] = $meeting; }
		if ($race) { $_GET['raceId'] = $race; }

		// Ensure WP context is present and include the template which will use WP enqueued assets
		include plugin_dir_path(__FILE__) . '../html/DisplayRaceResults.php';
		exit;
	}
});

?>
?>