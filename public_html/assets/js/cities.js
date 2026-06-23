/* Auto Business — small embedded city gazetteer (interim).
 *
 * Maps Country -> State/Province -> City -> {lat, lon, tz}. This is a compact
 * seed list so the gochar place picker works offline today; Module 5c will
 * replace it with the full city-search service (coords + timezone lookup).
 * lat north +, lon east +, tz hours east of UTC.
 */
window.AB_CITIES = {
  "India": {
    "Punjab":       { "Moga":{lat:30.80,lon:75.17,tz:5.5}, "Ludhiana":{lat:30.90,lon:75.85,tz:5.5}, "Amritsar":{lat:31.63,lon:74.87,tz:5.5}, "Chandigarh":{lat:30.74,lon:76.79,tz:5.5} },
    "Delhi":        { "New Delhi":{lat:28.61,lon:77.21,tz:5.5} },
    "Maharashtra":  { "Mumbai":{lat:19.08,lon:72.88,tz:5.5}, "Pune":{lat:18.52,lon:73.86,tz:5.5}, "Nagpur":{lat:21.15,lon:79.09,tz:5.5} },
    "Karnataka":    { "Bengaluru":{lat:12.97,lon:77.59,tz:5.5}, "Mysuru":{lat:12.30,lon:76.65,tz:5.5} },
    "Tamil Nadu":   { "Chennai":{lat:13.08,lon:80.27,tz:5.5}, "Coimbatore":{lat:11.02,lon:76.96,tz:5.5} },
    "West Bengal":  { "Kolkata":{lat:22.57,lon:88.36,tz:5.5} },
    "Telangana":    { "Hyderabad":{lat:17.39,lon:78.49,tz:5.5} }
  },
  "United States": {
    "California":   { "Los Angeles":{lat:34.05,lon:-118.24,tz:-8}, "San Francisco":{lat:37.77,lon:-122.42,tz:-8} },
    "New York":     { "New York":{lat:40.71,lon:-74.01,tz:-5} },
    "Texas":        { "Houston":{lat:29.76,lon:-95.37,tz:-6}, "Dallas":{lat:32.78,lon:-96.80,tz:-6} },
    "Illinois":     { "Chicago":{lat:41.88,lon:-87.63,tz:-6} }
  },
  "United Kingdom": {
    "England":      { "London":{lat:51.51,lon:-0.13,tz:0}, "Manchester":{lat:53.48,lon:-2.24,tz:0} }
  },
  "Canada": {
    "Ontario":      { "Toronto":{lat:43.65,lon:-79.38,tz:-5} },
    "British Columbia": { "Vancouver":{lat:49.28,lon:-123.12,tz:-8} }
  },
  "Australia": {
    "New South Wales": { "Sydney":{lat:-33.87,lon:151.21,tz:10} },
    "Victoria":     { "Melbourne":{lat:-37.81,lon:144.96,tz:10} }
  },
  "United Arab Emirates": {
    "Dubai":        { "Dubai":{lat:25.20,lon:55.27,tz:4} }
  },
  "Singapore": {
    "Singapore":    { "Singapore":{lat:1.35,lon:103.82,tz:8} }
  }
};
