# Open Static Map

Uses [Open Street Maps](https://www.openstreetmap.org/) to produce static maps, since at this time OSM doesn't have much for static map options.

Partially inspired by BigMap 2 (authored by Ilya Zverev). https://github.com/Zverik/bigmap2

Usage Example:
    
    <?php
    include 'open-static-map.php';
    $args = [
      'markers' => [ // Optional, can add 1 or many markers
        [
          'lat' => 51.1483793,
          'lon' => -100.4912917
        ],
        [
	        'lat' => 51.1453793,
	        'lon' => -100.4902917
	      ]
      ],
      'mapWidth' => '468',
      'mapHeight' => '468',
      // Optional, if no markers are used it go zoom to 0 unless set.
      // If markers are used this will override default auto zoom which which zooms to fit all markers
      // 'zoom' => 18 
      //  Optional, use if no markers are set. If markers are set this will override default which sets center to marker(s).
      // 'mapCenter' => [
      //  'lat'=>51.1484793,
      //  'lon'=>-100.4912917
      // ]
      // Optional, overwrite default marker image.
      // 'markerIcon' => '<img src="marker.png" class="marker" style="width:100%;height:auto;">'
    ];
    $staticMap = new staticMap($args);
    // Outputs HTML of map. Pass false as parameter to prevent return HTML string instead of echo-ing
    $staticMap->output();
    ?>
