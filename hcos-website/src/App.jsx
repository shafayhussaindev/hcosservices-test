import React, { useEffect } from 'react';
import Navigation from './components/Navigation.jsx';
import Footer from './components/Footer.jsx';
import pages from './pages/index.js';
import initializeInteractions from './lib/interactions.js';

export default function App({ page, values, nav }) {
  const Page = pages[page];
  if (!Page) throw new Error('Unknown page: ' + page);
  useEffect(() => initializeInteractions(), []);
  return <><Navigation active={nav} /><Page values={values} /><Footer /></>;
}
