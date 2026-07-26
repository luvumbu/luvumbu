/**
 * Récupère le détail de chaque compétition : épreuves par catégorie, horaire,
 * adresse exacte du stade, période d'inscription.
 *
 * Ces informations n'existent pas dans le calendrier : il faut ouvrir la fiche
 * de chaque compétition. Le script réutilise le profil Chrome du scraper
 * principal (cookies Cloudflare déjà valides) et interroge les fiches depuis
 * le contexte de la page, avec une pause entre chaque appel.
 *
 * Usage :
 *   node details.js                 toutes les compétitions de data/competitions.json
 *   node details.js --limit=5       les 5 premières (mise au point)
 *   node details.js --only=44492    une compétition précise
 *   node details.js --debug         conserve le HTML d'une fiche dans data/raw/
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

function parseArgs(argv) {
    const opts = { headless: false, debug: false, limit: 0, only: null, ids: null, delay: 350, timeout: 120000 };
    for (const arg of argv.slice(2)) {
        if (arg === '--headless') opts.headless = true;
        else if (arg === '--debug') opts.debug = true;
        else if (arg.startsWith('--limit=')) opts.limit = Number(arg.slice(8));
        else if (arg.startsWith('--only=')) opts.only = arg.slice(7);
        else if (arg.startsWith('--ids=')) opts.ids = arg.slice(6).split(/[,\s]+/).filter(Boolean);
        else if (arg.startsWith('--delay=')) opts.delay = Number(arg.slice(8));
    }
    return opts;
}

// ---------------------------------------------------------------------------
// Analyse d'une fiche — exécutée dans la page (DOMParser disponible).
// ---------------------------------------------------------------------------

/* eslint-disable no-undef */
function parseDetailHtml(html) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const clean = (node) => (node?.textContent || '').replace(/\s+/g, ' ').trim();

    // Le bloc « Info » est le tableau qui porte la date. On s'y limite : les
    // mêmes icônes (fa-home, fa-clock-o, fa-users) existent aussi dans le menu
    // du site et une recherche globale ramènerait les mauvaises cellules.
    const infoTable =
        doc.querySelector('i.fa-calendar-o')?.closest('table') ||
        doc.querySelector('table.small.top-heading');

    /** Cellule du bloc Info portant l'icône donnée (fa-calendar-o, fa-home…). */
    const rowFor = (iconClass) => {
        const icon = infoTable?.querySelector(`i.${iconClass}`);
        return icon ? icon.closest('td') : null;
    };

    const detail = {
        date_text: null,
        time_text: null,
        start_time: null,
        end_time: null,
        club_city: null,
        address: null,
        maps_url: null,
        email: null,
        participants: null,
        conditions: null,
        registration_text: null,
        registration_from: null,
        registration_to: null,
        registration_url: null,
        entrants_url: null,
        schedule_url: null,
        categories: [],
        schedule: [],
    };

    // --- date et horaire ----------------------------------------------------
    const dateCell = rowFor('fa-calendar-o') || rowFor('fa-calendar');
    if (dateCell) detail.date_text = clean(dateCell);

    const timeCell = rowFor('fa-clock-o');
    if (timeCell) {
        detail.time_text = clean(timeCell);
        const times = detail.time_text.match(/(\d{1,2}:\d{2})/g) || [];
        if (times[0]) detail.start_time = times[0];
        if (times[1]) detail.end_time = times[1];
    }

    // --- lieu ---------------------------------------------------------------
    const clubCell = rowFor('fa-home');
    if (clubCell) detail.club_city = clean(clubCell);

    const addressCell = rowFor('fa-map-marker');
    if (addressCell) {
        detail.address = clean(addressCell);
        const link = addressCell.querySelector('a[href*="maps."]');
        if (link) detail.maps_url = link.href;
    }

    const mailCell = rowFor('fa-envelope-o');
    if (mailCell) {
        const mail = mailCell.querySelector('a[href^="mailto:"]');
        if (mail) detail.email = mail.getAttribute('href').replace(/^mailto:/, '').split('?')[0];
    }

    const peopleCell = rowFor('fa-users');
    if (peopleCell) {
        const match = clean(peopleCell).match(/(\d+)/);
        if (match) detail.participants = Number(match[1]);
    }

    // --- conditions (Outdoor / chronométrage / reconnaissance fédérale) -----
    for (const td of infoTable?.querySelectorAll('td[colspan]') || []) {
        const text = clean(td);
        if (/^(Indoor|Outdoor)\b/i.test(text)) {
            detail.conditions = text;
            break;
        }
    }

    // --- période d'inscription ---------------------------------------------
    for (const td of infoTable?.querySelectorAll('td[colspan]') || []) {
        const text = clean(td);
        if (/s.inscrire|inschrijven|register/i.test(text)) {
            detail.registration_text = text;
            const dates = text.match(/(\d{2})-(\d{2})-(\d{4})/g) || [];
            const toIso = (d) => {
                const [dd, mm, yyyy] = d.split('-');
                return `${yyyy}-${mm}-${dd}`;
            };
            if (dates[0]) detail.registration_from = toIso(dates[0]);
            if (dates[1]) detail.registration_to = toIso(dates[1]);
            break;
        }
    }

    // --- lien d'inscription -------------------------------------------------
    // Le site ne publie ce lien que tant que les inscriptions sont ouvertes :
    // sa présence est donc le signal fiable, plus que la date affichée.
    const absolute = (href) =>
        !href ? null : href.startsWith('http') ? href : `https://www.athletisme.app${href}`;

    for (const a of doc.querySelectorAll('a[href]')) {
        const href = a.getAttribute('href') || '';
        if (!detail.registration_url && /\/wedstrijd\/inschrijven\/\d+/.test(href)) {
            detail.registration_url = absolute(href);
        }
        if (!detail.entrants_url && /\/wedstrijd\/atleten\/\d+/.test(href)) {
            detail.entrants_url = absolute(href);
        }
        // Horaire complet : la fiche n'en montre que les premières lignes.
        if (!detail.schedule_url && /\/wedstrijd\/chronoloog\/\d+/.test(href)) {
            detail.schedule_url = absolute(href);
        }
    }

    // --- épreuves par catégorie --------------------------------------------
    // Structure : 1re cellule = catégories, puis des paires
    // (td.onderdeel-groep = intitulé du groupe, td.onderdelen = épreuves).
    const packet = doc.querySelector('table.categorieenonderdelenpakket');
    if (packet) {
        for (const tr of packet.querySelectorAll('tr')) {
            if (tr.querySelector('th')) continue; // ligne d'en-tête

            const cells = [...tr.querySelectorAll('td')];
            if (cells.length === 0) continue;

            const categoryCell = cells.find((td) => !td.classList.contains('onderdelen') && !td.classList.contains('onderdeel-groep'));
            const categories = categoryCell
                ? [...categoryCell.querySelectorAll('span.tipped')].map((s) => ({
                      code: clean(s),
                      label: s.getAttribute('title') || null,
                  }))
                : [];

            const groups = [];
            cells.forEach((td, index) => {
                if (!td.classList.contains('onderdelen')) return;
                const previous = cells[index - 1];
                const groupName =
                    previous && previous.classList.contains('onderdeel-groep') ? clean(previous).replace(/:$/, '') : null;
                const events = [...td.querySelectorAll('span.tipped')].map((s) => ({
                    short: clean(s),
                    label: s.getAttribute('title') || null,
                }));
                groups.push({
                    group: groupName,
                    events: events.length ? events : clean(td).split(/\s*,\s*/).filter(Boolean).map((e) => ({ short: e, label: null })),
                });
            });

            if (categories.length || groups.length) {
                detail.categories.push({ categories, groups });
            }
        }
    }

    // --- chronologie (heure → épreuve) --------------------------------------
    for (const table of doc.querySelectorAll('table.small.top-heading')) {
        const rows = [...table.querySelectorAll('tr')];
        const timed = rows.filter((tr) => {
            const first = tr.querySelector('td');
            return first && /^\d{1,2}:\d{2}/.test(clean(first));
        });
        if (timed.length >= 2) {
            detail.schedule = timed.map((tr) => {
                const cells = [...tr.querySelectorAll('td')];
                return { time: clean(cells[0]), event: clean(cells[1]) };
            });
            break;
        }
    }

    return detail;
}
/* eslint-enable no-undef */

