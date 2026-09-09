/*
 * Recoit le diagnostic rempli sur la page et envoie deux mails depuis
 * benjamin@bl-connect.fr : le rapport au prospect, la fiche complete a Benjamin.
 *
 * Variables d'environnement attendues sur Netlify :
 *   BL_SMTP_PASS   mot de passe d'appareil Infomaniak (jamais dans le depot)
 *   BL_SMTP_USER   optionnel, defaut benjamin@bl-connect.fr
 *   BL_SMTP_HOST   optionnel, defaut mail.infomaniak.com
 *   BL_SMTP_PORT   optionnel, defaut 587 (STARTTLS)
 */
const nodemailer = require("nodemailer");

const EXPEDITEUR = process.env.BL_SMTP_USER || "benjamin@bl-connect.fr";
const HOTE = process.env.BL_SMTP_HOST || "mail.infomaniak.com";
const PORT = Number(process.env.BL_SMTP_PORT || 587);
const RDV = "https://calendly.com/benjcailhol/rdv-decouverte";
// Netlify pose URL a l adresse principale du site. Sans elle, pas de lien de reprise.
const SITE = String(process.env.URL || process.env.DEPLOY_PRIME_URL || "").replace(/\/+$/, "");

const MARINE = "#011734";
const CYAN = "#059fd9";
const CYAN_CLAIR = "#ecf8fd";
const GRIS = "#5e6e80";
const LIGNE = "#dde4ec";
const POLICE = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif";

/* Tout ce qui vient du formulaire traverse ceci avant d'entrer dans le HTML. */
function e(v) {
  return String(v == null ? "" : v)
    .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
}

function nf(n) {
  const x = Math.round(Number(n) || 0);
  return x.toLocaleString("fr-FR").replace(/ | /g, " ");
}

function ligneTache(t) {
  return `<tr>
    <td style="padding:9px 0;border-bottom:1px solid #eef2f6;font-size:14px;color:${MARINE}">${e(t.label)}</td>
    <td style="padding:9px 0;border-bottom:1px solid #eef2f6;font-size:13px;color:${GRIS};text-align:right;white-space:nowrap">${e(t.freq)} / sem.</td>
    <td style="padding:9px 0;border-bottom:1px solid #eef2f6;font-size:13px;color:${GRIS};text-align:right;white-space:nowrap">${e(t.duree)}</td>
    <td style="padding:9px 0;border-bottom:1px solid #eef2f6;font-size:14px;font-weight:700;color:${MARINE};text-align:right;white-space:nowrap">${nf(t.heures)} h</td>
    <td style="padding:9px 0;border-bottom:1px solid #eef2f6;font-size:14px;font-weight:700;color:${MARINE};text-align:right;white-space:nowrap">${nf(t.eurLo)} &euro;</td>
  </tr>`;
}

function blocPiste(p, i) {
  const montant = p.eur ? `Jusqu&rsquo;&agrave; ${nf(p.eur)} &euro; par an &middot; ` : "";
  return `<tr><td style="padding:14px 0;border-bottom:1px solid #eef2f6">
    <div style="font-size:11px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:${CYAN}">${e(p.famille)}</div>
    <div style="font-size:16px;font-weight:700;color:${MARINE};margin-top:3px">${i + 1}. ${e(p.titre)}</div>
    ${p.texte ? `<div style="font-size:14px;color:#2c3e54;margin-top:5px;line-height:1.5">${e(p.texte)}</div>` : ""}
    <div style="font-size:13px;font-weight:700;color:${CYAN};margin-top:6px">${montant}${e(p.effort)}</div>
  </td></tr>`;
}

