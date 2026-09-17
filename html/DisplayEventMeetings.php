<?php
if (!defined('ABSPATH')) {
    die('restricted access');
}

$eventId = isset($_GET['eventId']) ? intval($_GET['eventId']) : 0;
$eventTitle = isset($_GET['title']) ? $_GET['title'] : 'Event Meetings';
$apiEndpoint = esc_url(home_url('/wp-json/ipswich-events-api/v1/events/' . $eventId . '/meetings'));
?>
<?php wp_head(); ?>
<div id="raceListingGrid" style="width: 100%; min-height: 300px;" class="ag-theme-quartz"></div>
<style>
    .clickable {
        cursor: pointer;
        background-color: #f0f8ff;
    }
    .clickable:hover {
        background-color: #add8e6;
    }
</style>
<script>
const eventId = <?php echo (int) $eventId; ?>;
const resultsPage = '<?php echo esc_url(add_query_arg(array('ipswich_event_results' => 1), home_url('/'))); ?>';

class MeetingRacesTooltip {
    init(params) {
        const tooltipData = params.data.results || [];
        const table = document.createElement('table');
        table.style.borderCollapse = 'collapse';
        table.style.width = '100%';
        table.style.backgroundColor = '#fff';
        table.style.border = '1px solid #000';

        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        headerRow.innerHTML = `
            <th style="border: 1px solid #ccc; padding: 8px; text-align: left; background-color: #f4f4f4;">Race</th>
            <th style="border: 1px solid #ccc; padding: 8px; text-align: left; background-color: #f4f4f4;">Results</th>
        `;
        thead.appendChild(headerRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        tooltipData.forEach((result) => {
            const row = document.createElement('tr');
            const raceCell = document.createElement('td');
            raceCell.textContent = result.name;
            raceCell.style.border = '1px solid #ccc';
            raceCell.style.padding = '8px';
            row.appendChild(raceCell);

            const linkCell = document.createElement('td');
            linkCell.style.border = '1px solid #ccc';
            linkCell.style.padding = '8px';

            const link = document.createElement('a');
            if (result.type == 'pdf') {
                link.href = '<?php echo esc_url(home_url()); ?>/wp-json/ipswich-events-api/v1/events/' + eventId + '/meetings/' + params.data.meetingId + '/races/' + result.id + '/results/pdf';
                link.textContent = 'PDF';
            } else {
                link.href = resultsPage + '?title=' + encodeURIComponent('<?php echo esc_js($eventTitle); ?>') + '&eventId=' + eventId + '&meetingId=' + params.data.meetingId + '&raceId=' + result.id;
                link.textContent = 'CSV';
            }
            linkCell.appendChild(link);
            row.appendChild(linkCell);
            tbody.appendChild(row);
        });
        table.appendChild(tbody);

        this.tooltipContainer = document.createElement('div');
        this.tooltipContainer.style.position = 'absolute';
        this.tooltipContainer.style.backgroundColor = '#fff';
        this.tooltipContainer.style.border = '1px solid #ccc';
        this.tooltipContainer.style.padding = '10px';
        this.tooltipContainer.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.1)';
        this.tooltipContainer.style.pointerEvents = 'auto';
        this.tooltipContainer.style.zIndex = '1000';
        this.tooltipContainer.appendChild(table);

        this.eGui = { getGui: () => this.tooltipContainer };
    }

    getGui() {
        return this.tooltipContainer;
    }
}

const eventMeetingGridOptions = {
    rowData: [],
    defaultColDef: { flex: 1 },
    tooltipShowDelay: 200,
    tooltipInteraction: true,
    columnDefs: [
        { field: 'meetingId', hide: true },
        { headerName: 'Meeting', field: 'meetingName', tooltipField: 'meetingName', tooltipComponent: MeetingRacesTooltip },
        { headerName: 'Date', field: 'meetingDate' },
        { headerName: 'Venue', field: 'meetingVenue' }
    ]
};

const eventMeetingGridApi = agGrid.createGrid(document.querySelector('#raceListingGrid'), eventMeetingGridOptions);

fetch('<?php echo $apiEndpoint; ?>')
    .then((response) => response.json())
    .then((data) => eventMeetingGridApi.setGridOption('rowData', data));
</script>
<?php wp_footer(); ?>
