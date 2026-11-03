import React from 'react';
import { Html, decode } from '../components/Html.jsx';

export default function SharePage({ values = {} }) {
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
              <a href="/events">
                {"Events"}
              </a>
            </li>
            <li>
              <a href={"/events/" + value('SHARE_EVENT')}>
                {"Event"}
              </a>
            </li>
            <li aria-current="page">
              {"Share"}
            </li>
          </ol>
        </div>
      </nav>
      <section className="section detail">
        <div className="container">
          <div className="detail__inner">
            <header className="detail__head">
              <p className="page-hero__eyebrow reveal">
                {"Thank you"}
              </p>
              <h1 className="detail__title reveal" style={{"--d":"60ms"}}>
                {value('SHARE_TITLE')}
              </h1>
              <div className="section-rule reveal" style={{"--d":"120ms"}}></div>
            </header>
            <Html html={values.SHARE_BODY} />
          </div>
        </div>
      </section>
    </main>
  </>;
}