function rapport(d, pourBenjamin) {
  const dt = new Date().toLocaleDateString("fr-FR", { day: "numeric", month: "long", year: "numeric" });
  const nbT = (d.taches || []).length;
  const plafond = d.plafonne
    ? "Vos r&eacute;ponses d&eacute;passent le temps que l&rsquo;effectif indiqu&eacute; peut y consacrer : nous avons retenu ce maximum, pas le total d&eacute;clar&eacute;. "
    : "";

  const entete = pourBenjamin
    ? `<div style="background:${CYAN_CLAIR};border:1px solid #b8e6f7;border-radius:10px;padding:16px;margin-bottom:22px">
        <div style="font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:${CYAN}">Nouveau diagnostic</div>
        <div style="font-size:15px;color:${MARINE};margin-top:6px;line-height:1.7">
          <b>${e(d.prenom)}</b><br>
          <a href="mailto:${e(d.email)}" style="color:${MARINE}">${e(d.email)}</a><br>
          ${e(d.activite)}${d.dept ? " &middot; " + e(d.dept) : ""}
        </div>
      </div>`
    : `<div style="font-size:15px;color:#2c3e54;line-height:1.6;margin-bottom:22px">
        Bonjour ${e(d.prenom)},<br><br>
        Voici le diagnostic que vous venez de remplir. Tout ce qui suit vient de vos seules
        r&eacute;ponses : c&rsquo;est ce que ces t&acirc;ches vous co&ucirc;tent aujourd&rsquo;hui en temps de travail.
      </div>`;

  const reponses = pourBenjamin && d.reponses
    ? `<h2 style="font-size:13px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:${CYAN};margin:26px 0 10px">Ses r&eacute;ponses</h2>
       <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#2c3e54;line-height:1.7">
         ${Object.keys(d.reponses).map(k => `<tr>
           <td style="padding:2px 12px 2px 0;color:${GRIS};white-space:nowrap;vertical-align:top">${e(k)}</td>
           <td style="padding:2px 0;color:${MARINE}">${e(d.reponses[k])}</td></tr>`).join("")}
       </table>`
    : "";

  const reprise = d.lien
    ? `<div style="margin-top:24px;padding:16px 18px;border:1px solid ${LIGNE};border-radius:10px">
        <div style="font-size:14px;color:#2c3e54;line-height:1.55">
          <a href="${d.lien}" style="color:${CYAN};font-weight:700;text-decoration:none">Revoir ce diagnostic en ligne</a><br>
          La page rouvre votre r&eacute;sultat tel quel, et le bouton d&rsquo;enregistrement en PDF s&rsquo;y trouve.
        </div>
      </div>`
    : "";

  const cta = pourBenjamin
    ? reprise
    : `${reprise}
      <div style="background:${CYAN_CLAIR};border:1px solid #b8e6f7;border-radius:10px;padding:20px;margin-top:26px">
        <div style="font-size:16px;font-weight:700;color:${MARINE}">La suite tient en 30 minutes</div>
        <div style="font-size:14px;color:#2c3e54;margin-top:6px;line-height:1.55">
          Un &eacute;change d&eacute;couverte gratuit et sans engagement. Si l&rsquo;IA n&rsquo;est pas la bonne r&eacute;ponse
          pour ce que vous avez d&eacute;crit, nous vous le disons.
        </div>
        <div style="margin-top:14px">
          <a href="${RDV}" style="display:inline-block;background:${MARINE};color:#fff;text-decoration:none;font-size:15px;font-weight:700;padding:13px 22px;border-radius:8px">R&eacute;server mon &eacute;change d&eacute;couverte</a>
        </div>
      </div>`;

  return `<!doctype html><html lang="fr"><body style="margin:0;padding:0;background:#f4f7fa">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fa;padding:24px 12px">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:14px;padding:30px;font-family:${POLICE}">

<tr><td style="padding-bottom:18px;border-bottom:2px solid ${MARINE}">
  <div style="font-size:21px;font-weight:800;color:${MARINE};letter-spacing:-.02em">Diagnostic IA</div>
  <div style="font-size:13px;color:${GRIS};margin-top:3px">${e(d.activite)} &middot; ${dt} &middot; B&amp;L Connect</div>
</td></tr>

<tr><td style="padding-top:22px">${entete}</td></tr>

<tr><td>
  <div style="background:${MARINE};border-radius:12px;padding:24px;color:#fff">
    <div style="font-size:38px;font-weight:800;letter-spacing:-.03em;line-height:1">${nf(d.eurLo)} &euro;</div>
    <div style="font-size:14px;color:#b8e6f7;margin-top:6px">
      &agrave; r&eacute;cup&eacute;rer par an, au maximum, sur ${nbT === 1 ? "cette t&acirc;che" : "ces " + nbT + " t&acirc;ches"}
    </div>
  </div>
  <div style="font-size:12.5px;color:${GRIS};line-height:1.5;margin-top:10px">
    Soit ${nf(d.heures)} heures de travail par an, au co&ucirc;t horaire indiqu&eacute; (${e(d.taux)}), sur
    ${e(d.semaines)} semaines travaill&eacute;es. ${plafond}C&rsquo;est le maximum r&eacute;cup&eacute;rable, jamais un r&eacute;sultat
    garanti : il reste toujours la validation et les cas particuliers.
  </div>
</td></tr>

<tr><td>
  <h2 style="font-size:13px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:${CYAN};margin:26px 0 8px">Ce que ${pourBenjamin ? "cette personne a" : "vous nous avez"} indiqu&eacute;</h2>
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <th style="text-align:left;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:${GRIS};padding-bottom:6px;border-bottom:1px solid ${LIGNE}">T&acirc;che</th>
      <th style="text-align:right;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:${GRIS};padding-bottom:6px;border-bottom:1px solid ${LIGNE}">Fr&eacute;q.</th>
      <th style="text-align:right;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:${GRIS};padding-bottom:6px;border-bottom:1px solid ${LIGNE}">Dur&eacute;e</th>
      <th style="text-align:right;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:${GRIS};padding-bottom:6px;border-bottom:1px solid ${LIGNE}">Par an</th>
      <th style="text-align:right;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:${GRIS};padding-bottom:6px;border-bottom:1px solid ${LIGNE}">En euros</th>
    </tr>
    ${(d.taches || []).map(ligneTache).join("")}
  </table>
</td></tr>

<tr><td>
  <h2 style="font-size:13px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:${CYAN};margin:26px 0 4px">${pourBenjamin ? "Ce que la page a propos&eacute;" : "Ce que nous vous proposons"}</h2>
  ${d.verdict ? `<div style="font-size:15px;font-weight:700;color:${MARINE};margin-bottom:2px">${e(d.verdict.titre)}</div>
  <div style="font-size:14px;color:#2c3e54;line-height:1.5;margin-bottom:6px">${e(d.verdict.texte)}</div>` : ""}
  <table width="100%" cellpadding="0" cellspacing="0">${(d.pistes || []).map(blocPiste).join("")}</table>
</td></tr>

<tr><td>${reponses}${cta}</td></tr>

<tr><td style="padding-top:24px;margin-top:20px;border-top:1px solid ${LIGNE}">
  <div style="font-size:12px;color:${GRIS};line-height:1.55">
    Ce rapport reprend uniquement les r&eacute;ponses donn&eacute;es le ${dt}. Les montants sont calcul&eacute;s sur ces
    d&eacute;clarations et ne constituent pas une promesse de r&eacute;sultat. Le prix des solutions se chiffre
    s&eacute;par&eacute;ment, p&eacute;rim&egrave;tre par p&eacute;rim&egrave;tre.<br><br>
    B&amp;L Connect &middot; ${EXPEDITEUR} &middot; ${RDV}
  </div>
</td></tr>

</table>
</td></tr></table>
</body></html>`;
}