// ---------------------------------------------------------------------------

async function main() {
    const opts = parseArgs(process.argv);

    const sourceFile = path.join(DATA_DIR, 'competitions.json');
    let payload;
    try {
        payload = JSON.parse(await fs.readFile(sourceFile, 'utf8'));
    } catch (error) {
        console.error(`✗ ${sourceFile} introuvable. Lancez d'abord : node scrape.js`);
        process.exit(1);
    }

    let targets = payload.competitions.filter((c) => c.external_id);
    if (opts.only) targets = targets.filter((c) => String(c.external_id) === String(opts.only));
    if (opts.ids) targets = targets.filter((c) => opts.ids.includes(String(c.external_id)));
    if (opts.limit > 0) targets = targets.slice(0, opts.limit);

    if (targets.length === 0) {
        console.error('✗ Aucune compétition à traiter.');
        process.exit(1);
    }

    console.log(`→ ${targets.length} fiche(s) à consulter`);

    const context = await chromium.launchPersistentContext(PROFILE_DIR, {
        headless: opts.headless,
        channel: 'chrome',
        viewport: { width: 1280, height: 900 },
        locale: 'fr-BE',
        timezoneId: 'Europe/Brussels',
        args: ['--disable-blink-features=AutomationControlled'],
    });

    const page = context.pages()[0] || (await context.newPage());
    await page.goto(`${ORIGIN}/wedstrijden/`, { waitUntil: 'domcontentloaded', timeout: opts.timeout });

    if ((await page.title()).toLowerCase().includes('checking your browser')) {
        console.log('→ Challenge Cloudflare : laissez la fenêtre ouverte…');
        await page
            .waitForFunction(() => !document.title.toLowerCase().includes('checking your browser'), null, {
                timeout: opts.timeout,
            })
            .catch(() => {});
    }

    const details = [];
    let failed = 0;

    for (const [index, competition] of targets.entries()) {
        // Toutes les compétitions n'exposent pas /wedstrijd/main/ : on retombe
        // sur l'URL que le calendrier a donnée (chronoloog, uitslagen…).
        const candidates = [`${ORIGIN}/wedstrijd/main/${competition.external_id}/`];
        if (competition.url && !candidates.includes(competition.url)) {
            candidates.push(competition.url);
        }

        let url = null;
        let response = null;

        for (const candidate of candidates) {
            // Le site renvoie 429 quand on va trop vite : on attend de plus en
            // plus longtemps plutôt que d'abandonner la fiche.
            for (let attempt = 0; attempt < 4; attempt++) {
                const wait = attempt === 0 ? opts.delay : 4000 * attempt;
                response = await page.evaluate(
                    async ({ u, delay }) => {
                        await new Promise((resolve) => setTimeout(resolve, delay));
                        const r = await fetch(u, { credentials: 'include' });
                        return { status: r.status, body: await r.text() };
                    },
                    { u: candidate, delay: wait }
                );
                if (response.status !== 429) break;
            }
            if (response.status === 200) {
                url = candidate;
                break;
            }
        }

        if (url === null) {
            failed++;
            console.log(`\n  [${index + 1}/${targets.length}] ${competition.external_id} → HTTP ${response?.status}`);
            continue;
        }

        if (opts.debug && index === 0) {
            await fs.mkdir(RAW_DIR, { recursive: true });
            await fs.writeFile(path.join(RAW_DIR, `detail-${competition.external_id}.html`), response.body, 'utf8');
        }

        const detail = await page.evaluate(
            ({ html, fn }) => {
                // eslint-disable-next-line no-new-func
                const parse = new Function(`return (${fn})`)();
                return parse(html);
            },
            { html: response.body, fn: parseDetailHtml.toString() }
        );

        const eventCount = detail.categories.reduce(
            (total, block) => total + block.groups.reduce((n, g) => n + g.events.length, 0),
            0
        );

        details.push({ external_id: competition.external_id, source_url: url, ...detail });

        process.stdout.write(
            `\r  [${index + 1}/${targets.length}] ${competition.title.slice(0, 34).padEnd(34)} ` +
                `${eventCount} épreuve(s)   `
        );
    }

    await context.close();

    const outFile = path.join(DATA_DIR, 'details.json');
    await fs.writeFile(
        outFile,
        JSON.stringify({ scraped_at: new Date().toISOString(), count: details.length, details }, null, 2),
        'utf8'
    );

    const withEvents = details.filter((d) => d.categories.length > 0).length;
    const withSchedule = details.filter((d) => d.schedule.length > 0).length;

    console.log('');
    console.log('');
    console.log(`✓ ${details.length} fiche(s) → ${outFile}`);
    console.log(`✓ ${withEvents} avec épreuves détaillées, ${withSchedule} avec horaire`);
    if (failed) console.log(`⚠ ${failed} fiche(s) inaccessibles`);
    console.log('');
    console.log('Étape suivante : php bin/import-details.php');
}

main().catch((error) => {
    console.error('✗ Échec :', error);
    process.exit(1);
});
