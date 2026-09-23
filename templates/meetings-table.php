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

// Small generic inline icons — no external requests, no brand/copyright
// concerns, colour inherits from the surrounding link/text via currentColor.
$icon_csv = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="vertical-align:-2px;"><rect x="1.5" y="1.5" width="13" height="13" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M1.5 6H14.5M1.5 10.5H14.5M6 1.5V14.5M10.5 1.5V14.5" stroke="currentColor" stroke-width="1.1"/></svg>';
$icon_pdf = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="vertical-align:-2px;"><path d="M3 1.5H9.5L13 5V14C13 14.28 12.78 14.5 12.5 14.5H3.5C3.22 14.5 3 14.28 3 14V2C3 1.72 3.22 1.5 3.5 1.5Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M9.5 1.5V5H13" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>';
?>
<style>
    #<?php echo esc_js($tableId); ?> td,
    #<?php echo esc_js($tableId); ?> th {
        vertical-align: middle;
    }
    #<?php echo esc_js($tableId); ?> .ipswich-results-cell {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
    }
    #<?php echo esc_js($tableId); ?> .ipswich-result-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 999px;
        background: #f1f1f1;
        color: #1d2327;
        text-decoration: none;
        font-size: 13px;
        line-height: 1.4;
        white-space: nowrap;
    }
    #<?php echo esc_js($tableId); ?> a.ipswich-result-chip:hover {
        background: #dcdcde;
    }
    #<?php echo esc_js($tableId); ?> .ipswich-result-chip.is-unavailable {
        background: transparent;
        border: 1px dashed #c3c4c7;
        color: #767676;
        font-style: italic;
    }
    #<?php echo esc_js($tableId); ?> .ipswich-result-chip .count {
        opacity: 0.65;
        font-size: 12px;
    }
</style>
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
                                                <div class="ipswich-results-cell">
                                                <?php foreach ($meeting['results'] as $result): ?>
                                                        <?php if (empty($result['hasResults'])): ?>
                                                                <span class="ipswich-result-chip is-unavailable" title="No results available"><?php echo esc_html($result['name']); ?></span>
                                                        <?php else: ?>
                                                                <?php
                                                                if ($result['type'] === 'pdf') {
                                                                        $href = $apiBase . '/events/' . (int) $eventId . '/meetings/' . (int) $meeting['meetingId'] . '/races/' . (int) $result['id'] . '/results/pdf';
                                                                        $icon = $icon_pdf;
                                                                        $typeLabel = 'PDF';
                                                                } else {
                                                                        // resultsPage already contains ipswich_event_results and eventId
                                                                        $href = $resultsPage . '&meetingId=' . (int) $meeting['meetingId'] . '&raceId=' . (int) $result['id'];
                                                                        $icon = $icon_csv;
                                                                        $typeLabel = 'CSV';
                                                                }
                                                                $countLabel = isset($result['resultCount']) && $result['resultCount'] !== null
                                                                        ? (int) $result['resultCount'] . ' result' . ((int) $result['resultCount'] === 1 ? '' : 's')
                                                                        : '';
                                                                ?>
                                                                <a href="<?php echo esc_url($href); ?>" target="_blank" rel="noopener noreferrer" class="ipswich-result-chip" title="<?php echo esc_attr($typeLabel . ': ' . $result['name']); ?>">
                                                                        <?php echo $icon; ?>
                                                                        <span><?php echo esc_html($result['name']); ?></span>
                                                                        <?php if ($countLabel): ?><span class="count">(<?php echo esc_html($countLabel); ?>)</span><?php endif; ?>
                                                                </a>
                                                        <?php endif; ?>
                                                <?php endforeach; ?>
                                                </div>
                                        </td>
                                </tr>
                        <?php endforeach; ?>
                </tbody>
        </table>
</div>
<script>
jQuery(function ($) {
        $('#<?php echo esc_js($tableId); ?>').DataTable({
                order: [[1, 'desc']],
                columnDefs: [
                        { targets: 3, orderable: false }
                ]
        });
});
</script>
