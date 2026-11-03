import React from 'react';
import site from '../data/site.json';

const icons = {
  Facebook: <path d="M13.5 21v-8h2.7l.4-3.1h-3.1V7.9c0-.9.25-1.5 1.55-1.5h1.65V3.6c-.29-.04-1.27-.12-2.41-.12-2.39 0-4.02 1.46-4.02 4.13V9.9H7.5V13h2.77v8h3.23Z" />,
  Instagram: <><rect x="3.2" y="3.2" width="17.6" height="17.6" rx="5" fill="none" stroke="currentColor" strokeWidth="1.7" /><circle cx="12" cy="12" r="3.9" fill="none" stroke="currentColor" strokeWidth="1.7" /><circle cx="16.9" cy="7.1" r="1.1" /></>,
  Linkedin: <path d="M6.94 8.4H3.9V21h3.04V8.4ZM5.42 3a1.77 1.77 0 1 0 0 3.54 1.77 1.77 0 0 0 0-3.54ZM20.1 13.6c0-3.2-1.71-4.7-4-4.7-1.84 0-2.66 1.02-3.12 1.73V8.4H9.94c.04.86 0 12.6 0 12.6h3.04v-7.04c0-.27.02-.55.1-.74.22-.55.72-1.11 1.56-1.11 1.1 0 1.54.84 1.54 2.07V21h3.04l-.12-7.4Z" />
};

export default function Footer() {
  return <footer className="footer">
    <div className="container"><div className="footer__grid">
      <div>
        <img className="footer__logo" src="/assets/logos/hcos-logo.png" alt="HCOS" width="176" height="115" loading="lazy" />
        <h2 className="footer__heading">Contact Us</h2>
        <p className="footer__text">{site.address}<br />
          Tel: <a href={site.phones[0].href}>{site.phones[0].label}</a> | <a href={site.phones[1].href}>{site.phones[1].label}</a><br />
          Cell: <a href={site.cell.href}>{site.cell.label}</a><br />
          E-mail: <a href={'mailto:' + site.email}>{site.email}</a>
        </p>
      </div>
      <div><h2 className="footer__heading">Site Map</h2><ul className="footer__list">
        {site.sitemap.map(item => <li key={item.href}><a href={item.href}>{item.label}</a></li>)}
      </ul></div>
      <div><h2 className="footer__heading">Quick Connect</h2><div className="footer__social">
        {site.social.map(item => <a key={item.name} href={item.url} target={item.url === '#' ? undefined : '_blank'}
          rel={item.url === '#' ? undefined : 'noopener'} aria-disabled={item.url === '#' ? true : undefined}>
          <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">{icons[item.name]}</svg>{item.name}
        </a>)}
      </div></div>
      {site.visitorCounter?.enabled && <div><h2 className="footer__heading">Visitor Counter</h2>
        <div className="counter" id="visitorCounter" data-seed={site.visitorCounter.seed} aria-live="polite">
          {String(site.visitorCounter.seed).split('').map((digit, i) => <span key={i}>{digit}</span>)}
        </div>
      </div>}
    </div></div>
    <div className="container"><div className="footer__bottom">{site.copyright}</div></div>
  </footer>;
}
