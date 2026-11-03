import React from 'react';
import { Html, decode } from '../components/Html.jsx';

export default function CareersPage({ values = {} }) {
  return <>
    <main id="main">
      <section className="page-hero">
        <div className="container page-hero__inner">
          <p className="page-hero__eyebrow">
            {"Career"}
          </p>
          <h1 className="page-hero__title">
            {"CAREERS AT HCOS"}
          </h1>
          <p className="page-hero__deck">
            {"Right people at the right place at the right time."}
          </p>
        </div>
      </section>
      <section className="section">
        <div className="container split split--narrow">
          <div>
            <h2 className="section-title reveal">
              {"Why work here"}
            </h2>
            <div className="section-rule reveal" style={{"--d":"80ms"}}></div>
            <div className="prose reveal" style={{"--d":"140ms"}}>
              <p>
                {"Our mission is to provide the best solution to our clients in Human Resources and empower them with the latest tools and training necessary to get a competitive edge in the market — and we hold ourselves to the same standard internally."}
              </p>
              <p>
                {"Four values shape how we work. "}
                <strong>
                  {"Ethical:"}
                </strong>
                {" we set and support ethical standards for the event management, training and human resource profession. "}
                <strong>
                  {"Efficiency:"}
                </strong>
                {" resource assistance and management services are provided in a timely, cost-effective manner. "}
                <strong>
                  {"Effectiveness"}
                </strong>
                {" and "}
                <strong>
                  {"The Right Fit"}
                </strong>
                {" follow from that — right people at the right place at the right time."}
              </p>
            </div>
          </div>
          <Html html={values.JOBS} />
        </div>
      </section>
    </main>
  </>;
}
