<?php
if (!defined('ABSPATH')) {
    die('restricted access');
}

$eventId = isset($_GET['eventId']) ? intval($_GET['eventId']) : 0;
$meetingId = isset($_GET['meetingId']) ? intval($_GET['meetingId']) : 0;
$raceId = isset($_GET['raceId']) ? intval($_GET['raceId']) : 0;
$title = isset($_GET['title']) ? $_GET['title'] : 'Race Results';
$apiEndpoint = esc_url(home_url('/wp-json/ipswich-events-api/v1/events/' . $eventId . '/meetings/' . $meetingId . '/races/' . $raceId . '/results'));

// Fetch race/meeting/event metadata server-side so the page can show a real
// title, date and event info, instead of relying only on the generic
// ?title= query param.
require_once plugin_dir_path(__FILE__) . '../api/v1/class-ipswich-events-results-data-access.php';

$eventName = '';
$eventInfo = '';
$raceName = '';
$meetingName = '';
$meetingDate = '';
$meetingVenue = '';

if ($eventId && $meetingId && $raceId) {
    $dataAccess = new \IpswichEventResultsAPI\V1\Ipswich_Events_Results_Data_Access();
    $metaResponse = $dataAccess->get_race_results($raceId);

    if (!empty($metaResponse) && isset($metaResponse[0])) {
        $meta = $metaResponse[0];

        // Only trust this row if it actually belongs to the meeting/event
        // in the URL — same relationship check the REST controller does.
        $belongsToMeeting = !isset($meta->meeting_id) || (int) $meta->meeting_id === $meetingId;
        $belongsToEvent = !isset($meta->event_id) || (int) $meta->event_id === $eventId;

        if ($belongsToMeeting && $belongsToEvent) {
            $eventName = isset($meta->event_name) ? (string) $meta->event_name : '';
            $eventInfo = isset($meta->event_info) ? (string) $meta->event_info : '';
            $raceName = isset($meta->name) ? (string) $meta->name : '';
            $meetingName = isset($meta->meeting_name) ? (string) $meta->meeting_name : '';
            $meetingDate = isset($meta->date) ? (string) $meta->date : '';
            $meetingVenue = isset($meta->venue) ? (string) $meta->venue : '';
        }
    }
}

// Prefer the real race name where we have it; fall back to the query string.
$pageTitle = $raceName !== '' ? $raceName : $title;

add_filter('pre_get_document_title', function () use ($pageTitle) {
    return $pageTitle . ' — Ipswich JAFFA Running Club';
});

get_header();
?>

<div class="ipswich-race-results-page" style="max-width:100%; padding:20px; box-sizing:border-box;">
    <h1><?php echo esc_html($pageTitle); ?></h1>

    <?php if ($meetingName || $meetingDate || $meetingVenue || $eventInfo) : ?>
        <div class="ipswich-race-meta" style="margin-bottom:20px; color:#444;">
            <?php if ($meetingName || $meetingDate || $meetingVenue) : ?>
                <?php
                $formattedDate = '';
                if ($meetingDate) {
                    $timestamp = strtotime($meetingDate);
                    $formattedDate = $timestamp ? date_i18n('j F Y', $timestamp) : $meetingDate;
                }
                $metaParts = array_filter([$meetingName, $formattedDate, $meetingVenue]);
                ?>
                <p style="margin:0 0 4px; color:#555;"><?php echo esc_html(implode(' — ', $metaParts)); ?></p>
            <?php endif; ?>

            <?php if ($eventInfo) : ?>
                <p style="margin:8px 0 0; font-size:14px; color:#666;"><?php echo esc_html($eventInfo); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!$eventId || !$meetingId || !$raceId) : ?>
        <p>Missing event, meeting or race reference — this results page needs all three in the URL.</p>
    <?php else : ?>
        <div style="overflow-x: auto;">
            <table id="raceResultsTable" class="display responsive nowrap" style="width: 100%;"></table>
        </div>

        <style>
            /* A little breathing room on the collapsed-row "+" control and
           details panel the Responsive extension injects on small screens. */
            #raceResultsTable.dtr-inline.collapsed>tbody>tr>td.dtr-control {
                padding-left: 24px;
            }

            #raceResultsTable td,
            #raceResultsTable th {
                font-size: 14px;
            }

            @media (max-width: 600px) {

                #raceResultsTable td,
                #raceResultsTable th {
                    font-size: 13px;
                    padding: 6px 8px;
                }
            }
        </style>

        <script>
            jQuery(function($) {
                const apiEndpoint = '<?php echo $apiEndpoint; ?>';

                function toTitle(field) {
                    return field
                        .replace(/([a-z])([A-Z])/g, '$1 $2')
                        .replace(/[_-]+/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim()
                        .replace(/^./, (char) => char.toUpperCase());
                }

                fetch(apiEndpoint)
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('API returned ' + response.status);
                        }
                        return response.json();
                    })
                    .then((data) => {
                        if (!Array.isArray(data) || data.length === 0) {
                            document.querySelector('.ipswich-race-results-page').insertAdjacentHTML('beforeend', '<p>No results found.</p>');
                            return;
                        }

                        const fields = Object.keys(data[0]);
                        const columns = fields.map((field, index) => ({
                            data: field,
                            title: toTitle(field),
                            // Keep the first couple of columns (typically position/name)
                            // always visible; let later ones collapse first on narrow
                            // screens. Responsive falls back sensibly if there are fewer.
                            responsivePriority: index < 2 ? 1 : index + 1
                        }));

                        $('#raceResultsTable').DataTable({
                            data: data,
                            columns: columns,
                            paging: true,
                            pageLength: 50,
                            searching: true,
                            ordering: true,
                            responsive: true
                        });
                    })
                    .catch((error) => {
                        console.error('Error fetching race results:', error);
                        document.querySelector('.ipswich-race-results-page').insertAdjacentHTML('beforeend', '<p>Unable to load results.</p>');
                    });
            });
        </script>
    <?php endif; ?>
</div>

<?php get_footer(); ?>