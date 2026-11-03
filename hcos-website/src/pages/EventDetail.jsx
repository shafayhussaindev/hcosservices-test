import React from 'react';
import { Html, decode } from '../components/Html.jsx';
import EventCountdown from '../components/EventCountdown.jsx';

export default function EventDetailPage({ values = {} }) {
  const value = key => decode(values[key] ?? '{{' + key + '}}');
  return <>
    <main id="main" className="event-promotion">
      <nav className="crumbs" aria-label="Breadcrumb">
        <div className="container">
          <ol>
            <li>
              <a href="/">
                {"Home"}
              </a>
            </li>
            <li>
              <a href="/events">
                {"Events"}
              </a>
            </li>
            <li aria-current="page">
              {value('TITLE')}
            </li>
          </ol>
        </div>
      </nav>
      <header className="event-hero">
        <img className="event-hero__image" src={value('IMAGE')} alt={value('ALT')} fetchPriority="high" decoding="async" />
        <div className="container event-hero__content">
          <EventCountdown date={decode(values.COUNTDOWN_DATE || '')} />
          <h1>{value('TITLE')}</h1>
        </div>
      </header>
      <article className="event-content">
        <div className="container">
          <section className="event-summary" aria-label="Event information">
            <div className="event-summary__identity">
              <img src="/assets/logos/hcos-logo.png" alt="HCOS Services" width="176" height="115" />
              <p>{value('TITLE')}</p>
            </div>
            <Html html={values.DETAILS} />
          </section>
          <div className="event-story">
              <div className="event-story__intro prose">
                <h2>About the event</h2>
                <p className="lead reveal">
                  {value('INTRO')}
                </p>
              </div>
              <div className="event-action" id="register">
                <Html html={values.EVENT_ACTION} />
              </div>
          </div>
          <div className="event-programme">
              <Html html={values.BROCHURE} />
              <Html html={values.PEOPLE} />
              <Html html={values.MATERIALS} />
              <Html html={values.GALLERY} />
          </div>
          <section className="event-supporters" aria-label="Sponsors and partners">
            <Html html={values.SPONSORS} />
            <Html html={values.PARTNERS} />
          </section>
        </div>
      </article>
      <section className="section section--offwhite">
        <div className="container">
          <div className="section-head section-head--center">
            <h2 className="section-title reveal">
              {"More Events"}
            </h2>
            <div className="section-rule reveal" style={{"--d":"80ms"}}></div>
          </div>
          <div className="events__grid" dangerouslySetInnerHTML={{ __html: values.MORE_CARDS || '' }} />
          <div className="section-foot reveal">
            <a className="btn btn--ghost" href="/events">
              {"All Events "}
              <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"></path>
              </svg>
            </a>
          </div>
        </div>
      </section>
    </main>
  </>;
}
