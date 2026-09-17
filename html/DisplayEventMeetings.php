<?php
if (!defined('ABSPATH')) {
    die('restricted access');
}

$eventId = isset($_GET['eventId']) ? intval($_GET['eventId']) : 0;
$eventTitle = isset($_GET['title']) ? $_GET['title'] : 'Event Meetings';
$apiEndpoint = esc_url(home_url('/wp-json/ipswich-events-api/v1/events/' . $eventId . '/meetings'));

// home_url() can come back with a trailing slash depending on how the site
// URL is configured. Normalise it so appending '/events/...' below can't
// ever produce the "..uk//events/.." double-slash you saw in production.
$baseUrl = untrailingslashit(home_url());
?>
<?php wp_head(); ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<style>
    #meetingsTable td,
    #meetingsTable th {
        vertical-align: top;
    }
    .race-result-links a {
        display: inline-block;
        margin: 0 10px 6px 0;
        white-space: nowrap;
    }
</style>

<table id="meetingsTable" class="display" style="width:100%">
    <thead>
        <tr>
            <th>Meeting</th>
            <th>Date</th>
            <th>Venue</th>
            <th>Results</th>
        </tr>
    </thead>
    <tbody></tbody>
</table>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script>
(function ($) {
    const eventId = <?php echo (int) $eventId; ?>;
    const baseUrl = '<?php echo esc_js($baseUrl); ?>';

    // Prebuilt results page URL with event + title; meetingId and raceId
    // are appended per race below (same pattern as the original file).
    const resultsPage = '<?php echo esc_url(add_query_arg(array('ipswich_event_results' => 1, 'title' => $eventTitle, 'eventId' => $eventId), home_url('/'))); ?>';

    function buildResultLink(meetingId, result) {
        const link = document.createElement('a');

        if (result.type === 'pdf') {
            // baseUrl has no trailing slash, so this can never double up.
            link.href = baseUrl + '/events/' + eventId + '/meetings/' + meetingId + '/races/' + result.id + '/results/pdf';
            link.textContent = 'PDF: ' + result.name;
        } else {
            link.href = resultsPage + '&meetingId=' + meetingId + '&raceId=' + result.id;
            link.textContent = 'CSV: ' + result.name;
        }

        return link.outerHTML;
    }

    function buildResultsCell(meetingId, results) {
        if (!results || !results.length) {
            return '';
        }
        return '<div class="race-result-links">' +
            results.map((r) => buildResultLink(meetingId, r)).join('') +
            '</div>';
    }

    $(function () {
        const table = $('#meetingsTable').DataTable({
            columns: [
                { data: 'meetingName' },
                { data: 'meetingDate' },
                { data: 'meetingVenue', defaultContent: '' },
                {
                    data: 'results',
                    orderable: false,
                    render: function (results, type, row) {
                        return type === 'display' ? buildResultsCell(row.meetingId, results) : '';
                    }
                }
            ],
            order: [[1, 'desc']],
            pageLength: 25
        });

        fetch('<?php echo $apiEndpoint; ?>')
            .then((response) => response.json())
            .then((data) => {
                table.rows.add(data).draw();
            })
            .catch((err) => console.error('Failed to load meetings:', err));
    });
})(jQuery);
</script>
<?php wp_footer(); ?>
