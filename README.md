# Hotel Vastu Premium

This repository is split into two independent applications:

- `website/` — static Hotel Vastu Premium marketing website, assets, SEO files, deployment configuration and website QA tooling.
- `booking/` — Laravel booking, PMS and restaurant application.

Repository-level GitHub Actions remain in `.github/workflows/`.

## Website

For local website work:

```bash
cd website
npm run check
```

Open `website/index.html` directly or serve the `website/` directory with a static HTTP server.

The root `vercel.json` is a compatibility router so the existing Vercel project can keep serving the static site after this repository split. The website's own deployment configuration is in `website/vercel.json`.

See `website/README.md` and `website/DEPLOYMENT.md` for website-specific documentation.

## Booking system

The Laravel application remains isolated in `booking/`.

See `booking/README.md` and `booking/docs/MASTER_BLUEPRINT.md` for the frozen v1.0 booking-system scope.
