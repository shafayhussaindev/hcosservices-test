import React from 'react';


export default function AboutPage({ values = {} }) {
  return <>
    <main id="main">
      <section className="page-hero">
        <div className="container page-hero__inner">
          <p className="page-hero__eyebrow">
            {"About Us"}
          </p>
          <h1 className="page-hero__title">
            {"ABOUT OUR COMPANY"}
          </h1>
        </div>
      </section>
      <section className="section">
        <div className="container split">
          <div>
            <h2 className="section-title reveal">
              {"COMPANY BACKGROUND"}
            </h2>
            <div className="section-rule reveal" style={{"--d":"80ms"}}></div>
            <div className="prose reveal" style={{"--d":"140ms"}}>
              <p>
                {"Human Capital Optimization Services (Private) Limited (HCOS), established in 2015 and incorporated with the SECP in 2016, is a leading provider of integrated human resource solutions in Pakistan. With services ranging from executive search, HR consultancy, and recruitment outsourcing to corporate training, event management, and IT solutions, HCOS is dedicated to bridging the gap between organizational needs and human capital potential."}
              </p>
              <p>
                {"HCOS works across diverse industries including FMCG, pharmaceuticals, construction, oil & gas, education, and the development sector. Its performance-driven and capacity-building approach has positioned the company as a trusted partner in workforce optimization and organizational development nationwide."}
              </p>
              <p>
                {"Beyond its core services, HCOS is recognized for national initiatives such as the Eco-Textile Conference and Let’s Bridge the Gap, which promote collaboration between academia and industry. These platforms reflect HCOS’s broader mission to drive innovation, align skills with market demands, and support sustainable development through strategic partnerships."}
              </p>
            </div>
          </div>
          <figure className="split__figure reveal-clip">
            <img src="/assets/img/svc-governance.webp" alt="HCOS consultants in a client meeting" loading="lazy" decoding="async" width="900" height="619" />
          </figure>
        </div>
      </section>
      <section className="section section--offwhite">
        <div className="container">
          <div className="mv-grid">
            <article className="mv-card reveal">
              <span className="mv-card__icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <circle cx="12" cy="12" r="8.4" stroke="currentColor" strokeWidth="1.7"></circle>
                  <circle cx="12" cy="12" r="3.4" stroke="currentColor" strokeWidth="1.7"></circle>
                  <path d="M12 1.8v3M12 19.2v3M1.8 12h3M19.2 12h3" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"></path>
                </svg>
              </span>
              <h2 className="mv-card__title">
                {"Our Mission"}
              </h2>
              <p className="mv-card__text">
                {"To provide best solution to our clients in Human Resources and empower them with the latest tools and training necessary to get a competitive edge in the market."}
              </p>
            </article>
            <article className="mv-card reveal" style={{"--d":"100ms"}}>
              <span className="mv-card__icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M2.5 12S6 5.8 12 5.8 21.5 12 21.5 12 18 18.2 12 18.2 2.5 12 2.5 12Z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"></path>
                  <circle cx="12" cy="12" r="3.1" stroke="currentColor" strokeWidth="1.7"></circle>
                </svg>
              </span>
              <h2 className="mv-card__title">
                {"Our Vision"}
              </h2>
              <p className="mv-card__text">
                {"To be respected and appreciated by our clients to have significant addition to their success in business."}
              </p>
            </article>
          </div>
        </div>
      </section>
      <section className="section">
        <div className="container">
          <div className="section-head section-head--center">
            <h2 className="section-title reveal">
              {"COMPANY VALUES"}
            </h2>
            <div className="section-rule reveal" style={{"--d":"80ms"}}></div>
          </div>
          <div className="values-grid">
            <article className="value-card reveal" style={{"--d":"0ms"}}>
              <span className="value-card__icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M12 3.4v16.2M7 20.6h10" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"></path>
                  <path d="M4.2 6.6h15.6" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"></path>
                  <path d="M4.2 6.6 1.9 12.4h4.6L4.2 6.6ZM19.8 6.6l-2.3 5.8h4.6l-2.3-5.8Z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"></path>
                  <path d="M1.9 12.4a2.3 2.3 0 0 0 4.6 0M17.5 12.4a2.3 2.3 0 0 0 4.6 0" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"></path>
                  <circle cx="12" cy="4" r="1.4" stroke="currentColor" strokeWidth="1.7"></circle>
                </svg>
              </span>
              <h3 className="value-card__title">
                {"Ethical"}
              </h3>
              <p className="value-card__text">
                {"HCOS has a responsibility to set and support ethical standards for the Event Management, trainings and human resource profession."}
              </p>
            </article>
            <article className="value-card reveal" style={{"--d":"90ms"}}>
              <span className="value-card__icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M7 10.4 10.6 3a2.2 2.2 0 0 1 2.2 2.2v3.3h5.1a2 2 0 0 1 2 2.4l-1.4 6.6a2.4 2.4 0 0 1-2.3 1.9H7" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"></path>
                  <rect x="2.8" y="10.4" width="4.2" height="9" rx="1.2" stroke="currentColor" strokeWidth="1.7"></rect>
                </svg>
              </span>
              <h3 className="value-card__title">
                {"Efficiency"}
              </h3>
              <p className="value-card__text">
                {"Resource assistance and management services are provided in a timely, cost-effective manner."}
              </p>
            </article>
            <article className="value-card reveal" style={{"--d":"180ms"}}>
              <span className="value-card__icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="m3 11.2 3.4-3.4a2 2 0 0 1 1.5-.6h2.3l2.2 2.2a1.5 1.5 0 0 1-2.1 2.1L9 10.2" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"></path>
                  <path d="M13.4 9.4h2.9a2 2 0 0 1 1.4.6L21 13.4" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"></path>
                  <path d="m12.2 12.6 2.4 2.4M10.4 14.4l2.2 2.2M8.6 16.2l1.7 1.7" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"></path>
                </svg>
              </span>
              <h3 className="value-card__title">
                {"Effectiveness"}
              </h3>
              <p className="value-card__text">
                {"HCOS has a responsibility to set and support ethical standards for the Event Management, trainings and human resource profession."}
              </p>
            </article>
            <article className="value-card reveal" style={{"--d":"270ms"}}>
              <span className="value-card__icon">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <circle cx="12" cy="12" r="8.6" stroke="currentColor" strokeWidth="1.7"></circle>
                  <path d="m8.2 12.2 2.6 2.6 5-5.4" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"></path>
                </svg>
              </span>
              <h3 className="value-card__title">
                {"The Right Fit"}
              </h3>
              <p className="value-card__text">
                {"Right people at the right place at the right time."}
              </p>
            </article>
          </div>
        </div>
      </section>
      <section className="section section--tight section--offwhite">
        <div className="container">
          <div className="section-head section-head--center">
            <h2 className="section-title reveal">
              {"TRUSTED PARTNERS"}
            </h2>
            <div className="section-rule reveal" style={{"--d":"80ms"}}></div>
          </div>
          <div className="partners reveal" style={{"--d":"140ms"}} dangerouslySetInnerHTML={{ __html: values.PARTNER_LOGOS || '' }} />
        </div>
      </section>
      <section className="section cta-band">
        <div className="container cta-band__inner">
          <div>
            <h2 className="section-title reveal">
              {"Work with HCOS"}
            </h2>
            <p className="section-sub reveal" style={{"--d":"80ms"}}>
              {"Have questions or need assistance? Our team is ready to help you with the information and support you need."}
            </p>
          </div>
          <div className="reveal" style={{"--d":"160ms"}}>
            <a className="btn btn--primary" href="/contact">
              {"Contact Us "}
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
