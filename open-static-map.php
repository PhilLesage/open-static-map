<?php

/**
 * Outputs static map images and markers using Open Street Map
 *
 * Inspired by BigMap 2, authored by Ilya Zverev.
 * https://github.com/Zverik/bigmap2
 *
 * Licensed WTFPL, http://www.wtfpl.net
 * Authors: Philip LeSage
 * Date: January 2020
 *
 */

class staticMap
{

	function __construct($args)
	{
		$defaults = [
			'scale' => 256,
			// 'zoom' => 16,
			'markerWidth' => 20,
			'markerHeight' => 32,
			// 'tiles' => 'mapnik',
			'markers' => [],
			'mapCenter' => null,
			'markerIcon' => '<img src="marker.png" class="marker" style="width:100%;height:auto;">'
		];
		$params = array_merge($defaults, $args);
		$this->setValues($params);
	}

	/**
	 * Does what it says...
	 *
	 * @param $args (array [associative])
	 *
	 * @return void
	*/
	private function setValues($args)
	{
		$this->mapWidth = $args['mapWidth'];
		$this->mapHeight = $args['mapHeight'];
		$this->tileScale = $args['scale'];
		$this->markers = $args['markers'];
		$this->markerWidth = $args['markerWidth'];
		$this->markerHeight = $args['markerHeight'];
		$this->markerIcon = $args['markerIcon'];
		$this->mapCenter = $args['mapCenter'];
		$this->padding = 50;

		if( isset($args['zoom']) ) {
			$this->zoom = min(20, $args['zoom']);
			$this->zoom2 = pow(2, $this->zoom);
		} else {
			$ne_sw = $this->getBounds($args['markers']);
			$zoom = $this->getBoundsZoom($ne_sw);
			$this->zoom = $zoom;
			$this->zoom2 = pow(2, $this->zoom);
		}

		// $this->tileXmin = max(0, $args['xmin']);
		// $this->tileYmin = max(0, $args['ymin']);
		// $this->tileXmax = min($this->zoom2 - 1, $args['xmax']);
		// $this->tileYmax = min($this->zoom2 - 1, $args['ymax']);
		// if( $this->tileXmax < $this->tileXmin ) $this->tileXmax = $this->tileXmin;
		// if( $this->tileYmax < $this->tileYmin ) $this->tileYmax = $this->tileYmin;

		// the minimum number of map tiles to return across and down
		$min_x_tiles = round($args['mapWidth'] / $args['scale']) + 1;
		$min_y_tiles = round($args['mapHeight'] / $args['scale']) + 1;

		if( isset($args['markers']) ) {
			if($this->mapCenter === null) {
				$this->markersLatLonCenter = $this->getMarkersLatLonCenter($args['markers']);
			} else {
				$this->markersLatLonCenter = $this->mapCenter;
			}

			$this->tileXCenter = $this->lon2tile($this->markersLatLonCenter['lon']);
			$this->tileYCenter = $this->lat2tile($this->markersLatLonCenter['lat']);

			$this->tileXmin = floor($this->tileXCenter - $min_x_tiles/2);
			$this->tileYmin = floor($this->tileYCenter - $min_y_tiles/2);
			$this->tileXmax = floor($this->tileXCenter + $min_x_tiles/2);
			$this->tileYmax = floor($this->tileYCenter + $min_y_tiles/2);

			$this->markersXYCenter = $this->latlon2xy($this->markersLatLonCenter);

			$this->mapXYCenter = [
				'x' => $this->mapWidth/2 - $this->markersXYCenter['x'],
				'y' => $this->mapHeight/2 - $this->markersXYCenter['y']
			];

		}

		# $tiles = isset($_REQUEST['tiles']) && preg_match('/^[a-z0-9|-]+$/', $_REQUEST['tiles']) ? $_REQUEST['tiles'] : 'mapnik';
		// $this->tiles = $args['tiles'];
		$this->layers = $this->get_layers();
	}

