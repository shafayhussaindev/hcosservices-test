import React, { useEffect, useState } from 'react';
import { eventDayStart } from '../lib/event-dates.js';

export default function EventCountdown({ date }) {
  const target = eventDayStart(date);
  const [remaining, setRemaining] = useState(null);
  useEffect(() => {
    if (target === null) return;
    const tick = () => setRemaining(Math.max(0, Math.ceil((target - Date.now()) / 1000)));
    tick();
    const timer = window.setInterval(tick, 1000);
    return () => window.clearInterval(timer);
  }, [target]);
  if (remaining === 0) return <p className="event-countdown__caption">The event date has arrived.</p>;
  const seconds = remaining ?? 0;
  const units = [Math.floor(seconds / 86400), Math.floor(seconds / 3600) % 24, Math.floor(seconds / 60) % 60, seconds % 60];
  return <div className="event-countdown" hidden={remaining === null} role="group" aria-label="Countdown to event day in Pakistan time">
    <p className="event-countdown__caption">Until event day · Pakistan time</p>
    <div className="event-countdown__units">{['Days', 'Hours', 'Minutes', 'Seconds'].map((label, i) =>
      <div key={label}><span>{remaining === null ? '—' : String(units[i]).padStart(2, '0')}</span><small>{label}</small></div>
    )}</div>
  </div>;
}
