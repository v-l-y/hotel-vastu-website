import fs from "node:fs";

const [page, reportPath] = process.argv.slice(2);
if (!page || !reportPath) {
  console.error("Usage: node scripts/lighthouse-check.mjs <page> <report.json>");
  process.exit(2);
}

const report = JSON.parse(fs.readFileSync(reportPath, "utf8"));
const scores = {
  performance: report.categories.performance?.score ?? 0,
  accessibility: report.categories.accessibility?.score ?? 0,
  "best-practices": report.categories["best-practices"]?.score ?? 0,
  seo: report.categories.seo?.score ?? 0,
};

const thresholds = {
  performance: 0.90,
  accessibility: 1,
  "best-practices": 1,
  seo: 1,
};

console.log(page, scores);
let failed = false;
for (const [name, min] of Object.entries(thresholds)) {
  if (scores[name] < min) {
    console.error(`${page} ${name} score ${scores[name]} below ${min}`);
    failed = true;
  }
}

for (const id of [
  "first-contentful-paint",
  "largest-contentful-paint",
  "speed-index",
  "total-blocking-time",
  "cumulative-layout-shift",
]) {
  const audit = report.audits[id];
  console.log(
    page,
    "metric",
    id,
    audit?.score,
    audit?.numericValue,
    audit?.displayValue || ""
  );
}

if (failed) process.exit(1);
