# Job openings

Use this guide when editing Careers content. Start with the
[project README](../../README.md) for setup and deployment instructions.

`jobs.json` drives the Careers page. While it is empty the page shows the
"No current openings" state. Add one object per role and it gets a card on
`/careers` plus its own page at `/careers/<slug>`.

```json
[
  {
    "slug": "full-stack-backend-developer",
    "title": "Full Stack Backend Developer",
    "location": "Karachi, Pakistan",
    "type": "Full Time",
    "salary": "PKR 150,000 – 200,000",
    "summary": "One or two sentences shown on the listing card.",
    "sections": [
      { "heading": "Job Description", "paragraphs": ["First paragraph.", "Second paragraph."] },
      { "heading": "Responsibilities", "items": ["Bullet one", "Bullet two"] },
      { "heading": "Education & Experience", "items": ["Bullet one", "Bullet two"] }
    ],
    "applyEmail": "info@hcosservices.com"
  }
]
```

- `slug`, `title`, `summary`, `sections` — required
- `location`, `type`, `salary` — optional; shown as chips under the title when present
- Each section takes either `paragraphs` (prose) or `items` (bullet list)
- `applyEmail` — optional; defaults to the site email

The object above is a format example, not an approved job listing. Use only
approved job details, give each role a unique URL-safe slug (lowercase letters,
numbers and hyphens), and keep the file a valid JSON array without comments.

Run `npm run build` from the repository root, preview `/careers` and the role's detail page,
then follow [deployment](../../DEPLOYMENT.md). When a role closes, remove
its entry; use `[]` when no roles remain. A build removes the local detail page,
but deployment also needs to remove its obsolete server file as described in
the deployment guide.
