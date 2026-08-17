import { test, expect } from "@playwright/test";

// Function-level coverage of the date formatter (#2762, #775): event-bound
// times render in the timezone of the place they belong to via an explicit
// timeZone option, history timestamps default to the viewer's timezone.
// The formatter is pure browser-independent code, so it is exercised directly
// here (the team is moving client tests from mocha to Playwright); the UI
// integration is covered by timezones.spec.ts.
//
// The formatter reads Intl's default timezone, so the worker is pinned to UTC
// (the reported bug scenario) before the module loads. The 'Z' instants below
// are absolute and the 24h format keeps the assertions locale-independent.
import "../helpers/client-module-shim";
import dateFormatter, {
  parseWallClock,
  DEFAULT_TIME_ZONE,
} from "../../../client/src/helper/date-formatter";

const hourIn = (date: Date, timeZone?: string) =>
  dateFormatter.format(
    date,
    timeZone
      ? { timeZone, hour: "2-digit", hour12: false }
      : { hour: "2-digit", hour12: false },
  );

test.describe("date-formatter timezones", () => {
  test("renders history timestamps in the viewer timezone by default", () => {
    // the worker is UTC, so 10:00 UTC stays 10:00 without an explicit timezone
    expect(hourIn(new Date("2026-07-01T10:00:00Z"))).toBe("10");
    expect(DEFAULT_TIME_ZONE).toBe("Europe/Berlin");
  });

  test("renders event times in the given timezone, DST-aware", () => {
    // 10:00 UTC is 12:00 in Berlin in summer (CEST) and 11:00 in winter (CET).
    expect(hourIn(new Date("2026-07-01T10:00:00Z"), "Europe/Berlin")).toBe(
      "12",
    );
    expect(hourIn(new Date("2026-01-01T10:00:00Z"), "Europe/Berlin")).toBe(
      "11",
    );
  });

  test("renders in the given region timezone instead of the default", () => {
    // 10:00 UTC in summer is 13:00 in Riga (EEST, +3).
    const d = new Date("2026-07-01T10:00:00Z");
    expect(hourIn(d, "Europe/Riga")).toBe("13");
    // and the user-facing functions plumb the option through
    expect(dateFormatter.dateTime(d, { timeZone: "Europe/Riga" })).not.toBe(
      dateFormatter.dateTime(d, { timeZone: "Europe/Berlin" }),
    );
    expect(dateFormatter.time(d, { timeZone: "Europe/Riga" })).not.toBe(
      dateFormatter.time(d, { timeZone: "Europe/Berlin" }),
    );
  });

  test("uses the region day boundary for same-day checks when given a timezone", () => {
    // 23:30 UTC is already the next day in Berlin (01:30).
    const lateUtc = new Date("2026-07-01T23:30:00Z");
    const berlinNextDay = new Date("2026-07-02T08:00:00Z");
    expect(
      dateFormatter.isSame(lateUtc, berlinNextDay, {
        timeZone: "Europe/Berlin",
      }),
    ).toBe(true);
    // without a timezone the viewer's day boundary applies (worker is UTC)
    expect(dateFormatter.isSame(lateUtc, berlinNextDay)).toBe(false);
    // 21:30 UTC is 00:30 in Riga but still 23:30 in Berlin.
    const rigaMidnight = new Date("2026-07-01T21:30:00Z");
    const nextDayNoon = new Date("2026-07-02T09:00:00Z");
    expect(
      dateFormatter.isSame(rigaMidnight, nextDayNoon, {
        timeZone: "Europe/Riga",
      }),
    ).toBe(true);
    expect(
      dateFormatter.isSame(rigaMidnight, nextDayNoon, {
        timeZone: "Europe/Berlin",
      }),
    ).toBe(false);
  });

  test("parses naive wall-clock strings as the timezone they belong to", () => {
    // 12:00 German summer wall clock is the instant 10:00 UTC.
    expect(parseWallClock("2026-07-15 12:00:00").toISOString()).toBe(
      "2026-07-15T10:00:00.000Z",
    );
    // winter: +1
    expect(parseWallClock("2026-01-15 12:00:00").toISOString()).toBe(
      "2026-01-15T11:00:00.000Z",
    );
    // other region timezones work too (Riga summer is +3)
    expect(
      parseWallClock("2026-07-15 12:00:00", "Europe/Riga").toISOString(),
    ).toBe("2026-07-15T09:00:00.000Z");
  });

  test("returns an invalid date for input it cannot parse", () => {
    // Intl throws on NaN, so an unusable value must not reach it.
    for (const input of ["", "not a date", null, undefined]) {
      expect(Number.isNaN(parseWallClock(input as string).getTime())).toBe(
        true,
      );
    }
  });

  test("offers the viewer time only when it differs", () => {
    const d = new Date("2026-07-15T10:00:00Z");
    // worker timezone is UTC; German time differs, so the viewer gets a hint
    expect(
      dateFormatter.viewerTime(d, { timeZone: "Europe/Berlin" }),
    ).not.toBeNull();
    // when the display timezone matches the viewer's, there is nothing to hint
    expect(dateFormatter.viewerTime(d, { timeZone: "UTC" })).toBeNull();
    // equal wall clocks count as equal, even for different zone names
    expect(dateFormatter.viewerTime(d, { timeZone: "Etc/UTC" })).toBeNull();
  });
});
