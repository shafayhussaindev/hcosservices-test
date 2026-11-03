import React, { useEffect, useRef, useState } from 'react';
import { upcomingEvents } from '../lib/event-dates.js';

export default function EventPopup({ events }) {
  const dialog = useRef(null);
  const [slides, setSlides] = useState([]);
  const [index, setIndex] = useState(0);
  const [reducedMotion, setReducedMotion] = useState(false);
  const [focused, setFocused] = useState(false);
  const [hovered, setHovered] = useState(false);
  const [failedImage, setFailedImage] = useState('');
  const event = slides[index];
  const open = slides.length > 0;

  useEffect(() => {
    let list;
    try { list = JSON.parse(events); } catch { return; }
    if (!Array.isArray(list)) return;
    const timer = setTimeout(() => {
      if (typeof dialog.current?.showModal !== 'function' || document.querySelector('dialog[open]')) return;
      const eligible = upcomingEvents(list);
      if (!eligible.length) return;
      setIndex(0);
      setReducedMotion(window.matchMedia('(prefers-reduced-motion: reduce)').matches);
      setSlides(eligible);
    }, 2000);
    return () => clearTimeout(timer);
  }, [events]);

  useEffect(() => {
    if (!open || typeof dialog.current?.showModal !== 'function') return;
    const previousFocus = document.activeElement;
    dialog.current.showModal();
    document.body.classList.add('event-popup-open');
    return () => {
      document.body.classList.remove('event-popup-open');
      if (previousFocus?.isConnected) previousFocus.focus();
    };
  }, [open]);

  useEffect(() => {
    if (slides.length < 2 || reducedMotion || hovered || focused) return;
    const timer = window.setInterval(() => {
      if (!document.hidden) setIndex(current => (current + 1) % slides.length);
    }, 4000);
    return () => window.clearInterval(timer);
  }, [slides.length, reducedMotion, hovered, focused]);

  const go = next => {
    setIndex((next + slides.length) % slides.length);
  };

  const image = event?.image || '';
  const validImage = /^(assets|uploads)\/[a-z0-9_./-]+$/i.test(image) && !image.includes('..');
  const url = event ? '/events/' + event.slug : undefined;
  return <dialog ref={dialog} className="event-popup" id="eventPopup" aria-labelledby="eventPopupTitle"
    onMouseEnter={() => setHovered(true)} onMouseLeave={() => setHovered(false)}
    onClose={() => { setSlides([]); setHovered(false); setFocused(false); }}>
    <button className="event-popup__close" type="button" aria-label="Close event announcement" autoFocus onClick={() => dialog.current.close()}>&times;</button>
    {event && <>
      <div className="event-popup__banner">
        {validImage && failedImage !== image && <a key={event.slug} className="event-popup__image-link event-popup__slide" href={url} aria-label="View event details"
          onFocus={() => setFocused(true)} onBlur={() => setFocused(false)}>
          <img className="event-popup__image" src={'/' + image} alt={event.alt || event.title} onError={() => setFailedImage(image)} />
        </a>}
      </div>
      <div className="event-popup__body">
        <div key={event.slug} className="event-popup__slide" role="group" aria-roledescription="slide"
          aria-label={`Event ${index + 1} of ${slides.length}`} aria-live={reducedMotion || hovered || focused ? 'polite' : 'off'}>
          <p className="event-popup__eyebrow">Upcoming event{slides.length > 1 ? ` · ${index + 1} of ${slides.length}` : ''}</p>
          <h2 id="eventPopupTitle">{event.title}</h2>
          <p className="event-popup__date">{event.date}{event.endDate ? ' – ' + event.endDate : ''}</p>
          <div className="event-popup__actions">
            <a className="btn btn--ghost event-popup__link" href={url} onFocus={() => setFocused(true)} onBlur={() => setFocused(false)}>View event details &rarr;</a>
            {event.registrationOpen && <a className="btn event-popup__register" href={url + '/register'} onFocus={() => setFocused(true)} onBlur={() => setFocused(false)}>Register now <span aria-hidden="true">↗</span></a>}
          </div>
        </div>
        {slides.length > 1 && <div className="event-popup__controls" role="group" aria-label="Event carousel controls">
          <button type="button" className="event-popup__arrow" aria-label="Previous event" onClick={() => go(index - 1)}>&larr;</button>
          <div className="event-popup__dots">
            {slides.map((slide, i) => <button key={slide.slug} type="button" className="event-popup__dot"
              aria-label={`Show event ${i + 1}: ${slide.title}`} aria-current={i === index ? 'true' : undefined} onClick={() => go(i)} />)}
          </div>
          <button type="button" className="event-popup__arrow" aria-label="Next event" onClick={() => go(index + 1)}>&rarr;</button>
        </div>}
      </div>
    </>}
  </dialog>;
}
