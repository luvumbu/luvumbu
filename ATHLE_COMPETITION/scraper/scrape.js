/**
 * Scraper athletisme.app
 * ----------------------
 * Le site est protégé par un challenge Cloudflare Turnstile : toute requête
 * HTTP directe (curl, file_get_contents, fetch serveur) reçoit la page
 * « Checking your browser » au lieu des données. On pilote donc un vrai Chrome.
 *
 * Une fois la page ouverte, on n'aspire pas le DOM : on interroge directement
 * l'endpoint que l'application utilise elle-même —
 *   feeder.php?page=search&do=events&country=..&event_soort[]=..&startDate=..&endDate=..
 * — depuis le contexte de la page (donc avec les cookies Cloudflare valides).
 * Il renvoie le fragment HTML du calendrier, que l'on analyse avec DOMParser.
 *
 * Les passes « indoor » et « outdoor » sont faites séparément : c'est le seul
 * moyen de connaître le type de chaque compétition, l'information n'étant pas
 * présente dans les lignes du calendrier.
 *
 * Persistance du challenge : le profil de navigateur est conservé dans
 * .browser-profile, donc le cookie cf_clearance survit d'une exécution à l'autre.
 *
 * Usage :
 *   node scrape.js                              12 mois à venir, Belgique
 *   node scrape.js --from=2026-01-01 --to=2026-12-31
 *   node scrape.js --country=FR
 *   node scrape.js --months=24                  fenêtre à partir d'aujourd'hui
 *   node scrape.js --headless                   après un premier run réussi
 *   node scrape.js --debug                      conserve le HTML brut du feeder
 */

import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const DATA_DIR = path.resolve(__dirname, '..', 'data');
const RAW_DIR = path.join(DATA_DIR, 'raw');
const PROFILE_DIR = path.join(__dirname, '.browser-profile');
const ORIGIN = 'https://www.athletisme.app';

// ---------------------------------------------------------------------------
// Arguments
// ---------------------------------------------------------------------------

function parseArgs(argv) {
  const opts = {
    headless: false,
    debug: false,
    country: 'BE',
    from: null,
    to: null,
    months: 12,
    timeout: 120000,
  };
  for (const arg of argv.slice(2)) {
    if (arg === '--headless') opts.headless = true;
    else if (arg === '--debug') opts.debug = true;
    else if (arg.startsWith('--country=')) opts.country = arg.slice(10).toUpperCase();
    else if (arg.startsWith('--from=')) opts.from = arg.slice(7);
    else if (arg.startsWith('--to=')) opts.to = arg.slice(5);
    else if (arg.startsWith('--months=')) opts.months = Number(arg.slice(9));
    else if (arg.startsWith('--timeout=')) opts.timeout = Number(arg.slice(10));
    else if (arg.startsWith('--')) {
      console.error(`Option inconnue : ${arg}`);
      process.exit(64);
    }
  }

  const today = new Date();
  if (!opts.from) {
    opts.from = today.toISOString().slice(0, 10);
  }
  if (!opts.to) {
    const end = new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth() + opts.months, today.getUTCDate()));
    opts.to = end.toISOString().slice(0, 10);
  }
  return opts;
}

const toEpoch = (isoDate, endOfDay = false) =>
  Math.floor(new Date(`${isoDate}T${endOfDay ? '23:59:59' : '00:00:00'}Z`).getTime() / 1000);

function feederUrl({ country, from, to, environment }) {
  const q = new URLSearchParams();
  q.set('page', 'search');
  q.set('do', 'events');
  q.set('country', country);
  q.set('search', '');
  q.append('event_soort[]', environment);
  q.set('predefinedSearchTemplate', '');
  q.set('startDate', String(toEpoch(from)));
  q.set('endDate', String(toEpoch(to, true)));
  return `${ORIGIN}/feeder.php?${q.toString()}`;
}

// ---------------------------------------------------------------------------
// Analyse du fragment HTML renvoyé par feeder.php
// Exécutée dans la page pour disposer de DOMParser.
// ---------------------------------------------------------------------------

