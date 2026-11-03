import React from 'react';
import { Html, decode } from '../components/Html.jsx';

export default function EventRegistration({ values = {} }) {
  const title = decode(values.TITLE || '');
  const url = decode(values.EVENT_URL || '');
  return <main id="main" className="event-registration">
    <section className="page-hero page-hero--compact">
      <div className="container page-hero__inner">
        <a className="event-back" href={url}>← Back to event details</a>
        <h1 className="page-hero__title">REGISTRATION FORM</h1>
        <p className="page-hero__deck">{title}</p>
      </div>
    </section>
    <div className="container registration-layout">
      <aside className="registration-context">
        <img src={decode(values.IMAGE || '')} alt={decode(values.ALT || title)} />
        <h2>{title}</h2>
        <Html html={values.DETAILS} />
        <a className="event-back" href={url}>View programme and event information</a>
      </aside>
      <Html html={values.REGISTRATION} />
    </div>
  </main>;
}
