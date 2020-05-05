<!DOCTYPE html>
<html>
<head>
  <title>Open Static Maps</title>
  <style>
    body{
      background: #024;
    }
  </style>
</head>
<body>
<?php
include 'open-static-map.php';
// Example use:
$args = [
	'markers' => [
		[
			// 'lat' => '49.8539586',
			// 'lon' => '-97.2926487'
			'lat' => 51.1483793,
			'lon' => -100.4912917
		],
		[
			'lat' => 51.1493793,
			'lon' => -100.4922917
		],
		[
			'lat' => 51.1453793,
			'lon' => -100.4902917
		]
	],
	'mapWidth' => '468',
	'mapHeight' => '468'
	// 'zoom' => 18
];
$staticMap = new staticMap($args);

$staticMap->output();
?>
</body>
</html>
