import assert from 'node:assert/strict';
import { upcomingEvents, eventDayStart } from '../src/lib/event-dates.js';

const future = { slug: 'karachi-event', title: 'Karachi', date: '05-December-2026' };
const past = { slug: 'lahore-event', title: 'Lahore', date: '04-JULY-2026' };
const undated = { slug: 'undated-event', title: 'Undated', date: 'TBA' };
assert.deepEqual(upcomingEvents([future, past, undated]), [future, past, undated], 'Every supplied Upcoming event must remain, regardless of date');
assert.deepEqual(upcomingEvents([null, { slug: '../bad' }, {}, future]), [future]);
assert.deepEqual(upcomingEvents([]), []);
console.log('Dashboard-controlled popup event checks passed.');
assert.equal(eventDayStart('05-December-2026'), Date.parse('2026-12-05T00:00:00+05:00'));
assert.equal(eventDayStart('2026-12-05'), eventDayStart('05-December-2026'));
assert.equal(eventDayStart('12-April -2025'), Date.parse('2025-04-12T00:00:00+05:00'));
for (const date of ['TBA', '', '31-February-2026', '2026-13-01', '05-Nonsense-2026']) assert.equal(eventDayStart(date), null);