function versionTexte(d) {
  const L = [];
  L.push("DIAGNOSTIC IA - B&L CONNECT");
  L.push("");
  L.push(d.activite);
  L.push(nf(d.eurLo) + " EUR par an a recuperer au maximum, soit " + nf(d.heures) + " heures.");
  L.push("");
  (d.taches || []).forEach(t => {
    L.push("- " + t.label + " : " + t.freq + " fois/semaine, " + t.duree +
           " -> " + nf(t.heures) + " h/an, " + nf(t.eurLo) + " EUR");
  });
  L.push("");
  L.push("PISTES");
  (d.pistes || []).forEach((p, i) => {
    L.push((i + 1) + ". [" + p.famille + "] " + p.titre + (p.eur ? " (jusqu a " + nf(p.eur) + " EUR/an)" : ""));
    if (p.texte) L.push("   " + p.texte);
  });
  L.push("");
  if (d.lien) {
    L.push("Revoir ce diagnostic en ligne, et l enregistrer en PDF :");
    L.push(d.lien);
    L.push("");
  }
  L.push("Echange decouverte, 30 minutes, gratuit : " + RDV);
  return L.join("\n");
}

exports.handler = async function (event) {
  if (event.httpMethod === "OPTIONS") return { statusCode: 204, headers: cors(), body: "" };
  if (event.httpMethod !== "POST") return { statusCode: 405, headers: cors(), body: "Methode non autorisee" };

  let d;
  try {
    d = JSON.parse(event.body || "{}");
  } catch (err) {
    return reponse(400, { ok: false, erreur: "corps illisible" });
  }

  // Piege a robots : le champ est invisible pour un humain.
  if (d.societe) return reponse(200, { ok: true });

  // Ping de mesure : un ecran atteint, rien d'autre. Se lit dans les logs de la
  // fonction (Netlify > Logs > Functions), en filtrant sur DIAG-ETAPE.
  if (d.etape && !d.email) {
    console.log("DIAG-ETAPE " + JSON.stringify({
      etape: String(d.etape).slice(0, 20),
      sid: String(d.sid || "").slice(0, 12),
      metier: d.metier ? String(d.metier).slice(0, 20) : null
    }));
    return reponse(200, { ok: true });
  }

  const email = String(d.email || "").trim();
  const prenom = String(d.prenom || "").trim().slice(0, 60);
  if (!prenom || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
    return reponse(400, { ok: false, erreur: "prenom ou email manquant" });
  }
  d.email = email;
  d.prenom = prenom;
  d.activite = String(d.activite || "Activite non precisee").slice(0, 120);

  // Le lien ne vient jamais du client : on ne garde que le code, filtre, et on
  // reconstruit l adresse a partir de celle du site.
  const code = String(d.code || "").replace(/[^A-Za-z0-9_-]/g, "").slice(0, 3000);
  d.lien = SITE && code ? SITE + "/?d=" + code : null;

  if (!process.env.BL_SMTP_PASS) {
    console.error("BL_SMTP_PASS absente des variables Netlify");
    return reponse(500, { ok: false, erreur: "envoi indisponible" });
  }

  const envoi = nodemailer.createTransport({
    host: HOTE,
    port: PORT,
    secure: PORT === 465,
    auth: { user: EXPEDITEUR, pass: process.env.BL_SMTP_PASS }
  });

  const montant = nf(d.eurLo) + " EUR";

  const pourBenjamin = envoi.sendMail({
    from: { name: "Diagnostic B&L Connect", address: EXPEDITEUR },
    to: EXPEDITEUR,
    replyTo: `${prenom} <${email}>`,
    subject: `Diagnostic - ${d.activite} - ${prenom} (${montant})`,
    html: rapport(d, true),
    text: versionTexte(d) + "\n\n" + prenom + " - " + email
  });

  const pourLeProspect = envoi.sendMail({
    from: { name: "Benjamin Cailhol", address: EXPEDITEUR },
    to: `${prenom} <${email}>`,
    replyTo: EXPEDITEUR,
    subject: `Votre diagnostic IA : ${montant} par an`,
    html: rapport(d, false),
    text: versionTexte(d)
  });

  const [benjamin, prospect] = await Promise.allSettled([pourBenjamin, pourLeProspect]);

  if (benjamin.status === "rejected") console.error("mail Benjamin :", benjamin.reason && benjamin.reason.message);
  if (prospect.status === "rejected") console.error("mail prospect :", prospect.reason && prospect.reason.message);

  // Le lead est capte des que Benjamin a recu la fiche : la page peut confirmer.
  if (benjamin.status === "fulfilled") {
    return reponse(200, { ok: true, prospect: prospect.status === "fulfilled" });
  }
  return reponse(502, { ok: false, erreur: "envoi impossible" });
};

function cors() {
  return {
    "Access-Control-Allow-Origin": "*",
    "Access-Control-Allow-Headers": "Content-Type",
    "Access-Control-Allow-Methods": "POST, OPTIONS"
  };
}

function reponse(code, corps) {
  return {
    statusCode: code,
    headers: Object.assign({ "Content-Type": "application/json" }, cors()),
    body: JSON.stringify(corps)
  };
}