  /**
  * Get zoom level that fits bounds on map
  *
  * @param $bounds (array) array with most North-Eastern lat/lon points and most South-Western lat/lon points
  *
  * @return int - the zoom level
  */
  public function getBoundsZoom($bounds) {

    $ne_lon_px = $this->lon2x($bounds['ne']['lon'], 0);
    $sw_lon_px = $this->lon2x($bounds['sw']['lon'], 0);

    $ne_lat_px = $this->lat2y($bounds['ne']['lat'], 0);
    $sw_lat_px = $this->lat2y($bounds['sw']['lat'], 0);

    // find max zoom level to fit lon coords
    $z1 = 0;
    while(abs($sw_lon_px - $ne_lon_px) < ($this->mapWidth - $this->padding) && $z1 < 20) {
      $z1++;
      $ne_lon_px = $this->lon2x($bounds['ne']['lon'], $z1);
      $sw_lon_px = $this->lon2x($bounds['sw']['lon'], $z1);
    }

    // find max zoom level to fit lat coords
    $z2 = 0;
    while(abs($sw_lat_px - $ne_lat_px) < ($this->mapHeight - $this->padding) && $z2 < 20) {
      $z2++;
      $ne_lat_px = $this->lat2y($bounds['ne']['lat'], $z2);
      $sw_lat_px = $this->lat2y($bounds['sw']['lat'], $z2);
    }

    // get the minimum zoom level
    $z = min($z1,$z2);
    // our loop will actually take us 1 zoom level too deep while checking, so lets bring us back a level
    $z = $z - 1;
    $z = $z < 0 ? 0 : $z;

    return $z;
  }

  /**
  * Get NE & SW corners of marker group
  *
  * @param $markers (array) array of lat and lon coordinates
  *
  * @return array - the min max lat and lon coordinates of all the markers
  */
  public function getBounds($markers){
    $latMin = 999;
    $latMax = -999;
    $lonMin = 999;
    $lonMax = -999;

    foreach ($markers as $marker) {
      if($marker['lat'] < $latMin) $latMin = $marker['lat'];
      if($marker['lat'] > $latMax) $latMax = $marker['lat'];
      if($marker['lon'] < $lonMin) $lonMin = $marker['lon'];
      if($marker['lon'] > $lonMax) $lonMax = $marker['lon'];
    }

    return [
      'ne'=>['lat'=>$latMin,'lon'=>$lonMin],
      'sw'=>['lat'=>$latMax,'lon'=>$lonMax]
    ];
  }


	/**
	 * If there is multiple markers get the center point out of them all
	 *
	 * @param $markers (array) array of lat and lon coordinates
	 *
	 * @return array - the lat and lon coordinates of the center of all the markers
	*/

  public function getMarkersLatLonCenter($markers){
    $ne_sw = $this->getBounds($markers);
    return [
      'lat' => ($ne_sw['sw']['lat'] - $ne_sw['ne']['lat']) / 2 + $ne_sw['ne']['lat'],
      'lon' => ($ne_sw['sw']['lon'] - $ne_sw['ne']['lon']) / 2 + $ne_sw['ne']['lon']
    ];
  }

	/**
	 * Converts longitude to tile number (x tile)
	 *
	 * @param $lon (int || string)
	 * @param $zoom (int || string)
	 * @param $floor (boolean) : whether or not to round down the tile number (it could be for example 14.9 tiles from the top so it is in the 14th tile)
	 *
	 * @return int
	*/
	public function lon2tile($lon, $zoom = null, $floor = true) {
		// see: https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames#PHP
		if($zoom === null) $zoom = $this->zoom;
		$tileX = (($lon + 180) / 360) * pow(2, $zoom);
		if($floor) {
			$tileX = floor($tileX);
		}
		return $tileX;
	}

	/**
	 * Converts latitude to tile number (y tile)
	 *
	 * @param $lat (int || string)
	 * @param $zoom (int || string)
	 * @param $floor (boolean) : whether or not to round down the tile number (it could be for example 14.9 tiles left, so it is in the 14th tile)
	 *
	 * @return int
	*/
	public function lat2tile($lat, $zoom = null, $floor = true) {
		// see: https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames#PHP
		if($zoom === null) $zoom = $this->zoom;
		$tileY = (1 - log(tan(deg2rad($lat)) + 1 / cos(deg2rad($lat))) / pi()) /2 * pow(2, $zoom);
		if($floor) {
			$tileY = floor($tileY);
		}
		return $tileY;
	}

