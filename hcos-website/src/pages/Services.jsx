import React from 'react';


export default function ServicesPage({ values = {} }) {
  return <>
    <main id="main">
      <section className="page-hero">
        <div className="container page-hero__inner">
          <p className="page-hero__eyebrow">
            {"Services"}
          </p>
          <h1 className="page-hero__title">
            {"OUR SERVICES"}
          </h1>
          <p className="page-hero__deck">
            {"We provide professional solutions designed to help individuals and organizations grow through innovation, expertise, and reliable support."}
          </p>
        </div>
      </section>
      <section className="section">
        <div className="container service-rows" dangerouslySetInnerHTML={{ __html: values.SERVICE_ROWS || '' }} />
      </section>
      <section className="section section--offwhite cta-band">
        <div className="container cta-band__inner">
          <div>
            <h2 className="section-title reveal">
              {"Need a tailored solution?"}
            </h2>
            <p className="section-sub reveal" style={{"--d":"80ms"}}>
              {"Have questions or need assistance? Our team is ready to help you with the information and support you need."}
            </p>
          </div>
          <div className="reveal" style={{"--d":"160ms"}}>
            <a className="btn btn--primary" href="/contact">
              {"Get in Touch "}
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
