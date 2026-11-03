import React from 'react';


export default function EventsPage({ values = {} }) {
  return <>
    <main id="main">
      <section className="page-hero">
        <div className="container page-hero__inner">
          <p className="page-hero__eyebrow">
            {"Events"}
          </p>
          <h1 className="page-hero__title">
            {"OUR LATEST EVENT"}
          </h1>
          <p className="page-hero__deck">
            {"From concept to execution, we design events that inspire teams, impress clients, and elevate brands."}
          </p>
        </div>
      </section>
      <section className="section">
        <div className="container">
          <div className="events__grid" dangerouslySetInnerHTML={{ __html: values.EVENT_CARDS || '' }} />
        </div>
      </section>
    </main>
  </>;
}