/* eslint-disable no-undef */
function parseFeederHtml(html, environment, country) {
  const MONTHS = {
    janvier: 1, februari: 2, février: 2, fevrier: 2, mars: 3, avril: 4, mai: 5, juin: 6,
    juillet: 7, août: 8, aout: 8, septembre: 9, octobre: 10, novembre: 11, décembre: 12, decembre: 12,
    januari: 1, maart: 3, april: 4, mei: 5, juni: 6, juli: 7, augustus: 8, oktober: 10, december: 12,
  };

  const clean = (node) => (node?.textContent || '').replace(/\s+/g, ' ').trim();

  const doc = new DOMParser().parseFromString(`<table>${html}</table>`, 'text/html');
  const rows = [...doc.querySelectorAll('tr')];

  const out = [];
  let currentMonth = null;
  let currentYear = null;

  for (const tr of rows) {
    // Ligne d'en-tête de mois : « Juillet 2026 »
    const heading = tr.querySelector('h3');
    if (heading) {
      const text = clean(heading).replace(/ /g, ' ');
      const match = text.match(/([A-Za-zÀ-ÿ]+)\s+(\d{4})/);
      if (match) {
        const month = MONTHS[match[1].toLowerCase()];
        if (month) {
          currentMonth = month;
          currentYear = Number(match[2]);
        }
      }
      continue;
    }

    const link = tr.querySelector('td.eventnaam a[href]');
    if (!link) continue;

    // --- date ---------------------------------------------------------------
    const dayText = clean(tr.querySelector('td.datumCol .dagnummer'));
    const fullText = clean(tr.querySelector('td.datumCol .hidden-xs'));
    let startDate = null;

    const day = Number(dayText || (fullText.match(/\b(\d{1,2})\b/) || [])[1]);
    if (day && currentMonth && currentYear) {
      startDate = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    }

    // --- intitulé -----------------------------------------------------------
    // Le lien contient une version desktop (span.hidden-xs) et une version
    // mobile (span.visible-xs-inline) : on prend la première pour ne pas
    // concaténer les deux.
    const title =
      clean(link.querySelector(':scope > span.hidden-xs')) ||
      clean(link.querySelector('.eventnaam')) ||
      clean(link).split(/\s{2,}/)[0];

    // --- club et ville ------------------------------------------------------
    // Format « Nom du club, Ville » ; la ville est après la dernière virgule.
    const cells = [...tr.querySelectorAll('td')];
    const clubCity =
      clean(tr.querySelector('.verenigingnaam')) ||
      clean(cells[2]) ||
      '';

    let organizer = null;
    let city = clubCity;
    const comma = clubCity.lastIndexOf(',');
    if (comma > 0) {
      organizer = clubCity.slice(0, comma).trim();
      city = clubCity.slice(comma + 1).trim();
    }

    // --- divers -------------------------------------------------------------
    const href = link.getAttribute('href') || '';
    const idMatch = href.match(/\/wedstrijd\/[a-z]+\/(\d+)/i);
    const participantsText = cells.map(clean).find((t) => /^\d+$/.test(t) && t.length <= 6);
    const status = clean(tr.querySelector('[class^="modernlabel"], [class*=" modernlabel"]'));

    if (!title && !city) continue;

    out.push({
      external_id: idMatch ? idMatch[1] : null,
      title,
      location: city,
      venue: null,
      country_code: country,
      start_date: startDate,
      end_date: null,
      environment,
      categories: null,
      events: null,
      organizer,
      url: href.startsWith('http') ? href : `https://www.athletisme.app${href}`,
      raw: {
        club_city: clubCity,
        date_text: fullText,
        participants: participantsText ? Number(participantsText) : null,
        status,
      },
    });
  }

  return out;
}
/* eslint-enable no-undef */

// ---------------------------------------------------------------------------

