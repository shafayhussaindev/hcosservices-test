# Testimonials

Use this guide when approved client quotes are available. Start with the
[project README](../../README.md) for setup and deployment instructions.
Never invent a quote, person, company or endorsement. The object below is only
a format example.

`testimonials.json` drives the "What Our Clients Say" section on the homepage.
While the array is empty the section does not render at all.

Add one object per testimonial:

```json
[
  {
    "quote": "The exact words the client said. Keep it to two or three sentences.",
    "name": "Full Name",
    "role": "Job Title",
    "company": "Company Name",
    "logo": "assets/clients/company.jpg"
  }
]
```

- `quote`, `name` — required
- `role`, `company`, `logo` — optional; omit any you don't have
- `logo` — path to an existing file under `assets/`. Most client logos are
  already in `assets/clients/` (see that folder for exact filenames).

Keep the file a valid JSON array without comments. Run `npm run build` from
the repository root, preview the homepage, then follow [deployment](../../DEPLOYMENT.md).
Use `[]` to hide the section again. Three to six testimonials reads best; the
layout handles any count.
