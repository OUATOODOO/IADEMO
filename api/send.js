// Atelier du Guidon — Vercel Serverless Function
// POST /api/send — Sauvegarde RDV dans Supabase + notification Mailjet

const SUPABASE_URL      = process.env.SUPABASE_URL;
const SUPABASE_ANON_KEY = process.env.SUPABASE_ANON_KEY;
const MJ_APIKEY_PUBLIC  = process.env.MJ_APIKEY_PUBLIC;
const MJ_APIKEY_PRIVATE = process.env.MJ_APIKEY_PRIVATE;
const MJ_FROM_EMAIL     = process.env.MJ_FROM_EMAIL;
const MJ_FROM_NAME      = process.env.MJ_FROM_NAME;
const MJ_TO_EMAIL       = process.env.MJ_TO_EMAIL;

function clean(val = '') {
  return String(val).trim().replace(/<[^>]*>/g, '').slice(0, 500);
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

export default async function handler(req, res) {
  res.setHeader('Content-Type', 'application/json; charset=UTF-8');

  if (req.method !== 'POST') {
    return res.status(405).json({ ok: false, error: 'Method Not Allowed' });
  }

  // Supporter FormData (multipart/form-data ou application/x-www-form-urlencoded)
  // ET JSON selon ce que le client envoie
  let body = {};
  const ct = req.headers['content-type'] ?? '';
  if (ct.includes('application/json')) {
    body = req.body ?? {};
  } else {
    // Vercel parse automatiquement urlencoded dans req.body
    body = req.body ?? {};
    // Si req.body est vide (FormData multipart), lire le raw body
    if (!body.nom) {
      const raw = await new Promise((resolve) => {
        let data = '';
        req.on('data', chunk => { data += chunk; });
        req.on('end', () => resolve(data));
      });
      raw.split('&').forEach(pair => {
        const [k, v] = pair.split('=').map(decodeURIComponent);
        if (k) body[k] = v ?? '';
      });
    }
  }

  const nom     = clean(body.nom);
  const tel     = clean(body.tel);
  const email   = clean(body.email);
  const service = clean(body.service);
  const message = clean(body.message);

  if (!nom || !tel || !email || !service) {
    return res.status(400).json({ ok: false, error: 'Champs obligatoires manquants.' });
  }
  if (!isValidEmail(email)) {
    return res.status(400).json({ ok: false, error: 'Adresse email invalide.' });
  }

  // ── 1. Sauvegarde Supabase ─────────────────────────────────
  const sbRes = await fetch(`${SUPABASE_URL}/rest/v1/rdv_demandes`, {
    method: 'POST',
    headers: {
      'Content-Type':  'application/json',
      'apikey':        SUPABASE_ANON_KEY,
      'Authorization': `Bearer ${SUPABASE_ANON_KEY}`,
      'Prefer':        'return=minimal',
    },
    body: JSON.stringify({ nom, tel, email, service, message, statut: 'nouveau' }),
  });

  if (!sbRes.ok) {
    return res.status(500).json({ ok: false, error: 'Erreur base de données. Veuillez nous appeler directement.' });
  }

  // ── 2. Notification Mailjet (best-effort) ─────────────────
  const mjBody = JSON.stringify({
    Messages: [{
      From:     { Email: MJ_FROM_EMAIL, Name: MJ_FROM_NAME },
      To:       [{ Email: MJ_TO_EMAIL }],
      ReplyTo:  { Email: email, Name: nom },
      Subject:  `[RDV] Demande de rendez-vous — ${service}`,
      TextPart: [
        'Nouvelle demande de rendez-vous :',
        '',
        `Nom       : ${nom}`,
        `Téléphone : ${tel}`,
        `Email     : ${email}`,
        `Service   : ${service}`,
        `Message   :\n${message}`,
        '',
        '---',
        'Envoyé depuis le site Atelier du Guidon',
        'Voir tous les RDV : https://supabase.com/dashboard/project/oozngkpfjdvmkounialr/editor',
      ].join('\n'),
    }],
  });

  const mjAuth = Buffer.from(`${MJ_APIKEY_PUBLIC}:${MJ_APIKEY_PRIVATE}`).toString('base64');
  try {
    const mjRes = await fetch('https://api.mailjet.com/v3.1/send', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Authorization': `Basic ${mjAuth}` },
      body: mjBody,
    });
    const mjJson = await mjRes.json();
    if (!mjRes.ok) {
      console.error('[Mailjet] HTTP', mjRes.status, JSON.stringify(mjJson));
    } else {
      console.log('[Mailjet] OK', mjJson?.Messages?.[0]?.Status);
    }
  } catch (err) {
    console.error('[Mailjet] fetch error:', err.message);
  }

  return res.status(200).json({ ok: true });
}
