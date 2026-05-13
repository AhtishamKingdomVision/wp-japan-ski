<?php

$locations = $section['location'] ?? [];
$main_lat = get_field('accomodation_details_acc_latitude') ?? 42.858377;
$main_lng = get_field('accomodation_details_acc_longitude') ?? 140.705364;
$main_title = get_the_title() ?? '';
$main_address = get_field('accomodation_details_address') ?? '4-chōme-3-1-7 Niseko Hirafu 1 Jō, Kutchan, Hokkaido, Japan';

echo '<section class="full-section nearby_location" '.BackgroundFromSection($section).'>';
echo '<div class="container">';
echo TitleFromSection($section);

if (!empty($locations)) {

    echo '<div class="nearby-wrapper">';

    /* =========================
       MAP AREA
    ========================= */
    echo '<div class="nearby-map-area">';
    echo '<div id="nearby-map" class="nearby-map"></div>';

    echo '
        <div id="main-location-box" class="main-location-box">
            <h4>' . esc_html($main_title) . '</h4>
            <p>' . esc_html($main_address) . '</p>
        </div>
    ';

    echo '</div>';

    /* =========================
       SIDEBAR LIST
    ========================= */
    echo '<div class="nearby-list-box">';
    echo '<h3 class="nearby-title">Closest Landmarks</h3>';
    // echo '<div class="nearby-divider"></div>';
    echo '<ul class="nearby-list">';

    $mapArray = [];

    foreach ($locations as $i => $loc) {

        $title = $loc['title'] ?? '';
        $km = $loc['km'] ?? '';
        $lat = $loc['latitude'] ?? '';
        $lng = $loc['longitude'] ?? '';

        echo '
            <li class="nearby-item" data-index="' . $i . '">
                <span>' . esc_html($title) . '</span>
                <span class="distance">' . esc_html($km) . ' km</span>
            </li>';

        $mapArray[] = [
            'title' => $title,
            'km'    => $km,
            'lat'   => $lat,
            'lng'   => $lng,
        ];
    }

    echo '</ul>';
    echo '</div>'; // list

    echo '</div>'; // wrapper

    /* =========================
       PASS DATA TO JS
    ========================= */
    echo '<script>
        var nearbyData = ' . json_encode($mapArray) . ';
        var mainLocation = {
            lat: ' . floatval($main_lat) . ',
            lng: ' . floatval($main_lng) . ',
            title: "' . esc_js($main_title) . '",
            address: "' . esc_js($main_address) . '"
        };
    </script>';
}

echo '</div>';
echo '</section>';
?>

<!-- =========================
     LEAFLET FILES
========================= -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<!-- =========================
     LEAFLET MAP SCRIPT
========================= -->
<script>
document.addEventListener("DOMContentLoaded", function () {

    if (typeof nearbyData === "undefined" || nearbyData.length === 0) return;

    /* =========================
       INIT MAP
    ========================= */
    const map = L.map('nearby-map').setView(
        [mainLocation.lat, mainLocation.lng],
        15
    );

    // L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    //     attribution: ''
    // }).addTo(map);

    // L.tileLayer('https://stadiamaps.com/{z}/{x}/{y}{r}.png', {
    //     attribution: ''
    // }).addTo(map);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: ''
    }).addTo(map);

    // L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
    //     attribution: ''
    // }).addTo(map);

    /* =========================
       ICONS
    ========================= */
    const greenIcon = L.icon({
        iconUrl: "https://maps.google.com/mapfiles/ms/icons/green-dot.png",
        iconSize: [40, 40]
    });

    const redIcon = L.icon({
        iconUrl: "https://jsnew.japanskiexperience.com/wp-content/themes/kingdomvision/images/icons/placeholder.png",
        iconSize: [32, 32]
    });

    /* =========================
       MAIN MARKER
    ========================= */
    const mainMarker = L.marker(
        [mainLocation.lat, mainLocation.lng],
        { icon: greenIcon }
    ).addTo(map);

    const mainPopup = `
        <div style="padding:10px; font-size:14px;">
            <strong>${mainLocation.title}</strong><br>
            ${mainLocation.address}
        </div>
    `;

    mainMarker.bindPopup(mainPopup).openPopup();

    /* =========================
       NEARBY MARKERS
    ========================= */
    const markers = [];

    nearbyData.forEach((item, index) => {

        const lat = parseFloat(item.lat);
        const lng = parseFloat(item.lng);

        const marker = L.marker([lat, lng], { icon: redIcon })
            .addTo(map)
            .bindPopup(`
                <div style="font-size:14px;">
                    <strong>${item.title}</strong><br>
                    ${item.km} km away
                </div>
            `);

        markers.push(marker);

        marker.on("click", function () {
            marker.openPopup();
            map.setView([lat, lng], 16);
            highlightItem(index);
        });
    });

    /* =========================
       AUTO FIT ALL MARKERS
    ========================= */
    const group = new L.featureGroup(markers.concat([mainMarker]));
    map.fitBounds(group.getBounds().pad(0.2));

    /* =========================
       SIDEBAR CLICK
    ========================= */
    document.querySelectorAll(".nearby-item").forEach((el) => {
        el.addEventListener("click", function () {
            const i = this.dataset.index;
            markers[i].fire("click");
        });
    });

    /* =========================
       HIGHLIGHT ACTIVE ITEM
    ========================= */
    function highlightItem(index) {
        document.querySelectorAll(".nearby-item").forEach(el => el.classList.remove("active"));

        const current = document.querySelector('.nearby-item[data-index="' + index + '"]');

        if (current) {
            current.classList.add("active");
            current.scrollIntoView({ behavior: "smooth", block: "center" });
        }
    }

});
</script>