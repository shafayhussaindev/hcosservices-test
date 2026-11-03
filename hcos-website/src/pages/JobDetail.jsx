import React from 'react';
import { decode } from '../components/Html.jsx';

export default function JobDetailPage({ values = {} }) {
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
              <a href="/careers">
                {"Careers"}
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
            <p className="page-hero__eyebrow reveal">
              {"Open position"}
            </p>
            <h1 className="detail__title reveal" style={{"--d":"60ms"}}>
              {value('TITLE')}
            </h1>
            <div className="section-rule reveal" style={{"--d":"120ms"}}></div>
            <ul className="job-meta reveal" style={{"--d":"160ms"}} dangerouslySetInnerHTML={{ __html: values.META_ITEMS || '' }} />
          </header>
          <div className="prose article-body detail__body reveal" dangerouslySetInnerHTML={{ __html: values.BODY || '' }} />
          <aside className="register reveal" id="apply">
            <div className="register__head">
              <span className="register__badge">
                {"Apply"}
              </span>
              <h2 className="register__title">
                {"Apply for this role"}
              </h2>
              <p className="register__text">
                {"Send your CV and a short note about your experience. Please put the role title in the subject line."}
              </p>
            </div>
            <a className="btn btn--primary" href={"mailto:" + value('APPLY_EMAIL') + "?subject=" + value('APPLY_SUBJECT')}>
              {"Email your application "}
              <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"></path>
              </svg>
            </a>
            <p className="empty-state__note">
              {"Applications go to "}
              <a href={"mailto:" + value('APPLY_EMAIL')}>
                {value('APPLY_EMAIL')}
              </a>
            </p>
          </aside>
        </div>
      </article>
    </main>
  </>;
}
