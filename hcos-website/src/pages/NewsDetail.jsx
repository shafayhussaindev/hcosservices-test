import React from 'react';
import { decode } from '../components/Html.jsx';

export default function NewsDetailPage({ values = {} }) {
  const value = key => decode(values[key] ?? '{{' + key + '}}');
  return <>
    <main id="main">
      <nav className="crumbs" aria-label="Breadcrumb">
        <div className="container">
          <ol>
            <li>
              <a href="/">
                {"Home"}
              </a>
            </li>
            <li>
              <a href="/newsroom">
                {"Newsroom"}
              </a>
            </li>
            <li aria-current="page">
              {value('TITLE')}
            </li>
          </ol>
        </div>
      </nav>
      <article className="section detail">
        <div className="container detail__inner">
          <header className="detail__head">
            <p className="article__meta reveal">
              <time dateTime={value('DATETIME')}>
                {value('DATE')}
              </time>
              {" · " + value('META')}
            </p>
            <h1 className="detail__title reveal" style={{"--d":"60ms"}}>
              {value('TITLE')}
            </h1>
            <div className="section-rule reveal" style={{"--d":"120ms"}}></div>
          </header>
          <figure className="detail__figure reveal-clip">
            <img src={value('IMAGE')} alt={value('ALT')} width="1600" height="766" fetchPriority="high" decoding="async" />
          </figure>
          <div className="prose article-body detail__body reveal" dangerouslySetInnerHTML={{ __html: values.BODY || '' }} />
          <footer className="article-foot reveal">
            <a className="link-arrow" href="/newsroom">
              <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true" style={{"transform":"rotate(180deg)"}}>
                <path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"></path>
              </svg>
              {" Back to Newsroom "}
            </a>
          </footer>
        </div>
      </article>
      <section className="section section--offwhite">
        <div className="container">
          <div className="section-head section-head--center">
            <h2 className="section-title reveal">
              {"More from the Newsroom"}
            </h2>
            <div className="section-rule reveal" style={{"--d":"80ms"}}></div>
          </div>
          <div className="news-grid news-grid--3" dangerouslySetInnerHTML={{ __html: values.MORE_CARDS || '' }} />
          <div className="section-foot reveal">
            <a className="btn btn--ghost" href="/newsroom">
              {"All Articles "}
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
