# Weather Data Grabber

A basic PHP script that grabs and outputs weather data in JSON for use on other UCF sites.
Uses a simple caching mechanism that saves previously grabbed results to a text file and
refers to that text file if another request is made within the set cache duration.

The script is currently written to accept and parse NOAA XML data.  Condition codes for images
are strings by default but are converted to the Weather.com/Yahoo condition code standard
(since most of our sites are already using it anyway.)

Links to various icon sets are provided within the feed; `weather-small/` contains icons used
on ucf.edu; `weather-medium/` contains icons used in the GMUCF emails; `weather-large/` contains
icons used on UCF Today.

Note that this is written primarily to handle current status conditions, but could be expanded
relatively easily to handle extended forecasts.


## Returned Values

### successfulCache (string)
Either 'yes' or 'no'; if the requested data was successfully cached, this value is set to 'yes'.
If the value is ever 'no', fallback weather data is saved to the cache anyway, but the script
will check for fresh data from NOAA on the next page request.

### provider (string)
The URL of the weather data provider.

### condition (string)
A weather condition phrase. (See http://w1.weather.gov/xml/current_obs/weather.php)

### temp (string)
Temperature reading. (Includes degree symbol)

### imgCode (int)
A status condition code, based on the status code provided by NOAA.  This code is translated
into a Weather.com/Yahoo numerical condition code.

### imgSmall, imgMedium, imgLarge (string)
Links to relevant weather icons.  `weather-small/` contains icons used on ucf.edu; `weather-medium/` 
contains icons used in the GMUCF emails; `weather-large/` contains icons used on UCF Today.

### cachedAt (string)
The timestamp when this set of weather data was last cached.

### feedUpdatedAt (string)
The timestamp when the feed provider (NOAA) last updated their feed content.


## Notes
* **Sites using this feed should NOT re-request data if successfulCache is set to 'no' to prevent
an excessive number of requests to the NOAA service.**  Sites should cache this data regardless of
the successfulCache value and request data from this feed at a set interval.  Fallback values are
set regardless of the successfulCache value to prevent empty results from being returned.
* Note that the NOAA only refreshes their current condition data once an hour, at 45 minutes past
the hour.  The weather grabber script is set to cache data for 15 minutes.