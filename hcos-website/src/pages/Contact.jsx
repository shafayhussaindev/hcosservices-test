import React from 'react';
import { Html, decode } from '../components/Html.jsx';

export default function ContactPage({ values = {} }) {
  const value = key => decode(values[key] ?? '{{' + key + '}}');
  return <>
    <main id="main">
      <section className="page-hero page-hero--compact">
        <div className="container page-hero__inner">
          <p className="page-hero__eyebrow">
            {"Contact Us"}
          </p>
          <h1 className="page-hero__title">
            {"GET IN TOUCH"}
          </h1>
          <p className="page-hero__deck">
            {"Have questions or need assistance? Our team is ready to help you with the information and support you need."}
          </p>
        </div>
      </section>
      <div className="contact-map reveal">
        <iframe title="HCOS office location — Asia Pacific Trade Centre, Rashid Minhas Road, Karachi" src="https://maps.google.com/maps?q=Asia%20Pacific%20Trade%20Centre%20Rashid%20Minhas%20Road%20Karachi&z=15&output=embed" loading="lazy" referrerPolicy="no-referrer-when-downgrade"></iframe>
        <a className="contact-map__link" href="https://maps.google.com/?q=Asia+Pacific+Trade+Centre+Rashid+Minhas+Road+Karachi" target="_blank" rel="noopener">
          {"Open in Google Maps "}
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"></path>
          </svg>
        </a>
      </div>
      <section className="contact-details">
        <div className="container">
          <div className="contact-panel reveal">
            <p className="page-hero__eyebrow">
              {"Information"}
            </p>
            <h2 className="contact-panel__title">
              {"Our contacts"}
            </h2>
            <ul className="contact-panel__list">
              <li>
                <span className="contact__icon">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11Z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"></path>
                    <circle cx="12" cy="10" r="2.6" stroke="currentColor" strokeWidth="1.7"></circle>
                  </svg>
                </span>
                <span>
                  <strong>
                    {"Address"}
                  </strong>
                  {"Suite # DD-1, Mezzanine floor Asia Pacific Trade Centre Rashid Minhas Road Karachi, Pakistan."}
                </span>
              </li>
              <li>
                <span className="contact__icon">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M4.5 5.5c0-.8.7-1.5 1.5-1.5h2.2c.6 0 1.2.4 1.4 1l.9 2.6c.2.5 0 1.1-.4 1.4l-1.3 1a12 12 0 0 0 5.2 5.2l1-1.3c.3-.4.9-.6 1.4-.4l2.6.9c.6.2 1 .8 1 1.4V18c0 .8-.7 1.5-1.5 1.5A15 15 0 0 1 4.5 5.5Z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"></path>
                  </svg>
                </span>
                <span>
                  <strong>
                    {"Tel"}
                  </strong>
                  <a href="tel:+922134160979">
                    {"(021)34160979"}
                  </a>
                  {" | "}
                  <a href="tel:+922134637537">
                    {"(021) 34637537"}
                  </a>
                </span>
              </li>
              <li>
                <span className="contact__icon">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <rect x="6.5" y="2.5" width="11" height="19" rx="2.4" stroke="currentColor" strokeWidth="1.7"></rect>
                    <path d="M10.5 18.5h3" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round"></path>
                  </svg>
                </span>
                <span>
                  <strong>
                    {"Cell"}
                  </strong>
                  <a href="tel:+923103631599">
                    {"+92 310-3631599"}
                  </a>
                </span>
              </li>
              <li>
                <span className="contact__icon">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <rect x="3" y="5.5" width="18" height="13" rx="2.5" stroke="currentColor" strokeWidth="1.7"></rect>
                    <path d="m4 7.5 8 5.5 8-5.5" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"></path>
                  </svg>
                </span>
                <span>
                  <strong>
                    {"E-mail"}
                  </strong>
                  <a href="mailto:info@hcosservices.com">
                    {"info@hcosservices.com"}
                  </a>
                </span>
              </li>
            </ul>
            <div className="contact-panel__social" dangerouslySetInnerHTML={{ __html: values.CONTACT_SOCIAL || '' }} />
          </div>
        </div>
      </section>
      <section className="section">
        <div className="container contact__grid contact__grid--even">
          <div>
            <p className="page-hero__eyebrow reveal">
              {"Write us"}
            </p>
            <h2 className="section-title reveal" style={{"--d":"60ms"}}>
              {"Contact us here"}
            </h2>
            <div className="section-rule reveal" style={{"--d":"100ms"}}></div>
            <form className="form reveal" style={{"--d":"140ms"}} id="contactForm" noValidate data-endpoint={value('FORM_ENDPOINT')} data-fallback={value('FORM_FALLBACK_EMAIL')} data-subject="Website enquiry">
              <Html html={values.FORM_HIDDEN} />
              <input type="hidden" name="form_type" value="contact" />
              <div className="form__row">
                <div className="form__field">
                  <label className="sr-only" htmlFor="firstName">
                    {"First Name"}
                  </label>
                  <input id="firstName" name="firstName" type="text" placeholder="First Name" autoComplete="given-name" />
                </div>
                <div className="form__field">
                  <label className="sr-only" htmlFor="lastName">
                    {"Last Name"}
                  </label>
                  <input id="lastName" name="lastName" type="text" placeholder="Last Name" autoComplete="family-name" />
                </div>
                <div className="form__field">
                  <label className="sr-only" htmlFor="email">
                    {"Email"}
                  </label>
                  <input id="email" name="email" type="email" placeholder="Email" autoComplete="email" required />
                </div>
                <div className="form__field">
                  <label className="sr-only" htmlFor="phone">
                    {"Phone Number"}
                  </label>
                  <input id="phone" name="phone" type="tel" placeholder="Phone Number" autoComplete="tel" />
                </div>
                <div className="form__field form__field--full">
                  <label className="sr-only" htmlFor="message">
                    {"Message"}
                  </label>
                  <textarea id="message" name="message" placeholder="Message" required></textarea>
                </div>
              </div>
              <button className="btn btn--primary" type="submit">
                {"Send "}
                <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                  <path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"></path>
                </svg>
              </button>
              <p className="form__notice" role="status" hidden></p>
            </form>
          </div>
          <div>
            <p className="page-hero__eyebrow reveal">
              {"Information"}
            </p>
            <h2 className="section-title reveal" style={{"--d":"60ms"}}>
              {"When are we open?"}
            </h2>
            <div className="section-rule reveal" style={{"--d":"100ms"}}></div>
            <p className="lead reveal" style={{"--d":"140ms","marginTop":"26px"}}>
              {"Our team is available during regular business hours to assist you with inquiries and provide professional HR support."}
            </p>
            <div className="hours reveal" style={{"--d":"200ms"}}>
              <table className="hours__table">
                <caption className="sr-only">
                  {"Schedule Table"}
                </caption>
                <thead>
                  <tr>
                    <th scope="col">
                      {"Day"}
                    </th>
                    <th scope="col">
                      {"Normal Timing"}
                    </th>
                    <th scope="col">
                      {"Ramzaan Timing"}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <th scope="row">
                      {"Monday – Friday"}
                    </th>
                    <td>
                      {"9am – 6pm"}
                    </td>
                    <td>
                      {"9am – 3pm"}
                    </td>
                  </tr>
                  <tr>
                    <th scope="row">
                      {"Saturday"}
                    </th>
                    <td>
                      {"10am – 1pm"}
                    </td>
                    <td>
                      {"10am – 12pm"}
                    </td>
                  </tr>
                  <tr>
                    <th scope="row">
                      {"Sunday"}
                    </th>
                    <td>
                      {"Closed"}
                    </td>
                    <td>
                      {"Closed"}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
    </main>
  </>;
}
