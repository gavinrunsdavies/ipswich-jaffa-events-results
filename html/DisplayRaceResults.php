<?php
if (!defined('ABSPATH')) {
    die('restricted access');
}

$eventId = isset($_GET['eventId']) ? intval($_GET['eventId']) : 0;
$meetingId = isset($_GET['meetingId']) ? intval($_GET['meetingId']) : 0;
$raceId = isset($_GET['raceId']) ? intval($_GET['raceId']) : 0;
$title = isset($_GET['title']) ? $_GET['title'] : 'Race Results';
$apiEndpoint = esc_url(home_url('/wp-json/ipswich-events-api/v1/events/' . $eventId . '/meetings/' . $meetingId . '/races/' . $raceId . '/results'));

// Give the theme's own <title> a sensible value for this page, since
// get_header() below renders the theme's normal document head.
add_filter('pre_get_document_title', function () use ($title) {
    return $title . ' — Ipswich JAFFA Running Club';
});

get_header();
?>

<div class="ipswich-race-results-page" style="max-width:100%; padding:20px; box-sizing:border-box;">
    <h1><?php echo esc_html($title); ?></h1>

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