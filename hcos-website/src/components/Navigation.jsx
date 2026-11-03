import React from 'react';
import site from '../data/site.json';

export default function Navigation({ active }) {
  return <>
    <a className="sr-only" href="#main">Skip to main content</a>
    <header className="nav" id="nav">
      <div className="container nav__inner">
        <a className="nav__logo" href="/" aria-label="HCOS — home">
          <img src="/assets/logos/hcos-logo.png" alt="HCOS" width="176" height="115" />
        </a>
        <nav aria-label="Primary">
          <ul className="nav__menu">
            {site.nav.map(item => <li key={item.key}>
              <a className={'nav__link' + (active === item.key ? ' is-active' : '')}
                href={item.href} aria-current={active === item.key ? 'page' : undefined}>{item.label}</a>
            </li>)}
          </ul>
        </nav>
        <a className="btn btn--primary nav__cta" href={site.employeesPortal}>Employees Portal</a>
        <button className="nav__toggle" id="navToggle" aria-label="Open menu" aria-expanded="false" aria-controls="navPanel"><span /></button>
      </div>
    </header>
    <div className="nav__panel" id="navPanel">
      {site.nav.map(item => <a key={item.key} href={item.href} aria-current={active === item.key ? 'page' : undefined}>{item.label}</a>)}
      <a className="btn btn--primary" href={site.employeesPortal}>Employees Portal</a>
    </div>
  </>;
}
