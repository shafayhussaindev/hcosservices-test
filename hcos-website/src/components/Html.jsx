import React from 'react';
import { decodeHTML } from 'entities';

// PHP fragments are already escaped by the backend; decode text once for JSX.
export const decode = value => decodeHTML(String(value ?? ''));

export function Html({ html = '' }) {
  // Only application-rendered fragments belong here, never raw form input.
  return <div style={{ display: 'contents' }} dangerouslySetInnerHTML={{ __html: html }} />;
}
