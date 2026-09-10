<?php
/*
 * Recoit le diagnostic rempli par la page et envoie deux mails depuis la boite
 * B&L Connect : le rapport au visiteur, la fiche complete a Benjamin.
 *
 * Recoit aussi les pings de mesure, qui n'envoient rien et s'ecrivent dans
 * mesures.php (une ligne par ecran atteint).
 *
 * A deposer a cote de index.html. Reglages dans config.php.
 */

// Sortie JSON : aucun avertissement ne doit s'imprimer avant l'en-tete, sinon
// la page ne sait plus lire la reponse. Les erreurs partent dans le log serveur.
ini_set('display_errors', '0');
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/lib/Exception.php';
require __DIR__ . '/lib/PHPMailer.php';
require __DIR__ . '/lib/SMTP.php';

if (!file_exists(__DIR__ . '/config.php')) {
    repondre(500, array('ok' => false, 'erreur' => 'config absente'));
}
require __DIR__ . '/config.php';

const MARINE     = '#011734';
const CYAN       = '#059fd9';
const CYAN_CLAIR = '#ecf8fd';
const GRIS       = '#5e6e80';
const LIGNE      = '#dde4ec';
const POLICE     = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif";

/* ---------------------------------------------------------------- outils */

function repondre($code, $corps)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($corps, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Tout ce qui vient du formulaire passe par ici avant d'entrer dans le HTML. */
function e($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Nombres a la francaise, avec une espace insecable fine comme separateur. */
function nf($n)
{
    return number_format((float) $n, 0, ',', "\xe2\x80\xaf");
}

function champ($tableau, $cle, $defaut = '')
{
    return isset($tableau[$cle]) ? $tableau[$cle] : $defaut;
}

/** Coupe une chaine sans casser les accents, mbstring absent ou pas. */
function couper($texte, $taille)
{
    return function_exists('mb_substr')
        ? mb_substr($texte, 0, $taille, 'UTF-8')
        : substr($texte, 0, $taille);
}

/* ------------------------------------------------------- corps des mails */

function ligneTache($t)
{
    // Deux colonnes seulement : ce que c'est, ce que ca coute. Le detail passe
    // sous le libelle, ou il reste lisible meme sur un ecran de 320 points.
    return '<tr><td style="padding:11px 0;border-bottom:1px solid #eef2f6">'
        . '<table width="100%" cellpadding="0" cellspacing="0"><tr>'
        . '<td style="font-size:15px;font-weight:600;color:' . MARINE . ';line-height:1.35">'
        . e(champ($t, 'label')) . '</td>'
        . '<td style="font-size:15px;font-weight:700;color:' . MARINE . ';text-align:right;'
        . 'white-space:nowrap;vertical-align:top;padding-left:10px">'
        . nf(champ($t, 'eurLo', 0)) . '&nbsp;&euro;</td>'
        . '</tr></table>'
        . '<div style="font-size:13px;color:' . GRIS . ';margin-top:3px">'
        . e(champ($t, 'freq')) . ' fois par semaine &middot; ' . e(champ($t, 'duree'))
        . ' &middot; ' . nf(champ($t, 'heures', 0)) . ' h par an</div>'
        . '</td></tr>';
}

function blocPiste($p, $rang)
{
    $montant = champ($p, 'eur', 0) > 0
        ? 'Jusqu&rsquo;&agrave; ' . nf($p['eur']) . ' &euro; par an &middot; '
        : '';
    $texte = champ($p, 'texte') !== ''
        ? '<div style="font-size:14px;color:#2c3e54;margin-top:5px;line-height:1.5">' . e($p['texte']) . '</div>'
        : '';
    return '<tr><td style="padding:14px 0;border-bottom:1px solid #eef2f6">'
        . '<div style="font-size:11px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:' . CYAN . '">' . e(champ($p, 'famille')) . '</div>'
        . '<div style="font-size:16px;font-weight:700;color:' . MARINE . ';margin-top:3px">' . $rang . '. ' . e(champ($p, 'titre')) . '</div>'
        . $texte
        . '<div style="font-size:13px;font-weight:700;color:' . CYAN . ';margin-top:6px">' . $montant . e(champ($p, 'effort')) . '</div>'
        . '</td></tr>';
}

function moisFrancais($mois)
{
    $noms = array(1 => 'janvier', 'f&eacute;vrier', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'ao&ucirc;t', 'septembre', 'octobre', 'novembre', 'd&eacute;cembre');
    return $noms[(int) $mois];
}

function rapport($d, $pourBenjamin)
{
    $date   = date('j') . ' ' . moisFrancais(date('n')) . ' ' . date('Y');
    $taches = champ($d, 'taches', array());
    $pistes = champ($d, 'pistes', array());
    $nbT    = count($taches);
    $lien   = champ($d, 'lien');

    $plafond = champ($d, 'plafonne')
        ? 'Vos r&eacute;ponses d&eacute;passent le temps que l&rsquo;effectif indiqu&eacute; peut y consacrer&nbsp;: '
          . 'nous avons retenu ce maximum, pas le total d&eacute;clar&eacute;. '
        : '';

    if ($pourBenjamin) {
        $entete = '<div style="background:' . CYAN_CLAIR . ';border:1px solid #b8e6f7;border-radius:10px;padding:16px;margin-bottom:22px">'
            . '<div style="font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:' . CYAN . '">Nouveau diagnostic</div>'
            . '<div style="font-size:15px;color:' . MARINE . ';margin-top:6px;line-height:1.7"><b>' . e($d['prenom']) . '</b><br>'
            . '<a href="mailto:' . e($d['email']) . '" style="color:' . MARINE . '">' . e($d['email']) . '</a><br>'
            . e($d['activite']) . (champ($d, 'dept') ? ' &middot; ' . e($d['dept']) : '')
            . '</div></div>';
    } else {
        $entete = '<div style="font-size:15px;color:#2c3e54;line-height:1.6;margin-bottom:22px">'
            // Le champ porte le nom complet : on ne salue qu'avec le premier mot.
            . 'Bonjour ' . e(strtok($d['prenom'], ' ')) . ',<br><br>'
            . 'Voici le diagnostic que vous venez de remplir. Tout ce qui suit vient de vos seules '
            . 'r&eacute;ponses&nbsp;: c&rsquo;est ce que ces t&acirc;ches vous co&ucirc;tent aujourd&rsquo;hui en temps de travail.'
            . '</div>';
    }

    $reponses = '';
    if ($pourBenjamin && champ($d, 'reponses')) {
        $lignes = '';
        foreach ($d['reponses'] as $cle => $valeur) {
            $lignes .= '<tr><td style="padding:2px 12px 2px 0;color:' . GRIS . ';white-space:nowrap;vertical-align:top">' . e($cle) . '</td>'
                . '<td style="padding:2px 0;color:' . MARINE . '">' . e($valeur) . '</td></tr>';
        }
        $reponses = '<h2 style="font-size:13px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:' . CYAN . ';margin:26px 0 10px">Ses r&eacute;ponses</h2>'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#2c3e54;line-height:1.7">' . $lignes . '</table>';
    }

    $reprise = $lien
        ? '<div style="margin-top:24px;padding:16px 18px;border:1px solid ' . LIGNE . ';border-radius:10px">'
          . '<div style="font-size:14px;color:#2c3e54;line-height:1.55">'
          . '<a href="' . e($lien) . '" style="color:' . CYAN . ';font-weight:700;text-decoration:none">Revoir ce diagnostic en ligne</a><br>'
          . 'La page rouvre votre r&eacute;sultat tel quel, et le bouton d&rsquo;enregistrement en PDF s&rsquo;y trouve.'
          . '</div></div>'
        : '';

    if ($pourBenjamin) {
        $cta = $reprise;
    } else {
        $cta = $reprise
            . '<div style="background:' . CYAN_CLAIR . ';border:1px solid #b8e6f7;border-radius:10px;padding:20px;margin-top:26px">'
            . '<div style="font-size:16px;font-weight:700;color:' . MARINE . '">La suite tient en 30 minutes</div>'
            . '<div style="font-size:14px;color:#2c3e54;margin-top:6px;line-height:1.55">'
            . 'Un &eacute;change d&eacute;couverte gratuit et sans engagement. Si l&rsquo;IA n&rsquo;est pas la bonne '
            . 'r&eacute;ponse pour ce que vous avez d&eacute;crit, nous vous le disons.</div>'
            . '<div style="margin-top:16px"><a class="bouton" href="' . BL_RDV . '" style="display:inline-block;background:' . MARINE
            . ';color:#fff;text-decoration:none;font-size:16px;font-weight:700;padding:15px 24px;border-radius:10px">'
            . 'R&eacute;server mon &eacute;change d&eacute;couverte</a></div></div>';
    }

    $corpsTaches = '';
    foreach ($taches as $t) {
        $corpsTaches .= ligneTache($t);
    }

    $corpsPistes = '';
    foreach ($pistes as $i => $p) {
        $corpsPistes .= blocPiste($p, $i + 1);
    }

    $verdict = '';
    if (champ($d, 'verdict')) {
        $verdict = '<div style="font-size:15px;font-weight:700;color:' . MARINE . ';margin-bottom:2px">' . e(champ($d['verdict'], 'titre')) . '</div>'
            . '<div style="font-size:14px;color:#2c3e54;line-height:1.5;margin-bottom:6px">' . e(champ($d['verdict'], 'texte')) . '</div>';
    }

    return '<!doctype html><html lang="fr"><head>'
        . '<meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<style>'
        . '@media only screen and (max-width:600px){'
        . '  .cadre{padding:20px 16px !important}'
        . '  .marge{padding:12px 6px !important}'
        . '  .gros{font-size:34px !important}'
        . '  .titre{font-size:19px !important}'
        . '  .bouton{display:block !important; text-align:center !important}'
        . '}'
        . '</style></head>'
        . '<body style="margin:0;padding:0;background:#f4f7fa;-webkit-text-size-adjust:100%">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f7fa"><tr>'
        . '<td class="marge" align="center" style="padding:24px 12px">'
        . '<table width="600" cellpadding="0" cellspacing="0" class="cadre" style="max-width:600px;width:100%;background:#fff;border-radius:14px;padding:30px;font-family:' . POLICE . '">'

        . '<tr><td style="padding-bottom:18px;border-bottom:2px solid ' . MARINE . '">'
        . '<div class="titre" style="font-size:22px;font-weight:800;color:' . MARINE . ';letter-spacing:-.02em">Diagnostic IA</div>'
        . '<div style="font-size:13px;color:' . GRIS . ';margin-top:3px">' . e($d['activite']) . ' &middot; ' . $date . ' &middot; B&amp;L Connect</div>'
        . '</td></tr>'

        . '<tr><td style="padding-top:22px">' . $entete . '</td></tr>'

        . '<tr><td><div style="background:' . MARINE . ';border-radius:12px;padding:24px;color:#fff">'
        . '<div class="gros" style="font-size:40px;font-weight:800;letter-spacing:-.03em;line-height:1.05">' . nf(champ($d, 'eurLo', 0)) . ' &euro;</div>'
        . '<div style="font-size:15px;color:#b8e6f7;margin-top:8px;line-height:1.45">&agrave; r&eacute;cup&eacute;rer par an, au maximum, sur '
        . ($nbT === 1 ? 'cette t&acirc;che' : 'ces ' . $nbT . ' t&acirc;ches') . '</div></div>'
        . '<div style="font-size:13.5px;color:' . GRIS . ';line-height:1.55;margin-top:12px">'
        . 'Soit ' . nf(champ($d, 'heures', 0)) . ' heures de travail par an, au co&ucirc;t horaire indiqu&eacute; ('
        . e(champ($d, 'taux')) . '), sur ' . e(champ($d, 'semaines', 46)) . ' semaines travaill&eacute;es. ' . $plafond
        . 'C&rsquo;est le maximum r&eacute;cup&eacute;rable, jamais un r&eacute;sultat garanti&nbsp;: il reste toujours '
        . 'la validation et les cas particuliers.</div></td></tr>'

        . '<tr><td><h2 style="font-size:13px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:' . CYAN . ';margin:26px 0 8px">'
        . 'Ce que ' . ($pourBenjamin ? 'cette personne a' : 'vous nous avez') . ' indiqu&eacute;</h2>'
        . '<table width="100%" cellpadding="0" cellspacing="0">' . $corpsTaches . '</table></td></tr>'

        . '<tr><td><h2 style="font-size:13px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;color:' . CYAN . ';margin:26px 0 4px">'
        . ($pourBenjamin ? 'Ce que la page a propos&eacute;' : 'Ce que nous vous proposons') . '</h2>'
        . $verdict
        . '<table width="100%" cellpadding="0" cellspacing="0">' . $corpsPistes . '</table></td></tr>'

        . '<tr><td>' . $reponses . $cta . '</td></tr>'

        . '<tr><td style="padding-top:24px;border-top:1px solid ' . LIGNE . '">'
        . '<div style="font-size:12px;color:' . GRIS . ';line-height:1.55">'
        . 'Ce rapport reprend uniquement les r&eacute;ponses donn&eacute;es le ' . $date . '. Les montants sont calcul&eacute;s '
        . 'sur ces d&eacute;clarations et ne constituent pas une promesse de r&eacute;sultat. Le prix des solutions se chiffre '
        . 's&eacute;par&eacute;ment, p&eacute;rim&egrave;tre par p&eacute;rim&egrave;tre.<br><br>'
        . 'B&amp;L Connect &middot; ' . BL_MAIL . ' &middot; ' . BL_RDV
        . '</div></td></tr>'

        . '</table></td></tr></table></body></html>';
}

/**
 * Bloc lu par le script qui alimente le CRM. Encode, decoupe en lignes de 76
 * caracteres : une ligne longue serait recoupee au transport et le contenu
 * deviendrait illisible.
 */
function blocCrm($d)
{
    $utile = array(
        'prenom'   => champ($d, 'prenom'),
        'email'    => champ($d, 'email'),
        'activite' => champ($d, 'activite'),
        'dept'     => champ($d, 'dept'),
        'eurLo'    => (int) champ($d, 'eurLo', 0),
        'eurHi'    => (int) champ($d, 'eurHi', 0),
        'heures'   => (int) champ($d, 'heures', 0),
        'plafonne' => (bool) champ($d, 'plafonne'),
        'taux'     => champ($d, 'taux'),
        'lien'     => champ($d, 'lien'),
        'verdict'  => champ($d, 'verdict') ? champ($d['verdict'], 'titre') : '',
        'taches'   => array(),
        'pistes'   => array(),
        'reponses' => champ($d, 'reponses', array()),
    );
    foreach (champ($d, 'taches', array()) as $t) {
        $utile['taches'][] = array(
            'label'  => champ($t, 'label'),
            'freq'   => champ($t, 'freq'),
            'duree'  => champ($t, 'duree'),
            'heures' => (int) champ($t, 'heures', 0),
            'eurLo'  => (int) champ($t, 'eurLo', 0),
        );
    }
    foreach (champ($d, 'pistes', array()) as $pi) {
        $utile['pistes'][] = array(
            'famille' => champ($pi, 'famille'),
            'titre'   => champ($pi, 'titre'),
            'eur'     => (int) champ($pi, 'eur', 0),
            'effort'  => champ($pi, 'effort'),
        );
    }
    $code = base64_encode(json_encode($utile, JSON_UNESCAPED_UNICODE));
    return "--- CRM ---\n" . chunk_split($code, 76, "\n") . "--- FIN CRM ---";
}

function versionTexte($d)
{
    $L   = array();
    $L[] = 'DIAGNOSTIC IA - B&L CONNECT';
    $L[] = '';
    $L[] = champ($d, 'activite');
    $L[] = nf(champ($d, 'eurLo', 0)) . ' EUR par an a recuperer au maximum, soit ' . nf(champ($d, 'heures', 0)) . ' heures.';
    $L[] = '';
    foreach (champ($d, 'taches', array()) as $t) {
        $L[] = '- ' . champ($t, 'label') . ' : ' . champ($t, 'freq') . ' fois/semaine, ' . champ($t, 'duree')
            . ' -> ' . nf(champ($t, 'heures', 0)) . ' h/an, ' . nf(champ($t, 'eurLo', 0)) . ' EUR';
    }
    $L[] = '';
    $L[] = 'PISTES';
    foreach (champ($d, 'pistes', array()) as $i => $p) {
        $L[] = ($i + 1) . '. [' . champ($p, 'famille') . '] ' . champ($p, 'titre')
            . (champ($p, 'eur', 0) > 0 ? ' (jusqu a ' . nf($p['eur']) . ' EUR/an)' : '');
        if (champ($p, 'texte')) {
            $L[] = '   ' . $p['texte'];
        }
    }
    $L[] = '';
    if (champ($d, 'lien')) {
        $L[] = 'Revoir ce diagnostic en ligne, et l enregistrer en PDF :';
        $L[] = $d['lien'];
        $L[] = '';
    }
    $L[] = 'Echange decouverte, 30 minutes, gratuit : ' . BL_RDV;
    return implode("\n", $L);
}

/* ------------------------------------------------------------ traitement */

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    repondre(405, array('ok' => false, 'erreur' => 'methode non autorisee'));
}

$brut = file_get_contents('php://input');
if ($brut === false || strlen($brut) > 200000) {
    repondre(400, array('ok' => false, 'erreur' => 'corps illisible'));
}
$d = json_decode($brut, true);
if (!is_array($d)) {
    repondre(400, array('ok' => false, 'erreur' => 'corps illisible'));
}

// Piege a robots : le champ est invisible pour un humain.
if (champ($d, 'societe') !== '') {
    repondre(200, array('ok' => true));
}

// Ping de mesure : un ecran atteint, rien d'autre. Une ligne dans mesures.php,
// que PHP refuse de servir mais qui se telecharge par FTP.
if (champ($d, 'etape') !== '' && champ($d, 'email') === '') {
    $fichier = __DIR__ . '/mesures.php';
    if (!file_exists($fichier)) {
        file_put_contents($fichier, "<?php exit; ?>\n");
    }
    $ligne = json_encode(array(
        'quand'  => date('c'),
        'etape'  => substr((string) $d['etape'], 0, 20),
        'sid'    => substr((string) champ($d, 'sid'), 0, 12),
        'metier' => substr((string) champ($d, 'metier'), 0, 20),
    ), JSON_UNESCAPED_UNICODE);
    file_put_contents($fichier, $ligne . "\n", FILE_APPEND | LOCK_EX);
    repondre(200, array('ok' => true));
}

$prenom = trim((string) champ($d, 'prenom'));
$email  = trim((string) champ($d, 'email'));
if ($prenom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    repondre(400, array('ok' => false, 'erreur' => 'prenom ou email manquant'));
}
$d['prenom']   = couper($prenom, 60);
$d['email']    = $email;
$d['activite'] = couper((string) champ($d, 'activite', 'Activite non precisee'), 120);

// Le lien ne vient jamais du client : on ne garde que le code, filtre, et on
// reconstruit l'adresse a partir de celle du site.
$code = preg_replace('/[^A-Za-z0-9_-]/', '', (string) champ($d, 'code'));
$code = substr($code, 0, 3000);
$d['lien'] = $code !== '' ? rtrim(BL_SITE, '/') . '/?d=' . $code : '';

if (BL_MDP === '' || BL_MDP === 'a-coller-ici') {
    error_log('diagnostic : BL_MDP absent de config.php');
    repondre(500, array('ok' => false, 'erreur' => 'envoi indisponible'));
}

$montant = nf(champ($d, 'eurLo', 0)) . ' EUR';

function envoyer($destinataire, $nomDest, $sujet, $html, $texte, $repondreA, $nomExpediteur)
{
    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->Host       = BL_SMTP_HOTE;
    $m->Port       = BL_SMTP_PORT;
    $m->SMTPAuth   = true;
    $m->Username   = BL_MAIL;
    $m->Password   = BL_MDP;
    $m->SMTPSecure = (BL_SMTP_PORT === 465)
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $m->Timeout    = 20;
    $m->CharSet    = 'UTF-8';
    $m->setFrom(BL_MAIL, $nomExpediteur);
    $m->addAddress($destinataire, $nomDest);
    if ($repondreA) {
        $m->addReplyTo($repondreA[0], $repondreA[1]);
    }
    $m->isHTML(true);
    $m->Subject = $sujet;
    $m->Body    = $html;
    $m->AltBody = $texte;
    $m->send();
}

$texte = versionTexte($d);
$pourBenjamin = false;

try {
    envoyer(
        BL_MAIL, 'B&L Connect',
        'Diagnostic - ' . $d['activite'] . ' - ' . $d['prenom'] . ' (' . $montant . ')',
        rapport($d, true),
        $texte . "\n\n" . $d['prenom'] . ' - ' . $d['email'] . "\n\n" . blocCrm($d),
        array($d['email'], $d['prenom']),
        'Diagnostic B&L Connect'
    );
    $pourBenjamin = true;
} catch (Exception $err) {
    error_log('diagnostic, mail interne : ' . $err->getMessage());
}

$pourLeVisiteur = false;
try {
    envoyer(
        $d['email'], $d['prenom'],
        'Votre diagnostic IA : ' . $montant . ' par an',
        rapport($d, false),
        $texte,
        array(BL_MAIL, 'B&L Connect'),
        BL_EXPEDITEUR
    );
    $pourLeVisiteur = true;
} catch (Exception $err) {
    error_log('diagnostic, mail visiteur : ' . $err->getMessage());
}

// Le contact est capte des que la fiche est arrivee : la page peut confirmer.
if ($pourBenjamin) {
    repondre(200, array('ok' => true, 'prospect' => $pourLeVisiteur));
}
repondre(502, array('ok' => false, 'erreur' => 'envoi impossible'));
