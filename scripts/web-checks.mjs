// Barrierefreiheit (axe, WCAG 2.2 AA) und Lighthouse für die wichtigsten
// Seiten – Pflichtenheft 7.3 und 10.3. Läuft im GitHub-Workflow
// „Barrierefreiheit und Performance“; lokal: SITE=http://… node scripts/web-checks.mjs
import { chromium } from 'playwright';
import AxeBuilder from '@axe-core/playwright';
import lighthouse from 'lighthouse';
import { launch } from 'chrome-launcher';
import { appendFileSync } from 'node:fs';

if (! process.env.SITE) {
    console.error('SITE fehlt (Adresse der Instanz, z. B. https://test.dpsg-trier.de).');
    process.exit(1);
}
const SITE = process.env.SITE.replace(/\/$/, '');
// Seitenlisten aus der Instanz-Konfiguration (scripts/web-check-pages.php).
const list = (value, fallback) => (value ? value.split(',').filter(Boolean) : fallback);
const PAGES = list(process.env.WEB_CHECK_PAGES, ['/', '/aktuelles', '/veranstaltungen', '/kalender', '/kontakt', '/status']);
const LIGHTHOUSE_PAGES = list(process.env.WEB_CHECK_LIGHTHOUSE, ['/', '/aktuelles', '/veranstaltungen']);
const summary = (line) => process.env.GITHUB_STEP_SUMMARY && appendFileSync(process.env.GITHUB_STEP_SUMMARY, line + '\n');

// 1. axe auf Desktop und Handy
const browser = await chromium.launch(process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {});
const violations = {};
for (const vp of [{ name: 'Desktop', width: 1280, height: 900 }, { name: 'Handy', width: 390, height: 844 }]) {
    const ctx = await browser.newContext({ viewport: vp });
    await ctx.addInitScript(() => localStorage.setItem('dpsg-consent-v1', JSON.stringify({ prefs: { stats: false, external: false }, savedAt: Date.now() })));
    for (const path of PAGES) {
        const page = await ctx.newPage();
        await page.goto(SITE + path, { waitUntil: 'networkidle' }).catch(() => {});
        await page.waitForTimeout(1000);
        const result = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
        for (const v of result.violations) {
            violations[v.id] ??= { help: v.help, impact: v.impact, where: new Set(), nodes: [] };
            violations[v.id].where.add(`${vp.name} ${path}`);
            for (const n of v.nodes.slice(0, 2)) violations[v.id].nodes.push(`${path}: ${n.target.join(' ')}`);
        }
        await page.close();
    }
    await ctx.close();
}
await browser.close();

summary('## Barrierefreiheit (axe, WCAG 2.2 AA)');
if (Object.keys(violations).length === 0) {
    summary(`Keine Verstöße auf ${PAGES.length} Seiten (Desktop und Handy).`);
    console.log('axe: keine Verstöße');
} else {
    for (const [id, v] of Object.entries(violations)) {
        const line = `- **${id}** (${v.impact}): ${v.help} – ${[...v.where].join(', ')}`;
        summary(line);
        console.log(line);
        v.nodes.slice(0, 4).forEach((n) => console.log('    ' + n));
    }
}

// 2. Lighthouse (Handy-Profil)
summary('\n## Lighthouse (Handy)\n\n| Seite | Performance | Barrierefreiheit | Best Practices | SEO |\n|---|---|---|---|---|');
const chrome = await launch({ chromePath: process.env.CHROME_PATH, chromeFlags: ['--headless=new', '--no-sandbox'] });
let lowA11y = false;
for (const path of LIGHTHOUSE_PAGES) {
    const run = await lighthouse(SITE + path, { port: chrome.port, output: 'json', logLevel: 'error', onlyCategories: ['performance', 'accessibility', 'best-practices', 'seo'] });
    const c = run.lhr.categories;
    const s = (k) => Math.round((c[k]?.score ?? 0) * 100);
    if (s('accessibility') < 95) lowA11y = true;
    summary(`| ${path} | ${s('performance')} | ${s('accessibility')} | ${s('best-practices')} | ${s('seo')} |`);
    console.log(`Lighthouse ${path}: Performance ${s('performance')}, Barrierefreiheit ${s('accessibility')}, Best Practices ${s('best-practices')}, SEO ${s('seo')}`);
}
await chrome.kill();

// Rot, wenn es Verstöße gibt oder die Barrierefreiheit unter 95 fällt.
process.exit(Object.keys(violations).length > 0 || lowA11y ? 1 : 0);
