export function upcomingEvents(events) {
  // The server supplies published Upcoming events; dates are display-only.
  return events.filter(event => event && typeof event.slug === 'string' && /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(event.slug));
}

export function eventDayStart(date) {
  const text = String(date || '').trim();
  const iso = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text);
  const named = /^(\d{1,2})\s*-\s*([a-z]+)\s*-\s*(\d{4})$/i.exec(text);
  const months = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
  if (!iso && !named) return null;
  const year = Number(iso ? iso[1] : named[3]);
  const month = iso ? Number(iso[2]) : months.indexOf(named[2].toLowerCase()) + 1;
  const day = Number(iso ? iso[3] : named[1]);
  const utc = new Date(Date.UTC(year, month - 1, day));
  if (utc.getUTCFullYear() !== year || utc.getUTCMonth() !== month - 1 || utc.getUTCDate() !== day) return null;
  // Event details supply a date only; count to midnight in Pakistan, not an invented start time.
  return utc.getTime() - 5 * 3600000;
}
