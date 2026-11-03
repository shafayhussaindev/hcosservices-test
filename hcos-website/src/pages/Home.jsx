import React from 'react';
import { Html, decode } from '../components/Html.jsx';
import EventPopup from '../components/EventPopup.jsx';

export default function IndexPage({ values = {} }) {
  const value = key => decode(values[key] ?? '{{' + key + '}}');
  return <>
    <main id="main">
      <section className="hero">
        <div className="hero__media">
          <picture>
            <source media="(max-width: 768px)" srcSet="/assets/img/hero-mobile.webp" />
            <img src="/assets/img/hero.webp" alt="HCOS professionals walking together through a corporate campus" width="1920" height="1080" fetchPriority="high" decoding="async" />
          </picture>
        </div>
        <div className="hero__scrim"></div>
        <div className="orb" aria-hidden="true">
          <div className="orb__ring orb__ring--1"></div>
          <div className="orb__ring orb__ring--2"></div>
          <div className="orb__ring orb__ring--3"></div>
          <div className="orb__pulse"></div>
          <div className="orb__pulse orb__pulse--2"></div>
          <div className="orb__track orb__track--outer">
            <div className="orb__node" style={{"left":"50%","top":"calc(50% - 240px)"}}>
              <span className="orb__chip">
                {"People"}
              </span>
            </div>
            <div className="orb__node" style={{"left":"calc(50% + 208px)","top":"calc(50% + 120px)"}}>
              <span className="orb__chip">
                {"Business"}
              </span>
            </div>
            <div className="orb__node" style={{"left":"calc(50% - 208px)","top":"calc(50% + 120px)"}}>
              <span className="orb__chip">
                {"Communication"}
              </span>
            </div>
          </div>
          <div className="orb__track orb__track--inner">
            <div className="orb__node" style={{"left":"calc(50% + 144px)","top":"calc(50% - 90px)"}}>
              <span className="orb__chip">
                {"Workforce"}
              </span>
            </div>
            <div className="orb__node" style={{"left":"calc(50% + 6px)","top":"calc(50% + 170px)"}}>
              <span className="orb__chip">
                {"Training"}
              </span>
            </div>
            <div className="orb__node" style={{"left":"calc(50% - 150px)","top":"calc(50% - 80px)"}}>
              <span className="orb__chip">
                {"Events"}
              </span>
            </div>
          </div>
          <div className="orb__core">
            <img src="/assets/logos/hcos-logo.png" alt={''} width="176" height="115" />
          </div>
        </div>
        <div className="container hero__inner">
          <div className="hero__slides" id="heroSlides">
            <article className="hero__slide is-active">
              <h1 className="hero__title">
                <span className="line">
                  <span>
                    {"ABOUT OUR"}
                  </span>
                </span>
                <span className="line">
                  <span>
                    {"ORGANIZATION"}
                  </span>
                </span>
              </h1>
              <p className="hero__copy">
                {"Learn about our mission, vision, and the values that guide our commitment to excellence and long-term partnerships."}
              </p>
              <div className="hero__actions">
                <a className="btn btn--glass" href="/about">
                  {"Learn More "}
                  <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"></path>
                  </svg>
                </a>
              </div>
            </article>
            <article className="hero__slide">
              <h1 className="hero__title">
                <span className="line">
                  <span>
                    {"OUR"}
                  </span>
                </span>
                <span className="line">
                  <span>
                    {"SERVICES"}
                  </span>
                </span>
              </h1>
              <p className="hero__copy">
                {"We provide professional solutions designed to help individuals and organizations grow through innovation, expertise, and reliable support."}
              </p>
              <div className="hero__actions">
                <a className="btn btn--glass" href="/services">
                  {"Explore Services "}
                  <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"></path>
                  </svg>
                </a>
              </div>
            </article>
            <article className="hero__slide">
              <h1 className="hero__title">
                <span className="line">
                  <span>
                    {"OUR LATEST"}
                  </span>
                </span>
                <span className="line">
                  <span>
                    {"EVENT"}
                  </span>
                </span>
              </h1>
              <p className="hero__copy">
                {"From concept to execution, we design events that inspire teams, impress clients, and elevate brands."}
              </p>
              <div className="hero__actions">
                <a className="btn btn--glass" href="/events">
                  {"Explore Our Events "}
                  <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"></path>
                  </svg>
                </a>
              </div>
            </article>
            <article className="hero__slide">
              <h1 className="hero__title">
                <span className="line">
                  <span>
                    {"INSIGHTS &"}
                  </span>
                </span>
                <span className="line">
                  <span>
                    {"UPDATES"}
                  </span>
                </span>
              </h1>
              <p className="hero__copy">
                {"Stay informed with our latest articles, announcements, and industry insights designed to keep you ahead."}
              </p>
              <div className="hero__actions">
                <a className="btn btn--glass" href="/newsroom">
                  {"Read Articles "}
                  <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"></path>
                  </svg>
                </a>
              </div>
            </article>
            <article className="hero__slide">
              <h1 className="hero__title">
                <span className="line">
                  <span>
                    {"GET IN"}
                  </span>
                </span>
                <span className="line">
                  <span>
                    {"TOUCH"}
                  </span>
                </span>
              </h1>
              <p className="hero__copy">
                {"Have questions or need assistance? Our team is ready to help you with the information and support you need."}
              </p>
              <div className="hero__actions">
                <a className="btn btn--glass" href="/contact">
                  {"Contact Us "}
                  <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"></path>
                  </svg>
                </a>
              </div>
            </article>
          </div>
          <ul className="hero__dots" id="heroDots" role="tablist" aria-label="Hero slides"></ul>
        </div>
      </section>
      <section className="section" id="welcome">
        <div className="container welcome__grid">
          <div>
            <h2 className="section-title reveal">
              {"WELCOME TO HCOS"}
            </h2>
            <div className="section-rule reveal" style={{"--d":"80ms"}}></div>
            <p className="lead welcome__body reveal" style={{"--d":"140ms"}}>
              {"HCOS Services (Private) Limited is a leader in the HR services industry, creating and delivering services that enable our clients to succeed in a highly competitive market place. HCOS offers a range of services for the entire employment and business cycle. Our ultimate objective is to assist our clients make distinctive, substantial, and lasting improvements in their human capital management."}
            </p>
            <div className="welcome__facts">
              <div className="reveal" style={{"--d":"200ms"}}>
                <h3 className="fact__title">
                  {"Solution Focused"}
                </h3>
                <p className="fact__text">
                  {"We deliver tailored HR solutions that solve real business challenges and drive organizational growth."}
                </p>
              </div>
              <div className="reveal" style={{"--d":"260ms"}}>
                <h3 className="fact__title">
                  {"99.99% Success"}
                </h3>
                <p className="fact__text">
                  {"Our proven expertise and reliable processes ensure consistently successful HR outcomes for our clients."}
                </p>
              </div>
            </div>
            <div className="welcome__cta reveal" style={{"--d":"320ms"}}>
              <a className="btn btn--primary" href="/about">
                {"Read More "}
                <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                  <path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"></path>
                </svg>
              </a>
            </div>
          </div>
          <figure className="welcome__figure welcome__video reveal-clip" style={{"margin":"0"}} id="companyVideo" data-video="/assets/video/hcos-company-introduction.mp4">
            <img src="/assets/video/hcos-company-introduction-poster.webp" alt="HCOS company introduction video" loading="lazy" decoding="async" width="1280" height="720" />
            <button className="welcome__play" type="button" aria-label="Play the HCOS company introduction video">
              <span className="welcome__play-icon" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M8 5.6v12.8c0 .8.9 1.3 1.6.9l10-6.4a1 1 0 0 0 0-1.7l-10-6.4A1 1 0 0 0 8 5.6Z"></path>
                </svg>
              </span>
              <span className="welcome__play-label">
                {"Company Introduction"}
              </span>
            </button>
            <div className="welcome__badge">
              <img src="/assets/logos/hcos-logo.png" alt="HCOS" width="176" height="115" />
            </div>
          </figure>
        </div>
      </section>
      <section className="section section--offwhite" id="events">
        <div className="container">
          <div className="section-head section-head--center">
            <h2 className="section-title reveal">
              {"OUR LATEST EVENT"}
            </h2>
            <div className="section-rule reveal" style={{"--d":"80ms"}}></div>
            <p className="section-sub reveal" style={{"--d":"140ms"}}>
              {"From concept to execution, we design events that inspire teams, impress clients, and elevate brands."}
            </p>
          </div>
          <div className="events__grid" dangerouslySetInnerHTML={{ __html: values.EVENT_CARDS || '' }} />
          <div className="section-foot reveal">
            <a className="btn btn--ghost" href="/events">
              {"Explore Our Events "}
              <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"></path>
              </svg>
            </a>
          </div>
        </div>
      </section>
      <section className="section" id="services">
        <div className="container">
          <div className="section-head section-head--center">
            <h2 className="section-title reveal">
              {"OUR SERVICES"}
            </h2>
            <div className="section-rule reveal" style={{"--d":"80ms"}}></div>
            <p className="section-sub reveal" style={{"--d":"140ms"}}>
              {"We provide professional solutions designed to help individuals and organizations grow through innovation, expertise, and reliable support."}
            </p>
          </div>
          <div className="services__grid" dangerouslySetInnerHTML={{ __html: values.SERVICE_CARDS || '' }} />
          <div className="section-foot reveal">
            <a className="btn btn--primary" href="/services">
              {"Explore Services "}
              <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"></path>
              </svg>
            </a>
          </div>
        </div>
      </section>
      <section className="section section--tight section--offwhite" id="clients">
        <div className="container">
          <div className="section-head section-head--center">
            <h2 className="section-title reveal">
              {"OUR CLIENTS"}
            </h2>
            <div className="section-rule reveal" style={{"--d":"80ms"}}></div>
          </div>
        </div>
        <div className="marquee reveal" style={{"--d":"140ms"}}>
          <div className="marquee__track" id="marqueeTrack">
            <div className="marquee__group" id="marqueeGroup">
              <span className="marquee__item">
                <img src="/assets/clients/adamjee-insurance.jpg" alt="Adamjee Insurance" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/brookes.jpg" alt="Brookes" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/agp.jpg" alt="AGP" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/ismail-industries.jpg" alt="Ismail Industries Limited" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/ksbl.jpg" alt="Karachi School of Business & Leadership" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/engro.jpg" alt="Engro" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/sanofi.jpg" alt="Sanofi" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/tcs.jpg" alt="TCS" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/lucky-cement.jpg" alt="Lucky Cement" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/js-bank.jpg" alt="JS Bank" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/artistic-milliners.jpg" alt="Artistic Milliners" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/soorty.jpg" alt="Soorty" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/geo.jpg" alt="Geo" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/national.jpg" alt="National" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/shan.jpg" alt="Shan" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/youngs.jpg" alt="Young’s" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/jubilee-insurance.jpg" alt="Jubilee Insurance" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/hilal.jpg" alt="Hilal" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/habitt.jpg" alt="Habitt" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/imtiaz.jpg" alt="Imtiaz Super Market" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/amreli-steels.jpg" alt="Amreli Steels" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/international-steels.jpg" alt="International Steels Limited" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/indigo-textile.jpg" alt="Indigo Textile" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/yunus-textiles.jpg" alt="Yunus Textiles" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/house-of-habib.jpg" alt="House of Habib" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/bank-of-khyber.jpg" alt="The Bank of Khyber" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/nafa.jpg" alt="NBP Fullerton Asset Management" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/metier.jpg" alt="Métier" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/chase-up.jpg" alt="Chase Up" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/ocean-mall.jpg" alt="Ocean Mall" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/tarzz.jpg" alt="Tarzz" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/yd.jpg" alt="YD" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/redtone.jpg" alt="Redtone" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/millac.jpg" alt="Millac" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/pharma-obs.jpg" alt="Pharma OBS" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/mustaqim.jpg" alt="Mustaqim Dyeing & Printing Industries" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/osman-housing.jpg" alt="Osman Housing" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/unibro.jpg" alt="Unibro" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/paf-kiet.jpg" alt="PAF-Karachi Institute of Economics and Technology" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/iqra-university.jpg" alt="Iqra University" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/sir-syed-university.jpg" alt="Sir Syed University of Engineering & Technology" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/dha-suffa-university.jpg" alt="DHA Suffa University" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/iobm.jpg" alt="Institute of Business Management" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/indus-university.jpg" alt="Indus University" loading="lazy" decoding="async" />
              </span>
              <span className="marquee__item">
                <img src="/assets/clients/ntu.jpg" alt="National Textile University" loading="lazy" decoding="async" />
              </span>
            </div>
          </div>
        </div>
      </section>
      <Html html={values.TESTIMONIALS} />
      <section className="section" id="news">
        <div className="container">
          <div className="section-head section-head--center">
            <h2 className="section-title reveal">
              {"OUR LATEST NEWS"}
            </h2>
            <div className="section-rule reveal" style={{"--d":"80ms"}}></div>
          </div>
          <div className="news-grid" dangerouslySetInnerHTML={{ __html: values.NEWS_CARDS || '' }} />
          <div className="section-foot reveal">
            <a className="btn btn--ghost" href="/newsroom">
              {"Read Articles "}
              <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M2.5 8h11m0 0L9 3.5M13.5 8 9 12.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round"></path>
              </svg>
            </a>
          </div>
        </div>
      </section>
      <section className="section section--offwhite" id="contact">
        <div className="container contact__grid">
          <div>
            <h2 className="section-title reveal">
              {"GET IN TOUCH"}
            </h2>
            <div className="section-rule reveal" style={{"--d":"80ms"}}></div>
            <p className="lead reveal" style={{"--d":"140ms","marginTop":"26px","maxWidth":"440px"}}>
              {"Have questions or need assistance? Our team is ready to help you with the information and support you need."}
            </p>
            <div className="contact__details">
              <div className="contact__item reveal" style={{"--d":"200ms"}}>
                <span className="contact__icon">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 21s7-5.4 7-11a7 7 0 1 0-14 0c0 5.6 7 11 7 11Z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"></path>
                    <circle cx="12" cy="10" r="2.6" stroke="currentColor" strokeWidth="1.7"></circle>
                  </svg>
                </span>
                <div>
                  <p className="contact__label">
                    {"Office"}
                  </p>
                  <p className="contact__value">
                    {"Suite # DD-1, Mezzanine floor Asia Pacific Trade Centre Rashid Minhas Road Karachi, Pakistan."}
                  </p>
                </div>
              </div>
              <div className="contact__item reveal" style={{"--d":"260ms"}}>
                <span className="contact__icon">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M4.5 5.5c0-.8.7-1.5 1.5-1.5h2.2c.6 0 1.2.4 1.4 1l.9 2.6c.2.5 0 1.1-.4 1.4l-1.3 1a12 12 0 0 0 5.2 5.2l1-1.3c.3-.4.9-.6 1.4-.4l2.6.9c.6.2 1 .8 1 1.4V18c0 .8-.7 1.5-1.5 1.5A15 15 0 0 1 4.5 5.5Z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round"></path>
                  </svg>
                </span>
                <div>
                  <p className="contact__label">
                    {"Telephone"}
                  </p>
                  <p className="contact__value">
                    <a href="tel:+922134160979">
                      {"(021)34160979"}
                    </a>
                    {" | "}
                    <a href="tel:+922134637537">
                      {"(021) 34637537"}
                    </a>
                    <br />
                    {"Cell: "}
                    <a href="tel:+923103631599">
                      {"03103631599"}
                    </a>
                  </p>
                </div>
              </div>
              <div className="contact__item reveal" style={{"--d":"320ms"}}>
                <span className="contact__icon">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <rect x="3" y="5.5" width="18" height="13" rx="2.5" stroke="currentColor" strokeWidth="1.7"></rect>
                    <path d="m4 7.5 8 5.5 8-5.5" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"></path>
                  </svg>
                </span>
                <div>
                  <p className="contact__label">
                    {"E-mail"}
                  </p>
                  <p className="contact__value">
                    <a href="mailto:info@hcosservices.com">
                      {"info@hcosservices.com"}
                    </a>
                  </p>
                </div>
              </div>
            </div>
          </div>
          <form className="form reveal" style={{"--d":"180ms"}} noValidate data-endpoint={value('FORM_ENDPOINT')} data-fallback={value('FORM_FALLBACK_EMAIL')} data-subject="Website enquiry">
            <Html html={values.FORM_HIDDEN} />
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
            <p className="form__notice" role="status" aria-live="polite" hidden></p>
          </form>
        </div>
      </section>
    </main>
    <EventPopup events={value('POPUP_EVENTS')} />
  </>;
}
