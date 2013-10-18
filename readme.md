# Weather Data Grabber

A basic PHP script that grabs and outputs weather data in JSON for use on other UCF sites.
Uses a simple caching mechanism that saves previously grabbed results and
refers to that saved file if another request is made within the set cache duration.
Conditions retrieved are relative to the Orlando area, but can be updated for other projects 
by modifying the `WEATHER_URL_CURRENT` and `WEATHER_URL_FORECAST` constants.

The script is currently written to accept and parse NOAA XML data.  Condition codes for images
are strings by default but are converted to the Weather.com/Yahoo condition code standard
(since most of our sites are already using it anyway.)  These condition codes are also used 
to generate a condition phrase, due to the default NOAA condition phrases being very verbose.

Links to various icon sets are provided within the feed; `img/weather-small/` contains icons used
on ucf.edu; `img/weather-medium/` contains icons used in the GMUCF emails; `img/weather-large/` 
contains icons used on UCF Today.


## Usage
The Orlando area's current conditions feed is returned by default when index.php is requested. 
Use the GET parameter `data` to return different sets of data:

* ?data=current, default: Current Conditions
* ?data=forecastToday: Forecast for today; includes conditions/temperatures for today and tonight
* ?data=forecastExtended: 7 day forecast, divided into days ('days', grouped into 'day1-7')


## Current Conditions & Forecasts Returned Values

### successfulFetch (string--previously 'successfulCache' in v1.0.0)
Either 'yes' or 'no'; if the requested data was successfully fetched from the specified external source,
this value is set to 'yes'. If the value is ever 'no', a basic set of fallback weather data or stale data 
is saved to the cache anyway so that empty JSON is never returned.

### provider (string)
The URL of the weather data provider.

### date (string)
The date for the given set of weather conditions. (Format YYYY-MM-DD)

### condition (string)
A weather condition phrase.  These phrases are based loosely on the Yahoo Weather API code descriptions: 
(http://developer.yahoo.com/weather/#codetable)

### imgCode (int)
A status condition code, based on the status code provided by NOAA.  This code is translated
into a Weather.com/Yahoo numerical condition code.

### imgSmall, imgMedium, imgLarge (string)
Links to relevant weather icons.  `img/weather-small/` contains icons used on ucf.edu; 
`img/weather-medium/` contains icons used in the GMUCF emails; `img/weather-large/` contains icons 
used on UCF Today.

### cachedAt (string)
The timestamp when this set of weather data was last cached.

### feedUpdatedAt (string)
The timestamp when the feed provider (NOAA) last updated their feed content.


## Current Conditions, Today's Forecast Returned Values

### temp (string)
Current temperature reading. (Includes degree symbol)

### tempN (int)
Current temperature reading. (Does not include degree symbol)


## Extended Forecast Returned Values (per 'day')

### tempMax (string)
Maximum predicted temperature for that day. (Includes degree symbol)

### tempMaxN (int)
Maximum predicted temperature for that day. (Does not include degree symbol)

### tempMin (string)
Minimum predicted temperature for that day. (Includes degree symbol.)  Note that a 'day7' tempMin 
is not provided by NOAA.

### tempMinN (int)
Minimum predicted temperature for that day. (Does not include degree symbol.)


## Notes
* Sites using this service are expected to cache results on their end and should cache the returned 
JSON data regardless of the successfulCache value.  Data should only be requested from this script at 
a set interval.
* A minimum set of fallback values are set regardless of the successfulCache value to 
prevent empty results from being returned.  Re-requesting this script will not refresh an unsuccessful 
cache unless it has expired.
* The cache is only refreshed with new data when the old cached data has expired, AND a newly-grabbed 
set of data was grabbed successfully.  This script will continue to display stale data until a 
successful data fetch is performed.  If no stale data is available to fall back to, a basic set of
fallback condition data is cached until the next expiration.
* Note that the NOAA only refreshes their current condition data once an hour, at (roughly) 45 
minutes past the hour.  The weather grabber script is set to cache data for 15 minutes.