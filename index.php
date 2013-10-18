<?php
define('SITE_URL', 'http://'.$_SERVER['SERVER_NAME'].(str_replace('?'.$_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI'])));
define('SITE_PATH', dirname(__FILE__).'/');

define('CACHEDATA_PATH', SITE_PATH.'data/');
define('CACHEDATA_CURRENT', CACHEDATA_PATH.'data-current.json');
define('CACHEDATA_FORECAST_TODAY', CACHEDATA_PATH.'data-forecast-today.json');
define('CACHEDATA_FORECAST_EXTENDED', CACHEDATA_PATH.'data-forecast-extended.json');

define('WEATHER_URL_CURRENT', 'http://w1.weather.gov/xml/current_obs/KORL.xml');
define('WEATHER_URL_FORECAST_TODAY', 'http://graphical.weather.gov/xml/sample_products/browser_interface/ndfdBrowserClientByDay.php?lat=28.5898683&lon=-81.1802619&format=12+hourly&numDays=1');
define('WEATHER_URL_FORECAST_EXTENDED', 'http://graphical.weather.gov/xml/sample_products/browser_interface/ndfdBrowserClientByDay.php?lat=28.5898683&lon=-81.1802619&format=24+hourly&numDays=7');

define('WEATHER_URL_TIMEOUT', 8);			// seconds
define('WEATHER_CACHE_DURATION', 60 * 15); 	// seconds (15 minutes)

if (isset($_GET['data']) && $_GET['data'] == 'forecastToday') {
	define('REQUESTED_DATA', 'forecastToday');
}
elseif (isset($_GET['data']) && $_GET['data'] == 'forecastExtended') {
	define('REQUESTED_DATA', 'forecastExtended');
}
else {
	define('REQUESTED_DATA', 'current');
}


/**
 * Returns either previously cached data or newly fetched data
 * depending on the TTL of the cached data and whether or not it exists.
 *
 * @return obj
 **/
function get_weather_data($forecast_type='current') {	
	switch ($forecast_type) {
		case 'forecastToday':
			$cache_data_path = CACHEDATA_FORECAST_TODAY;
			break;
		case 'forecastExtended':
			$cache_data_path = CACHEDATA_FORECAST_EXTENDED;
			break;
		case 'current':
		default:
			$cache_data_path = CACHEDATA_CURRENT;
			break;
	}

	// Check if cached weather data already exists
	$cache_data_contents = @file_get_contents($cache_data_path);

	// The cache time must be within now and the cache duration 
	// to return cached data:
	if ($cache_data_contents !== false) {
		$cache_json = json_decode($cache_data_contents);
		if ( date('YmdHis', strtotime($cache_json->cachedAt)) > date('YmdHis', strtotime('Now -'.WEATHER_CACHE_DURATION.' seconds')) ) {
			return json_encode($cache_json, JSON_FORCE_OBJECT);
		}
		else {
			return make_new_cachedata($forecast_type, $cache_json, $cache_data_path);
		}
	}
	else {
		return make_new_cachedata($forecast_type, null, $cache_data_path);
	}
}


/**
 * Returns an array of weather data and saves the data
 * to a cache file for later use.
 *
 * @return obj
 **/
function make_new_cachedata($forecast_type, $old_cache_data, $cache_data_path) {
	// Create a new template for weather data, and determine the
	// URL of the external weather data to grab for new fetches
	$weather = null;
	$weather_template = array(
		'successfulFetch' => 'yes',
		'provider'		  => '',
		'cachedAt'		  => '',
		'feedUpdatedAt'	  => '',
	);
	switch ($forecast_type) {
		case 'forecastToday':
			$weather_url = WEATHER_URL_FORECAST_TODAY;
			$time_template = array(
				'condition'	=> 'Fair', 		// Fallback
				'temp'		=> '80&#186;',	// Fallback
				'tempN'		=> 80,			// Fallback
				'imgCode'	=> 34,			// Fallback
				'imgSmall'	=> '',
				'imgMedium'	=> '',
				'imgLarge'	=> '',
			);
			$weather = array_merge($weather_template, array(
				'date'	  => '',
				'today'	  => $time_template,
				'tonight' => $time_template,
			));
			break;
		case 'forecastExtended':
			$weather_url = WEATHER_URL_FORECAST_EXTENDED;
			$date_template = array(
				'date'		=> '',
				'condition'	=> 'Fair', 		// Fallback
				'tempMax'	=> '80&#186;',	// Fallback
				'tempMaxN'	=> 80,			// Fallback
				'tempMin'	=> '80&#186;',	// Fallback
				'tempMinN'	=> 80,
				'imgCode'	=> 34,			// Fallback
				'imgSmall'	=> '',
				'imgMedium'	=> '',
				'imgLarge'	=> '',
			);
			$weather = array_merge($weather_template, array(
				'days' => array(
							'day1' => $date_template,
							'day2' => $date_template,
							'day3' => $date_template,
							'day4' => $date_template,
							'day5' => $date_template,
							'day6' => $date_template,
							'day7' => $date_template,
						),
			));
			break;
		case 'current':
		default:
			$weather_url = WEATHER_URL_CURRENT;
			$weather = array_merge($weather_template, array(
				'date'		=> '',
				'condition'	=> 'Fair', 	   // Fallback
				'temp'		=> '80&#186;', // Fallback
				'tempN'		=> 80,		   // Fallback
				'imgCode'	=> 34,		   // Fallback
				'imgSmall'	=> '',
				'imgMedium'	=> '',
				'imgLarge'	=> '',
			));
			break;
	}
	
	// Set a timeout and try to grab the weather feed
	$opts = array('http' => array(
							'method' => 'GET',
							'timeout' => WEATHER_URL_TIMEOUT
							));
	$context = stream_context_create($opts);
	$raw_weather = @file_get_contents($weather_url, false, $context);
	
	if ($raw_weather) {
		$xml = simplexml_load_string($raw_weather);
		switch ($forecast_type) {
			case 'forecastToday':
				// Set date
				$datetime = $xml->data->{'time-layout'}->{'start-valid-time'}[0];
				$weather['date'] = date('Y-m-d', strtotime($datetime));

				// Set today temp
				$temp_max = $xml->data->parameters->temperature[0]->value[0];
				$temp_max = preg_match('/[0-9]+/', $temp_max) ? (int)$temp_max : null;
				$weather['today']['tempN'] = $temp_max;
				$weather['today']['temp'] = $temp_max !== null ? $temp_max.'&#186;' : null;

				// Set tonight temp
				$temp_min = $xml->data->parameters->temperature[1]->value[0];
				$temp_min = preg_match('/[0-9]+/', $temp_min) ? (int)$temp_min : null;
				$weather['tonight']['tempN'] = $temp_min;
				$weather['tonight']['temp'] = $temp_min !== null ? $temp_min.'&#186;' : null;

				// Convert NOAA's weather icon names
				$weather['today']['imgCode'] = !empty($xml->data->parameters->{'conditions-icon'}->{'icon-link'}[0]) ? $xml->data->parameters->{'conditions-icon'}->{'icon-link'}[0] : null;
				$weather['tonight']['imgCode'] = !empty($xml->data->parameters->{'conditions-icon'}->{'icon-link'}[1]) ? $xml->data->parameters->{'conditions-icon'}->{'icon-link'}[1] : null;
				if ($weather['today']['imgCode'] !== null) {
					$weather_img_name = get_noaa_img_code($weather['today']['imgCode']);
					$converted_status = convert_weather_status($weather_img_name);
					$weather['today']['imgCode'] = $converted_status['weather_code'];
					$weather['today']['condition'] = $converted_status['weather_condition'];
				}
				if ($weather['tonight']['imgCode'] !== null) {
					$weather_img_name = get_noaa_img_code($weather['tonight']['imgCode']);
					$converted_status = convert_weather_status($weather_img_name);
					$weather['tonight']['imgCode'] = $converted_status['weather_code'];
					$weather['tonight']['condition'] = $converted_status['weather_condition'];
				}

				// We assume the fetch was a success unless the
				// imgCode for a given day is empty.

				// Catch missing imgCode (cid)
				if (!isset($weather['today']['imgCode']) || !intval($weather['today']['imgCode'])) {
					$weather['today']['imgCode'] = 34;
					$weather['successfulFetch'] = 'no';
				}
				if (!isset($weather['tonight']['imgCode']) || !intval($weather['tonight']['imgCode'])) {
					$weather['tonight']['imgCode'] = 34;
					$weather['successfulFetch'] = 'no';
				}

				// Set image icons
				$weather['today']['imgSmall']  = SITE_URL.'img/weather-small/'.$weather['today']['imgCode'].'.png';
				$weather['today']['imgMedium'] = SITE_URL.'img/weather-medium/'.$weather['today']['imgCode'].'.png';
				$weather['today']['imgLarge']  = SITE_URL.'img/weather-large/WC'.$weather['today']['imgCode'].'.png';
				$weather['tonight']['imgSmall']  = SITE_URL.'img/weather-small/'.$weather['tonight']['imgCode'].'.png';
				$weather['tonight']['imgMedium'] = SITE_URL.'img/weather-medium/'.$weather['tonight']['imgCode'].'.png';
				$weather['tonight']['imgLarge']  = SITE_URL.'img/weather-large/WC'.$weather['tonight']['imgCode'].'.png';

				// Set other data
				$weather['provider'] 	  = (string)$xml->head->source->credit;
				$weather['feedUpdatedAt'] = date('r', strtotime((string)$xml->head->product->{'creation-date'}));

				break;
			case 'forecastExtended':
				// Loop through each day of the week; set values
				for ($i = 0; $i < 7; $i++) {
					$daycount = $i + 1;
					$day = 'day'.$daycount;

					// Set date
					$datetime = $xml->data->{'time-layout'}->{'start-valid-time'}[$i];
					$weather['days'][$day]['date'] = date('Y-m-d', strtotime($datetime));

					// Set max temp
					$temp_max = $xml->data->parameters->temperature[0]->value[$i];
					$temp_max = preg_match('/[0-9]+/', $temp_max) ? (int)$temp_max : null;
					$weather['days'][$day]['tempMaxN'] = $temp_max;
					$weather['days'][$day]['tempMax'] = $temp_max !== null ? $temp_max.'&#186;' : null;

					// Set min temp
					$temp_min = $xml->data->parameters->temperature[1]->value[$i];
					$temp_min = preg_match('/[0-9]+/', $temp_min) ? (int)$temp_min : null;
					$weather['days'][$day]['tempMinN'] = $temp_min;
					$weather['days'][$day]['tempMin'] = $temp_min !== null ? $temp_min.'&#186;' : null;

					// Convert NOAA's weather icon names
					$weather['days'][$day]['imgCode'] = !empty($xml->data->parameters->{'conditions-icon'}->{'icon-link'}[$i]) ? $xml->data->parameters->{'conditions-icon'}->{'icon-link'}[$i] : null;
					if ($weather['days'][$day]['imgCode'] !== null) {
						$weather_img_name = get_noaa_img_code($weather['days'][$day]['imgCode']);
						$converted_status = convert_weather_status($weather_img_name);
						$weather['days'][$day]['imgCode'] = $converted_status['weather_code'];
						$weather['days'][$day]['condition'] = $converted_status['weather_condition'];
					}

					// We assume the fetch was a success unless the
					// imgCode for a given day is empty.

					// Catch missing imgCode (cid)
					if (!isset($weather['days'][$day]['imgCode']) || !intval($weather['days'][$day]['imgCode'])) {
						$weather['days'][$day]['imgCode'] = 34;
						$weather['successfulFetch'] = 'no';
					}

					// Set image icons
					$weather['days'][$day]['imgSmall']  = SITE_URL.'img/weather-small/'.$weather['days'][$day]['imgCode'].'.png';
					$weather['days'][$day]['imgMedium'] = SITE_URL.'img/weather-medium/'.$weather['days'][$day]['imgCode'].'.png';
					$weather['days'][$day]['imgLarge']  = SITE_URL.'img/weather-large/WC'.$weather['days'][$day]['imgCode'].'.png';	
				}

				// Set other data
				$weather['provider'] 	  = (string)$xml->head->source->credit;
				$weather['feedUpdatedAt'] = date('r', strtotime((string)$xml->head->product->{'creation-date'}));

				break;				
			case 'current':
			default:
				// Make sure we get actual usable values before assigning them
				$temp = preg_match('/[0-9]+/', $xml->temp_f) ? (int)number_format((string)$xml->temp_f) : null; // strip decimal place
				$weather['temp'] = $temp;
				$weather['tempN'] = $temp !== null ? $temp.'&#186;' : null;
				$weather['imgCode'] = !empty($xml->icon_url_name) ? (string)$xml->icon_url_name : null;
				
				// Convert NOAA's weather icon names.
				if ($weather['imgCode'] !== null) {
					list($weather_img_name, $ext) = explode('.', $weather['imgCode']);
					$converted_status = convert_weather_status($weather_img_name);
					$weather['imgCode'] = $converted_status['weather_code'];
					$weather['condition'] = $converted_status['weather_condition'];
				}

				// We assume the fetch was a success unless the
				// temp or imgCode are empty.
				
				// Catch missing temp
				if (!isset($weather['temp']) || !$weather['temp']) {
					$weather['temp'] = '80&#186;';
					$weather['tempN'] = 80;
					$weather['successfulFetch'] = 'no';
				}
				
				// Catch missing imgCode (cid)
				if (!isset($weather['imgCode']) || !intval($weather['imgCode'])) {
					$weather['imgCode'] = 34;
					$weather['successfulFetch'] = 'no';
				}
				
				// Set image location URLs, other data
				$weather['imgSmall']  	  = SITE_URL.'img/weather-small/'.$weather['imgCode'].'.png';
				$weather['imgMedium'] 	  = SITE_URL.'img/weather-medium/'.$weather['imgCode'].'.png';
				$weather['imgLarge']  	  = SITE_URL.'img/weather-large/WC'.$weather['imgCode'].'.png';
				$weather['provider']  	  = (string)$xml->credit_URL;
				$weather['feedUpdatedAt'] = (string)$xml->observation_time_rfc822;
				$weather['date'] 	  	  = date('Y-m-d', strtotime($weather['feedUpdatedAt']));

				break;
		}
		
		// Set other data
		$weather['cachedAt'] = date('r');
	}
	else {
		$weather['successfulFetch'] = 'no';
	}

	// Figure out whether or not we need to save newly-grabbed data:
	if ($weather['successfulFetch'] == 'yes' || ($weather['successfulFetch'] == 'no' && $old_cache_data == null)) {
		// The fetch was successful, or the fetch was bad and we have no fallback data.
		// Setup a new json object for caching:
		$json = json_encode($weather, JSON_FORCE_OBJECT);
		// Write the new data to the cache file:
		$filehandle = fopen($cache_data_path, 'w') or die('Cache file open failed.');
		fwrite($filehandle, $json);
		fclose($filehandle);
	}
	else {
		// Keep previously saved data.
		$json = json_encode($old_cache_data, JSON_FORCE_OBJECT);
	}
	
	// Finally, return the newly-grabbed content:
	return $json;
}


/**
 * Given a NOAA-provided URL to a condition icon, this function
 * returns just the condition code.
 *
 * @return string
 **/
function get_noaa_img_code($url) {
	$filename = substr(strrchr($url, '/'), 1); // Split img url at last forward slash
	list($weather_img_name, $ext) = explode('.', $filename); // Remove .jpg/.png/whatever extension from remaining string
	$weather_img_name = preg_replace('/[0-9]+/', '', $weather_img_name); // Strip precipitation chance # from code, if exists

	return $weather_img_name;
}


/**
 * Convert NOAA's weather icon codes to Yahoo/weather.com codes, conditions
 * See http://w1.weather.gov/xml/current_obs/weather.php
 *
 * @return array
 **/
function convert_weather_status($weather_img_name) {
	switch ($weather_img_name) {
		case 'bkn':
			$weather_code = 28; // Mostly Cloudy
			$weather_condition = 'Mostly cloudy';
			break;
		case 'nbkn':
			$weather_code = 27; // Mostly Cloudy (night)
			$weather_condition = 'Mostly cloudy';
			break;
		case 'skc':
			$weather_code = 32; // Fair, Clear
			$weather_condition = 'Fair';
			break;
		case 'nskc':
			$weather_code = 31; // Fair, Clear (night)
			$weather_condition = 'Fair';
			break;
		case 'few':
			$weather_code = 34; // Few Clouds
			$weather_condition = 'Fair';
			break;
		case 'nfew':
			$weather_code = 29; // Few Clouds (night)
			$weather_condition = 'Fair';
			break;
		case 'sct':
			$weather_code = 30; // Partly Cloudy
			$weather_condition = 'Partly cloudy';
			break;
		case 'nsct':
			$weather_code = 27; // Partly Cloudy (night)
			$weather_condition = 'Partly cloudy';
			break;
		case 'ovc':
		case 'novc':
			$weather_code = 26; // Overcast (day, night)
			$weather_condition = 'Overcast';
			break;
		case 'fg':
		case 'nfg':
			$weather_code = 20; // Foggy
			$weather_condition = 'Foggy';
			break;
		case 'smoke':
			$weather_code = 22; // Smoke
			$weather_condition = 'Smoke';
			break;
		case 'fzra':
			$weather_code = 8;  // Freezing drizzle
			$weather_condition = 'Freezing drizzle';
			break;
		case 'ip':
			$weather_code = 18; // Hail
			$weather_condition = 'Hail';
			break;
		case 'mix':
		case 'nmix':
			$weather_code = 7;  // Mixed snow and sleet (day, night)
			$weather_condition = 'Mixed snow/sleet';
			break;	
		case 'raip':
			$weather_code = 35; // Mixed rain and hail
			$weather_condition = 'Mixed rain/hail';
			break;	
		case 'rasn':
		case 'nrasn':
			$weather_code = 6;  // Mixed rain and sleet
			$weather_condition = 'Mixed rain/sleet';
			break;	
		case 'shra':
			$weather_code = 11; // Light Showers
			$weather_condition = 'Showers';
			break;
		case 'tsra':
			$weather_code = 3;  // Severe Thunderstorms
			$weather_condition = 'Severe thunderstorms';
			break;	
		case 'ntrsa':
		case 'hi_ntrsa':
			$weather_code = 47; // Thunderstorms, Thunderstorm in vicinity (night)
			$weather_condition = 'Isolated thundershowers';
			break;
		case 'sn':
			$weather_code = 16; // Snow
			$weather_condition = 'Snow';
			break;	
		case 'nsn':
			$weather_code = 46; // Snow (night)
			$weather_condition = 'Snow';
			break;
		case 'wind':
		case 'nwind':
			$weather_code = 23; // Windy
			$weather_condition = 'Windy';
			break;
		case 'nsvrtsra':
			$weather_code = 0; // Funnel spout/tornado
			$weather_condition = 'Tornado';
			break;
		case 'hi_shwrs':
			$weather_code = 40; // Showers in Vicinity
			$weather_condition = 'Scattered showers';
			break;
		case 'hi_nshwrs':
		case 'nra':
			$weather_code = 45; // Showers, Showers in Vicinity (night)
			$weather_condition = 'Scattered showers';
			break;
		case 'fzrara':
			$weather_code = 10; // Freezing Rain
			$weather_condition = 'Freezing rain';
			break;	
		case 'hi_tsra':
			$weather_code = 38; // Thunderstorm in vicinity (day)
			$weather_condition = 'Scattered thunderstorms';
			break;	
		case 'ra1':
			$weather_code = 9;  // Drizzle
			$weather_condition = 'Drizzle';
			break;
		case 'ra':
			$weather_code = 12; // Showers
			$weather_condition = 'Showers';
			break;	
		case 'dust':
			$weather_code = 19; // Dust
			$weather_condition = 'Dust';
			break;
		case 'mist':
			$weather_code = 21; // Haze
			$weather_condition = 'Haze';
			break;					
		default:
			$weather_code = 34; // Fallback
			$weather_condition = 'Fair';
			break;
	}
	return array('weather_code' => $weather_code, 'weather_condition' => $weather_condition);
}


// Display weather data
header('Content-Type: application/json');
print get_weather_data(REQUESTED_DATA);

?>