	/**
	 * Converts lat and lat coords into pixels from the left (most left tile) of the map.
	 *
	 * @param $lon (int || string) : longitude of coordinate on map
	 * @param $zoom (int || string) : zoom level of map
	 *
	 * @return (int) : x pixel position of coordinates relative to the left of the map (most left tile)
	 *
	*/
	public function lon2x($lon, $zoom = null, $tileXmin = null) {
		if($zoom === null) $zoom = $this->zoom;
		/* Get the current tiles without rounding down

			As an example lets say it returns tile 16.25, so the coordinate is 1/4 across the 16th tile...
		*/
		$tileX = $this->lon2tile($lon, $zoom, false);

		/* Get the difference from the most left shown tile

			Continuing our example from above: lets say our left most tile is 13.
			So this would be 16.25 - 13 = 3.25 tiles to the left...
		*/
		$tileXmin = $tileXmin ? $tileXmin : isset($this->tileXmin) ? $this->tileXmin : 0;
		$tileDiffX = $tileX - $tileXmin;

		/* Calculate the pixels

			Again continuing our example from above: lets say the scale of the tiles is 256 (and it almost certainly is)
			That means each tile is 256px x 256px. 3.25tiles * 256px = 832px
			So our coordinate is 832px from the left
		*/
		$x = $tileDiffX * $this->tileScale;

		return $x;
	}

	/**
	 * Converts lat and lon coords into pixels from the top (most top tile) of the map.
	 *
	 * @param $lat (int || string) : latitude of coordinate on map
	 * @param $zoom (int || string) : zoom level of map
	 *
	 * @return (int) : y pixel position of coordinates relative to the top of the map (most top tile)
	 *
	*/
	public function lat2y($lat, $zoom = null, $tileYmin = null) {
		if($zoom === null) $zoom = $this->zoom;
		// Also see function lon2x for explanation of how this works
		$tileY = $this->lat2tile($lat, $zoom, false);
		$tileYmin = $tileYmin ? $tileYmin : isset($this->tileYmin) ? $this->tileYmin : 0;
		$tileDiffY = $tileY - $tileYmin;
		$y = $tileDiffY * $this->tileScale;
		return $y;
	}

	/**
	 * Converts lat and lon coords into pixels from the top and the left (most left or top tiles) of the map respectively.
	 *
	 * @param $latLon (array [associative]) : coordinate on map
	 * @param $zoom (int || string) : zoom level of map
	 *
	 * @return (array [associative]) : x and y pixel position of coordinates relative to left and top of the map (most left or top tiles)
	 *
	*/
	public function latlon2xy($latLon, $zoom = null) {
		if($zoom === null) $zoom = $this->zoom;
		$x = $this->lon2x($latLon['lon'], $zoom);
		$y = $this->lat2y($latLon['lat'], $zoom);
		return ['x' => $x, 'y' => $y];
	}

	/**
	 * Converts tile number to latitude and longitude coordinates
	 *
	 * @param $x (int) tile position to the left
	 * @param $y (int) tile position from the top
	 *
	 * @return (array) coordinates from the tile's corners
	*/
	public function tile2latlon($x, $y) {
		$this->zoom2;
		// see: http://wiki.openstreetmap.org/wiki/Slippy_map_tilenames#Perl
		$relY1 = M_PI * (1 - 2 * $y / $zoom2);
		$relY2 = M_PI * (1 - 2 * ($y + 1) / $zoom2);
		$lat1 = rad2deg(atan(sinh($relY1)));
		$lat2 = rad2deg(atan(sinh($relY2)));
		$lon1 = 360 * ($x / $zoom2 - 0.5);
		$lon2 = $lon1 + 360 / $zoom2;
		return array($lat2, $lon1, $lat1, $lon2);
	}

	/**
	 * Sets markers positions on map by converting the lat lon coordinates to y x position in pixels
	 *
	 * @param $latlon (array) the marker's position
	 * @param $width (int) marker's width
	 * @param $height (int) marker's height
	 *
	 * @return (array) pixel coordinates of marker
	*/
	public function setMarkerPosition($latlon, $width = null, $height = null) {
		$xy = $this->latlon2xy($latlon);

		// if marker does not have a specific size use default
		if( $width === null ) {
			$w = $this->markerWidth;
		} else {
			$w = $height;
		}
		if( $height === null ) {
			$h = $this->markerHeight;
		} else {
			$h = $height;
		}

		// "center" the marker
		$xy['x'] = $xy['x'] - ($w/2);
		$xy['y'] = $xy['y'] - ($h);

		return $xy;
	}

