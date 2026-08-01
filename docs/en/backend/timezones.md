# Timezones

How dates and times are stored, parsed and returned. The rules are short; the bugs from
ignoring them are not (see #2758 and its children for the family of off-by-an-hour issues).

## The three rules

### 1. The database stores German wall-clock time

All `datetime` columns hold Europe/Berlin wall-clock time as naive strings - MySQL
`datetime` carries no timezone marker. `date_default_timezone_set('Europe/Berlin')` in
`config.inc.php` and `Database::date()`/`Database::now()` enforce this on the write side.

This is the current convention, not necessarily the final one: whether storage moves to
UTC is an open decision tracked in #2758. Until that is decided, treat every datetime
string from the database as Berlin wall-clock time.

### 2. The API returns UTC with a `Z` suffix

Every datetime in a JSON API response is serialized as UTC with second precision and a
`Z` suffix (`2026-01-15T11:00:00Z`), no matter which timezone the PHP object carried.
`Foodsharing\Lib\UtcDateTimeHandler` does this centrally, so do not format datetimes to
strings in transactions or gateways for API responses - return `DateTime`/`Carbon`
objects and let the serializer handle them.

Two exceptions:
- Properties with an explicit serializer format, e.g. `#[Type("DateTime<'Y-m-d'>")]` for
  date-only fields, keep that format and the object's own timezone. Converting a Berlin
  midnight to UTC would shift date-only values to the previous day.
- A few legacy queries preformat with `CONVERT_TZ(..., 'Europe/Berlin', 'UTC')` in SQL.
  Their output matches the canonical format, but do not copy the pattern into new code -
  the remaining call sites are being replaced with plain columns and object returns.

In API tests, build expected values with `ApiTester::utcDateTime()` and check formats
with `ApiTester::assertUtcDateTimeFormat()`.

### 3. Parsing always names the timezone

Converting between database strings and datetime objects is gateway work - controllers
and transactions should only ever see objects. In the gateways, use the two central
helpers: `Database::date()` turns an object into a database string, and
`Database::parseDate()` turns a database string into a `Carbon` with Europe/Berlin
already pinned (rule 1). Avoid spelling out `DateTime::createFromFormat` with a format
and timezone by hand.

Unix timestamps have no helper; pass the timezone explicitly:

```php
// unix timestamps: NEVER omit the timezone
Carbon::createFromTimestamp($row['date_ts'], new DateTimeZone('Europe/Berlin'));
```

`Carbon::createFromTimestamp($ts)` without a timezone resolves to **UTC**, not to PHP's
default timezone. The instant stays correct, but any wall-clock formatting of that object
is off by the German UTC offset - this exact mistake stored wrong `date_reference` values
in the store log (#2760).

## Testing across DST boundaries

Tests that compute dates relative to `now()` never cross the summer/winter time switch,
which is where timezone bugs hide. When testing time handling, use a fixed date in the
other DST period (e.g. a mid-January pickup while suites usually run in summer) - see
`PickupApiCest::pickupTimesStayConsistentAcrossDstChanges`.

## Where this is going

Displaying times in the timezone of the region/store they belong to (instead of assuming
Berlin everywhere) is tracked in #2758: regions carry an inheritable `timezone` field
(#2761, `RegionTimezoneResolver`), and the output paths switch to it in #2762.

Longer term, the `Database` class could accept and return datetime objects itself, the
way !4814 added enum support - then gateways would not need to care about the string
format at all unless they want a special one.