async function main() {
  const opts = parseArgs(process.argv);

  await fs.mkdir(DATA_DIR, { recursive: true });
  await fs.mkdir(RAW_DIR, { recursive: true });

  console.log(`→ Pays    : ${opts.country}`);
  console.log(`→ Période : ${opts.from} → ${opts.to}`);
  console.log(`→ Mode    : ${opts.headless ? 'headless' : 'fenêtre visible'}`);

  const context = await chromium.launchPersistentContext(PROFILE_DIR, {
    headless: opts.headless,
    channel: 'chrome', // Chrome installé sur la machine : moins détectable qu'un Chromium Playwright
    viewport: { width: 1280, height: 900 },
    locale: 'fr-BE',
    timezoneId: 'Europe/Brussels',
    args: ['--disable-blink-features=AutomationControlled'],
  });

  const page = context.pages()[0] || (await context.newPage());

  console.log('→ Ouverture du calendrier…');
  await page.goto(`${ORIGIN}/wedstrijden/`, { waitUntil: 'domcontentloaded', timeout: opts.timeout });

  const challenged = async () => (await page.title()).toLowerCase().includes('checking your browser');

  if (await challenged()) {
    console.log('→ Challenge Cloudflare détecté.');
    if (opts.headless) {
      console.log('  ⚠ En headless il ne se résoudra probablement pas.');
      console.log('  ⚠ Relancez une fois SANS --headless pour enregistrer le cookie dans le profil.');
    } else {
      console.log('  Laissez la fenêtre ouverte ; cochez la case si elle apparaît (2 min max).');
    }
    try {
      await page.waitForFunction(() => !document.title.toLowerCase().includes('checking your browser'), null, {
        timeout: opts.timeout,
      });
      console.log('→ Challenge franchi.');
    } catch {
      console.error('✗ Challenge non franchi dans le délai imparti.');
      await context.close();
      process.exit(2);
    }
  }

  await page.waitForLoadState('networkidle', { timeout: opts.timeout }).catch(() => {});

  // --- Une passe par type d'épreuve ----------------------------------------
  const all = [];

  for (const environment of ['out', 'in']) {
    const url = feederUrl({ ...opts, environment });
    const label = environment === 'in' ? 'indoor' : 'outdoor';
    process.stdout.write(`→ Récupération ${label}… `);

    const response = await page.evaluate(async (u) => {
      const r = await fetch(u, {
        credentials: 'include',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      return { status: r.status, body: await r.text() };
    }, url);

    if (response.status !== 200) {
      console.log(`échec (HTTP ${response.status})`);
      continue;
    }

    if (opts.debug) {
      await fs.writeFile(path.join(RAW_DIR, `feeder-${environment}.html`), response.body, 'utf8');
    }

    const records = await page.evaluate(
      ({ html, environment: env, country, fn }) => {
        // eslint-disable-next-line no-new-func
        const parse = new Function(`return (${fn})`)();
        return parse(html, env, country);
      },
      { html: response.body, environment, country: opts.country, fn: parseFeederHtml.toString() }
    );

    console.log(`${records.length} compétition(s)`);
    all.push(...records);
  }

  await context.close();

  if (all.length === 0) {
    console.error('✗ Aucune compétition récupérée. Relancez avec --debug et examinez data/raw/.');
    process.exit(3);
  }

  // Une compétition peut apparaître dans les deux passes (rare) : on déduplique
  // sur l'identifiant source.
  const byId = new Map();
  for (const record of all) {
    const key = record.external_id || `${record.start_date}|${record.title}|${record.location}`;
    if (!byId.has(key)) byId.set(key, record);
  }
  const competitions = [...byId.values()].sort((a, b) => String(a.start_date).localeCompare(String(b.start_date)));

  const payload = {
    source: 'athletisme.app',
    source_url: `${ORIGIN}/wedstrijden/`,
    scraped_at: new Date().toISOString(),
    country: opts.country,
    period: { from: opts.from, to: opts.to },
    count: competitions.length,
    competitions,
  };

  const outFile = path.join(DATA_DIR, 'competitions.json');
  await fs.writeFile(outFile, JSON.stringify(payload, null, 2), 'utf8');

  const cities = new Set(competitions.map((c) => c.location).filter(Boolean));
  const undated = competitions.filter((c) => !c.start_date).length;

  console.log('');
  console.log(`✓ ${competitions.length} compétition(s) → ${outFile}`);
  console.log(`✓ ${cities.size} ville(s) distincte(s)`);
  if (undated) console.log(`⚠ ${undated} compétition(s) sans date exploitable`);
  console.log('');
  console.log('Étape suivante : php bin/import.php');
}

main().catch((error) => {
  console.error('✗ Échec du scraping :', error);
  process.exit(1);
});
