import React from 'react';
import { createRoot, hydrateRoot } from 'react-dom/client';
import App from './App.jsx';

const data = JSON.parse(document.getElementById('react-page-data').textContent);
const container = document.getElementById('root');
if (container.hasChildNodes()) hydrateRoot(container, <App {...data} />);
else createRoot(container).render(<App {...data} />);
