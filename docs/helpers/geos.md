# Geos Helper

The Geos helper provides location-based functionality and geographical utilities for your application.

## Configuration

Configure in your `.env` file:

```env
GEO_DB_PATH=/path/to/GeoLite2-City.mmdb
GEO_CACHE_TTL=3600
DEFAULT_CURRENCY=USD
```

## Basic Usage

### Location Detection

```php
// Get user location
$location = tiny::geo()->getLocation();
echo $location->country; // "US"
echo $location->city;    // "New York"

// Get location from IP
$location = tiny::geo()->getLocationByIp('8.8.8.8');

// Check specific country
if (tiny::geo()->isCountry('US')) {
    // Show US-specific content
}
```

### Currency Handling

```php
// Format currency based on location
$price = tiny::geo()->formatCurrency(29.99);

// With specific currency
$price = tiny::geo()->formatCurrency(29.99, 'EUR');

// Convert currency
$converted = tiny::geo()->convertCurrency(29.99, 'USD', 'EUR');
```

## Advanced Features

### Region Detection

```php
// Get user's region
$region = tiny::geo()->getRegion();

// Check EU status
if (tiny::geo()->isEU()) {
    // Show GDPR notice
}

// Get timezone
$timezone = tiny::geo()->getTimezone();
```

### Distance Calculations

```php
// Calculate distance between coordinates
$distance = tiny::geo()->distance(
    40.7128, -74.0060,  // New York
    51.5074, -0.1278    // London
);

// Find nearest location
$nearest = tiny::geo()->nearest([
    'lat' => 40.7128,
    'lng' => -74.0060
], $locations);
```

### Address Handling

```php
// Format address
$formatted = tiny::geo()->formatAddress([
    'street' => '123 Main St',
    'city' => 'New York',
    'state' => 'NY',
    'zip' => '10001',
    'country' => 'US'
]);

// Validate address
if (tiny::geo()->validateAddress($address)) {
    // Address is valid
}
```

## Best Practices

1. **Performance**
   - Cache location data
   - Use appropriate TTLs
   - Batch geocoding requests
   - Optimize database queries

2. **Accuracy**
   - Update GeoIP database
   - Handle edge cases
   - Validate coordinates
   - Consider proxy usage

3. **User Experience**
   - Allow manual override
   - Respect user preferences
   - Handle missing data
   - Provide fallbacks

4. **Compliance**
   - Follow privacy laws
   - Get user consent
   - Document data usage
   - Handle data deletion
