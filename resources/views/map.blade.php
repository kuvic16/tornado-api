<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Tornado API - MAP</title>
        <link href="{{ asset('css/app.css') }}" rel="stylesheet">
        <!-- Styles -->
        <style>
              /* Always set the map height explicitly to define the size of the div
               * element that contains the map. */
              #map {
                height: 100%;
              }
              /* Optional: Makes the sample page fill the window. */
              html, body {
                height: 100%;
                margin: 0;
                padding: 0;
              }
            </style>

    </head>
    <body class="text-center">
        <div class="form-group">
          <label for="polygons">Polygons (ex: lat,lon:lat,lon)</label>
          <textarea id="polygons" class="form-control" cols="5" rows="4"></textarea>        
        </div>
        <div id="map"></div>
            <script>

              // This example creates a simple polygon representing the Bermuda Triangle.
              var myLatlng = {lat: 33.665, lng: -99.023};

              function initMap() {
                var map = new google.maps.Map(document.getElementById('map'), {
                  zoom: 8,
                  center: myLatlng,
                  mapTypeId: 'terrain'
                });

                // Create the initial InfoWindow.
                var infoWindow = new google.maps.InfoWindow(
                    {content: '33.665, -99.023', position: myLatlng});
                infoWindow.open(map);

                // Configure the click listener.
                map.addListener('click', function(mapsMouseEvent) {
                  // Close the current InfoWindow.
                  infoWindow.close();

                  // Create a new InfoWindow.
                  infoWindow = new google.maps.InfoWindow({position: mapsMouseEvent.latLng});
                  infoWindow.setContent(mapsMouseEvent.latLng.toString());
                  infoWindow.open(map);
                });

                // Define the LatLng coordinates for the polygon's path.
                var triangleCoords = [
                  {lat: 33.40, lng: -98.85},
                  {lat: 33.40, lng: -98.42},
                  {lat: 33.47, lng: -98.42},
                  {lat: 33.47, lng: -97.98},
                  {lat: 33.44, lng: -97.98},
                  {lat: 33.43, lng: -97.92},
                  {lat: 33.00, lng: -97.92},
                  {lat: 33.01, lng: -98.43},
                  {lat: 32.97, lng: -98.43},
                  {lat: 32.96, lng: -98.85}
                ];

                // Construct the polygon.
                var bermudaTriangle = new google.maps.Polygon({
                  paths: triangleCoords,
                  strokeColor: '#FF0000',
                  strokeOpacity: 0.8,
                  strokeWeight: 2,
                  fillColor: '#FF0000',
                  fillOpacity: 0.35
                });
                bermudaTriangle.setMap(map);
              }
            </script>
            <script async defer
            src="https://maps.googleapis.com/maps/api/js?callback=initMap">
            </script>

    </body>
</html>
