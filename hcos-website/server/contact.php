<?php
// /contact — static page, but the form's endpoint and anti-bot fields are
// filled at request time so the signed timestamp is always fresh.
require __DIR__ . '/_app/bootstrap.php';
page_contact();
