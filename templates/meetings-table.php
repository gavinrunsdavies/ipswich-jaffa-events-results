<?php
if (!defined('ABSPATH')) {
    die('restricted access');
}

/**
 * Expects the following variables to already be set by the includer:
 * @var array  $meetings     Rows from Ipswich_Events_Results_Data_Access::get_meetings()
 * @var int    $eventId
 * @var string $apiBase      untrailingslashit(home_url()) — for PDF links
 * @var string $resultsPage  Base URL (with ipswich_event_results/eventId/title already set) for CSV links
 * @var string $tableId      Unique id for this table instance
 */
?>
<div class="ipswich-event-results">
    <table id="<?php echo esc_attr($tableId); ?>" class="widefat striped display" style="width:100%">
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
                            <?php if (empty($result['hasResults'])): ?>
                                <span class="ipswich-no-results" style="color:#767676;"><?php echo esc_html($result['name']); ?> — No results available</span><br />
                            <?php else: ?>
                                <?php
                                if ($result['type'] === 'pdf') {
                                    $href = $apiBase . '/events/' . (int) $eventId . '/meetings/' . (int) $meeting['meetingId'] . '/races/' . (int) $result['id'] . '/results/pdf';
                                } else {
                                    // resultsPage already contains ipswich_event_results and eventId
                                    $href = $resultsPage . '&meetingId=' . (int) $meeting['meetingId'] . '&raceId=' . (int) $result['id'];
                                }
                                $label = strtoupper($result['type']);
                                ?>
                                <a href="<?php echo esc_url($href); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($label); ?>: <?php echo esc_html($result['name']); ?></a><br />
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
    jQuery(function($) {
        $('#<?php echo esc_js($tableId); ?>').DataTable({
            order: [
                [1, 'desc']
            ],
            columnDefs: [{
                targets: 3,
                orderable: false
            }]
        });
    });
</script>