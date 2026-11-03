import React from 'react';


export default function NewsroomPage({ values = {} }) {
  return <>
    <main id="main">
      <section className="page-hero">
        <div className="container page-hero__inner">
          <p className="page-hero__eyebrow">
            {"Newsroom"}
          </p>
          <h1 className="page-hero__title">
            {"INSIGHTS & UPDATES"}
          </h1>
          <p className="page-hero__deck">
            {"Stay informed with our latest articles, announcements, and industry insights designed to keep you ahead."}
          </p>
        </div>
      </section>
      <section className="section">
        <div className="container">
          <div className="news-grid" dangerouslySetInnerHTML={{ __html: values.NEWS_CARDS || '' }} />
        </div>
      </section>
    </main>
  </>;
}
