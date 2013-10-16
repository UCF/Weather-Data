<?php
define('SITE_URL', 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);
define('SITE_PATH', dirname(__FILE__).'/');

define('CACHEDATA_PATH', SITE_PATH.'data/');
define('CACHEDATA_CURRENT', CACHEDATA_PATH.'data-current.txt');
define('CACHEDATA_FORECAST', '');

define('WEATHER_URL_CURRENT', 'http://w1.weather.gov/xml/current_obs/KORL.xml'); // URL to NOAA weather XML (current)
define('WEATHER_URL_FORECAST', '');

define('WEATHER_URL_TIMEOUT', 8);			// seconds
define('WEATHER_CACHE_DURATION', 60 * 15); 	// seconds (15 minutes)
define('CACHE_DELIMITER', '!@!');


/**
 * Returns either previously cached data or newly fetched data
 * depending on the TTL of the cached data and whether or not it exists.
 *
 * @return array
 **/
function get_weather_data($forecasttype='current') {	
	switch ($forecasttype) {
		case 'forecast':
			$cache_data 	= CACHEDATA_FORECAST;
			$weather_url 	= WEATHER_URL_FORECAST;
			break;
		case 'current':
		default:
			$cache_data 	= CACHEDATA_CURRENT;
			$weather_url 	= WEATHER_URL_CURRENT;
			break;
	}
	
	// Check if cached weather data already exists
	$cache_data_contents = file_get_contents($cache_data) ? file_get_contents($cache_data) : null;
	
	// The cache time must be within now and the cache duration 
	// to return cached data:
	// TODO: refactor to properly handle a weeks' worth of weather data...
	if ($cache_data_contents) {
		switch ($forecasttype) {
			case 'forecast':
				// TODO: setup vars for a weeks' worth of weather data...
				break;
			case 'current':
			default:
				list($c_success, $c_provider, $c_cond, $c_temp, $c_imgcode, $c_img_s, $c_img_m, $c_img_l, $c_cachetime, $c_feedtime) = explode(CACHE_DELIMITER, $cache_data_contents);
				break;
		}		
		if ( date('YmdHis', strtotime($c_cachetime)) > date('YmdHis', strtotime('Now -'.WEATHER_CACHE_DURATION.' seconds')) ) {
			return array(
				'successfulCache'	=> $c_success,
				'provider'			=> $c_provider,
				'condition'			=> $c_cond,
				'temp'				=> $c_temp,
				'imgCode'			=> (int)$c_imgcode,
				'imgSmall'			=> $c_img_s,
				'imgMedium'			=> $c_img_m,
				'imgLarge'			=> $c_img_l,
				'cachedAt'			=> $c_cachetime,
				'feedUpdatedAt'		=> $c_feedtime, 
			);
		}
		else {
			return make_new_cachedata($cache_data, $weather_url);
		}
	} 
	else {
		return make_new_cachedata($cache_data, $weather_url);
	}
}


/**
 * Returns an array of weather data and saves the data
 * to a cache file for later use.
 *
 * @return array
 **/
function make_new_cachedata($cache_data, $weather_url) {
	$weather = array(
		'successfulCache'	=> '',
		'provider'			=> '',
		'condition'			=> 'Fair', 		// Fallback
		'temp'				=> '80&#186;',	// Fallback
		'imgCode'			=> 34,			// Fallback
		'imgSmall'			=> '',
		'imgMedium'			=> '',
		'imgLarge'			=> '',
		'cachedAt'			=> '',
		'feedUpdatedAt'		=> '',
	);
	
	// Set a timeout and grab the weather feed
	$opts = array('http' => array(
							'method' => 'GET',
							'timeout' => WEATHER_URL_TIMEOUT
							));
	$context = stream_context_create($opts);
	$raw_weather = file_get_contents($weather_url, false, $context);
	
	if ($raw_weather) {
		$xml = simplexml_load_string($raw_weather);
		
		// Make sure we get actual usable values before assigning them
		$weather['temp']		= preg_match('/[0-9]+/', $xml->temp_f) ? number_format((string)$xml->temp_f).'&#186;' : null; // strip decimal place
		$weather['imgCode']		= !empty($xml->icon_url_name) ? (string)$xml->icon_url_name : null;
		
		// Convert NOAA's weather icon names to standard weather codes
		// See http://w1.weather.gov/xml/current_obs/weather.php
		// TODO: update $weather['condition'] to use simplified phrases
		list($weather_img_name, $ext) = explode('.', $weather['imgCode']);
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
		}
		$weather['imgCode'] = $weather_code;
		$weather['condition'] = $weather_condition;

		// We assume the fetch was a success unless the
		// condition, temp or imgCode are empty:
		$weather['successfulCache'] = 'yes';
		
		// Catch missing condition
		if (!is_string($weather['condition']) or !$weather['condition']){
			$weather['condition'] = 'Fair';
			$weather['successfulCache'] = 'no';
		}
		
		// Catch missing temp
		if (!isset($weather['temp']) or !$weather['temp']){
			$weather['temp'] = '80&#186;';
			$weather['successfulCache'] = 'no';
		}
		
		// Catch missing imgCode (cid)
		if (!isset($weather['imgCode']) or !intval($weather['imgCode'])){
			$weather['imgCode'] = '34';
			$weather['successfulCache'] = 'no';
		}
		
		// Set other data
		$weather['provider'] 		= (string)$xml->credit_URL;
		$weather['imgSmall'] 		= SITE_URL.'img/weather-small/'.$weather['imgCode'].'.png'; 
		$weather['imgMedium'] 		= SITE_URL.'img/weather-medium/'.$weather['imgCode'].'.png'; 
		$weather['imgLarge'] 		= SITE_URL.'img/weather-large/WC'.$weather['imgCode'].'.png';  
		$weather['cachedAt'] 		= date('r');
		$weather['feedUpdatedAt'] 	= (string)$xml->observation_time_rfc822;
	}
	
	// Setup a new string for caching:
	$string = '';
	foreach ($weather as $key => $val) {
		$string .= $val.CACHE_DELIMITER;
	}
	$string = substr($string, 0, -strlen(CACHE_DELIMITER));
	
	// Write the new string of data to the cache file:
	// TODO: create file on the fly if it doesn't exist yet
	if (file_exists($cache_data)) {
		$filehandle = fopen($cache_data, 'w') or die('Cache file open failed.');
		fwrite($filehandle, $string);
		fclose($filehandle);
	}
	else {
		$weather['successfulCache'] = 'no';
	}
	
	// Finally, return the newly-grabbed content:
	return $weather;
}



// This output can be updated later to output results based
// on GET params (current, forecast...)
header('Content-Type: application/json');
print json_encode(get_weather_data('current'));

?>