	/**
	 * Get map layers/tiles, add respective attribution
	 * @return (array) map layers
	*/
	public function get_layers() {
		$result = array();
		$result[] = 'http://tile.openstreetmap.org/!z/!x/!y.png';
		$this->attribution = 'Map data &copy; <a href="http://openstreetmap.org">OpenStreetMap</a>';
		$attrib_plain = str_replace('&copy;', '(c)', preg_replace('/<[^>]+>/', '', $this->attribution));
		return $result;
	}

	public function get_marker_html($marker){
		$xy = $this->setMarkerPosition($marker);
		return '<div class="marker-wrap" style="width:'.$this->markerWidth.'px;height:'.$this->markerHeight.'px;position:absolute;top:'.$xy['y'].'px;left:'.$xy['x'].'px">'.$this->markerIcon.'</div>';
	}

	/**
	 * Returns max number of tiles for given zoom
	 * See: https://wiki.openstreetmap.org/wiki/Zoom_levels
	 *
	 * @param $zoom (int) zoom level
	 * @return (array) max number of tiles in zoom level
	*/
	public function max_tiles_in_z_lv($zoom = null){
		$zoom = $zoom ? $zoom : $this->zoom;
		$max_tiles = [
			1, 4, 16, 64, 256, 1024, 4096, 16384, 65536, 262144, 1048576, 4194304, 16777216, 67108864, 268435456, 1073741824, 4294967296, 17179869184, 68719476736, 274877906944, 1099511627776
		];
		return $max_tiles[$zoom];
	}

	public function output($echo = true){
		$output = '';
		$output .= '<div class="staticMapWrap" style="position:relative;overflow:hidden;width:'.$this->mapWidth.'px;height:'.$this->mapHeight.'px;">';
			$markers_html = '';
			foreach ($this->markers as $marker) {
				$markers_html .= $this->get_marker_html($marker);
			}

			$output .= '<div class="staticMapPositioning" style="position:absolute;top:'.$this->mapXYCenter['y'].'px;left:'.$this->mapXYCenter['x'].'px;width:100%;height:100%;">';
				for( $y = $this->tileYmin; $y <= $this->tileYmax; $y++ ) {
					for( $x = $this->tileXmin; $x <= $this->tileXmax; $x++ ) {
						$xp = $this->tileScale * ($x - $this->tileXmin);
						$yp = $this->tileScale * ($y - $this->tileYmin);
						$style = "style=\"position: absolute; left: ${xp}px; top: ${yp}px; width: {$this->tileScale}px; height: {$this->tileScale}px\"";
						for( $l = 0; $l < count($this->layers); $l++ ) {
							// prevent tring to get tile that don't exist
							if($x < 0 || $y < 0 || $y > ($this->max_tiles_in_z_lv()-1) || $x> ($this->max_tiles_in_z_lv()-1)) continue;
							$bg = str_replace('!x', $x, str_replace('!y', $y, str_replace('!z', $this->zoom, $this->layers[$l])));
							if( preg_match('/{([a-z0-9]+)}/', $bg, $m) )
								$bg = str_replace($m[0], substr($m[1], rand(0, strlen($m[1]) - 1), 1), $bg);
							$output .= "<img src=\"$bg\" $style>";
						}
					}
				}
				$output .= $markers_html;
			$output .= '</div>';
			// $output .= "<div style=\"position: absolute; left: 5px; top: ".($this->tileScale*($this->tileYmax-$this->tileYmin+1)-15)."px; font-size: 8px;\">$this->attribution</div>\n";
			$output .= "<div style=\"position: absolute; left: 5px; bottom: ".($this->tileScale*($this->tileYmax-$this->tileYmin+1)-15)."px; font-size: 8px;\">$this->attribution</div>\n";
		$output .= '</div>';

		if($echo) echo $output;
		else return $output;
	}

}

// Example use:
// $args = [
// 	'markers' => [
// 		[
// 			// 'lat' => '49.8539586',
// 			// 'lon' => '-97.2926487'
// 			'lat' => 51.1483793,
// 			'lon' => -100.4912917
// 		],
// 		[
// 			'lat' => 51.1493793,
// 			'lon' => -100.4922917
// 		],
// 		[
// 			'lat' => 51.1453793,
// 			'lon' => -100.4902917
// 		]
// 	],
// 	'mapWidth' => '468',
// 	'mapHeight' => '468'
// 	// 'zoom' => 18
// ];
// $staticMap = new staticMap($args);

// $staticMap->output();
