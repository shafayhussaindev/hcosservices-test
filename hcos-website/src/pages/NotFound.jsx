import React from 'react';


export default function NotFoundPage({ values = {} }) {
  return <>
    <main id="main">
      <section className="page-hero">
        <div className="container page-hero__inner">
          <p className="page-hero__eyebrow">
            {"Error 404"}
          </p>
          <h1 className="page-hero__title">
            {"PAGE NOT FOUND"}
          </h1>
          <p className="page-hero__deck">
            {"The page you were looking for has moved or does not exist."}
          </p>
        </div>
      </section>
      <section className="section">
        <div className="container">
          <div className="empty-state reveal" style={{"maxWidth":"560px"}}>
            <h2 className="empty-state__title">
              {"Try one of these instead"}
            </h2>
            <p className="empty-state__text">
              {"Head back to the homepage, or get in touch and we will point you in the right direction."}
            </p>
            <div style={{"display":"flex","flexWrap":"wrap","gap":"12px","marginTop":"26px"}}>
              <a className="btn btn--primary" href="/">
                {"Go to homepage "}
                <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                  <path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"></path>
                </svg>
              </a>
              <a className="btn btn--ghost" href="/contact">
                {"Contact Us"}
              </a>
            </div>
          </div>
        </div>
      </section>
    </main>
  </>;
}
