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

register_activation_hook(__FILE__, function() {
	// flush rewrite rules to register plugin routes
	flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function() {
	flush_rewrite_rules();
});

class Program
{
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
	{
		wp_register_style('ipswich-datatables-css', 'https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css', array(), '1.13.7');
		wp_register_script('ipswich-datatables-js', 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js', array('jquery'), '1.13.7', true);
		wp_register_script('ipswich-ag-grid', 'https://cdn.jsdelivr.net/npm/ag-grid-community@32.3.3/dist/ag-grid-community.min.js', array(), '32.3.3', true);
	}

		add_action('wp_print_scripts', array($this, 'scripts'));
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

		$resultsPage = esc_url(add_query_arg(array('ipswich_event_results' => 1), home_url('/')));
        
		// Enqueue assets only for pages that render the shortcode
		wp_enqueue_style('ipswich-datatables-css');
		wp_enqueue_script('ipswich-datatables-js');
		wp_enqueue_script('ipswich-ag-grid');
		$apiBase = esc_url(home_url('/wp-json/ipswich-events-api/v1'));

		ob_start();
		?>
		<div class="ipswich-event-results">
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Meeting</th>
						<th>Date</th>
						<th>Venue</th>
						<th>Results</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($meetings as $meeting): ?>
						<tr>
							<td><?php echo esc_html($meeting['meetingName']); ?></td>
							<td><?php echo esc_html($meeting['meetingDate']); ?></td>
							<td><?php echo esc_html($meeting['meetingVenue']); ?></td>
							<td>
								<?php foreach ($meeting['results'] as $result): ?>
									<?php
									$href = ($result['type'] === 'pdf')
										? $apiBase . '/events/' . (int) $eventId . '/meetings/' . (int) $meeting['meetingId'] . '/races/' . (int) $result['id'] . '/results/pdf'
										: $resultsPage . '?eventId=' . (int) $eventId . '&meetingId=' . (int) $meeting['meetingId'] . '&raceId=' . (int) $result['id'];
									$label = strtoupper($result['type']);
									?>
									<a href="<?php echo esc_url($href); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($label); ?>: <?php echo esc_html($result['name']); ?></a><br />
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	public function scripts()
	{
		// Enqueue DataTables and any plugin assets via WordPress
		wp_enqueue_script('jquery');
		wp_enqueue_style('ipswich-datatables-css', 'https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css', array(), '1.13.7');
		wp_enqueue_script('ipswich-datatables-js', 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js', array('jquery'), '1.13.7', true);
		// ag-grid (optional)
		wp_enqueue_script('ipswich-ag-grid', 'https://cdn.jsdelivr.net/npm/ag-grid-community@32.3.3/dist/ag-grid-community.min.js', array(), '32.3.3', true);
	}
}
