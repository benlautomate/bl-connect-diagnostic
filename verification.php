<?php
/*
 * Page de controle, a ouvrir une fois dans le navigateur apres avoir depose le
 * dossier : https://votre-site/diagnostic/verification.php
 *
 * Elle dit ce qui manque, teste la connexion a la boite mail, et peut envoyer
 * un message d'essai. SUPPRIMEZ-LA du serveur une fois que tout est vert.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$lignes = array();
$bloquant = 0;

function verdict(&$lignes, &$bloquant, $ok, $titre, $detail = '', $grave = true)
{
    if (!$ok && $grave) {
        $bloquant++;
    }
    $lignes[] = array('ok' => $ok, 'grave' => $grave, 'titre' => $titre, 'detail' => $detail);
}

/* ---- PHP lui-meme */
verdict($lignes, $bloquant, version_compare(PHP_VERSION, '7.2', '>='),
    'Version de PHP', PHP_VERSION . ' (7.2 minimum)');
verdict($lignes, $bloquant, extension_loaded('openssl'),
    'Extension openssl', 'indispensable pour parler au serveur mail en chiffre');
verdict($lignes, $bloquant, function_exists('json_decode'),
    'Extension json', '');
verdict($lignes, $bloquant, function_exists('mb_substr'),
    'Extension mbstring', 'sans elle les accents peuvent etre coupes de travers', false);

/* ---- les fichiers du dossier */
$manquants = array();
foreach (array('index.html', 'diagnostic.php', 'lib/PHPMailer.php', 'lib/SMTP.php',
               'lib/Exception.php', 'partage.png', 'favicon.png') as $f) {
    if (!file_exists(__DIR__ . '/' . $f)) {
        $manquants[] = $f;
    }
}
verdict($lignes, $bloquant, count($manquants) === 0,
    'Fichiers du dossier', $manquants ? 'manquent : ' . implode(', ', $manquants) : 'tous presents');

verdict($lignes, $bloquant, is_writable(__DIR__),
    'Dossier inscriptible', 'necessaire seulement pour le journal des abandons', false);

/* ---- la configuration */
$config = file_exists(__DIR__ . '/config.php');
verdict($lignes, $bloquant, $config, 'Fichier config.php',
    $config ? 'present' : 'copiez config.exemple.php en config.php');

if ($config) {
    require __DIR__ . '/config.php';
    $mdp = defined('BL_MDP') ? BL_MDP : '';
    verdict($lignes, $bloquant, $mdp !== '' && $mdp !== 'a-coller-ici',
        'Mot de passe renseigne', 'le mot de passe d appareil Infomaniak, pas celui du compte');
    verdict($lignes, $bloquant, defined('BL_MAIL') && filter_var(BL_MAIL, FILTER_VALIDATE_EMAIL),
        'Adresse de la boite', defined('BL_MAIL') ? BL_MAIL : '');
    verdict($lignes, $bloquant, defined('BL_SITE') && strpos(BL_SITE, 'http') === 0,
        'Adresse publique du diagnostic', defined('BL_SITE') ? BL_SITE : '');
}

/* ---- la connexion a la boite mail */
$smtp = null;
if ($bloquant === 0 && $config) {
    require __DIR__ . '/lib/Exception.php';
    require __DIR__ . '/lib/PHPMailer.php';
    require __DIR__ . '/lib/SMTP.php';
    try {
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
        $m->Timeout    = 15;
        $m->smtpConnect();
        $m->smtpClose();
        $smtp = true;
    } catch (Exception $err) {
        $smtp = $err->getMessage();
    } catch (Throwable $err) {
        $smtp = $err->getMessage();
    }
    verdict($lignes, $bloquant, $smtp === true, 'Connexion a la boite mail',
        $smtp === true ? BL_SMTP_HOTE . ':' . BL_SMTP_PORT : (string) $smtp);
}

/* ---- envoi d'essai, sur demande */
$essai = null;
if (isset($_GET['essai']) && $smtp === true) {
    try {
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
        $m->CharSet = 'UTF-8';
        $m->setFrom(BL_MAIL, 'Diagnostic B&L Connect');
        $m->addAddress(BL_MAIL, 'B&L Connect');
        $m->isHTML(true);
        $m->Subject = 'Essai du diagnostic';
        $m->Body    = '<p>Si vous lisez ceci, la page peut envoyer les diagnostics.</p>'
            . '<p style="color:#5e6e80">Envoye depuis ' . htmlspecialchars(BL_SITE) . '</p>';
        $m->AltBody = 'Si vous lisez ceci, la page peut envoyer les diagnostics.';
        $m->send();
        $essai = true;
    } catch (Exception $err) {
        $essai = $err->getMessage();
    }
}
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Vérification du diagnostic</title>
<style>
  body{margin:0;padding:2.5rem 1.25rem;background:#f5f8fb;color:#011734;
       font:16px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif}
  main{max-width:38rem;margin:0 auto}
  h1{font-size:1.5rem;margin:0 0 .25rem}
  .sous{color:#5e6e80;font-size:.9rem;margin:0 0 1.5rem}
  ul{list-style:none;margin:0;padding:0}
  li{background:#fff;border:1px solid #dde4ec;border-radius:12px;padding:.875rem 1rem;margin-bottom:.5rem;
     display:flex;gap:.75rem;align-items:flex-start}
  .p{flex:none;width:1.5rem;height:1.5rem;border-radius:50%;color:#fff;font-weight:700;
     display:flex;align-items:center;justify-content:center;font-size:.8rem}
  .oui{background:#059fd9}.non{background:#c0392b}.mou{background:#d8a24a}
  b{display:block}
  small{color:#5e6e80}
  .bilan{border-radius:12px;padding:1rem 1.25rem;margin:1.5rem 0;font-weight:600}
  .bon{background:#ecf8fd;border:1px solid #b8e6f7;color:#04244c}
  .mauvais{background:#fdecea;border:1px solid #f5c6c0;color:#7b241c}
  a.bouton{display:inline-block;background:#011734;color:#fff;text-decoration:none;
           padding:.75rem 1.25rem;border-radius:10px;font-weight:600}
  .avert{margin-top:2rem;padding:1rem 1.25rem;background:#fdf7ed;border:1px solid #ecd9b4;
         border-radius:12px;font-size:.9rem;color:#7a5a1e}
</style>
</head>
<body>
<main>
  <h1>Vérification du diagnostic</h1>
  <p class="sous">Cette page dit ce qui manque avant la mise en service.</p>

  <ul>
<?php foreach ($lignes as $l): ?>
    <li>
      <span class="p <?php echo $l['ok'] ? 'oui' : ($l['grave'] ? 'non' : 'mou'); ?>">
        <?php echo $l['ok'] ? '✓' : ($l['grave'] ? '✕' : '!'); ?>
      </span>
      <span><b><?php echo htmlspecialchars($l['titre']); ?></b>
      <?php if ($l['detail']): ?><small><?php echo htmlspecialchars($l['detail']); ?></small><?php endif; ?>
      </span>
    </li>
<?php endforeach; ?>
  </ul>

<?php if ($bloquant === 0): ?>
  <div class="bilan bon">Tout est en place. Le diagnostic peut envoyer ses rapports.</div>
  <?php if ($essai === true): ?>
    <div class="bilan bon">Message d’essai parti. Regardez la boîte <?php echo htmlspecialchars(BL_MAIL); ?>.</div>
  <?php elseif (is_string($essai)): ?>
    <div class="bilan mauvais">L’essai a échoué : <?php echo htmlspecialchars($essai); ?></div>
  <?php else: ?>
    <p><a class="bouton" href="?essai=1">M’envoyer un message d’essai</a></p>
  <?php endif; ?>
<?php else: ?>
  <div class="bilan mauvais"><?php echo $bloquant; ?> point<?php echo $bloquant > 1 ? 's' : ''; ?> à régler avant que les mails puissent partir.</div>
<?php endif; ?>

  <div class="avert"><b>À faire ensuite :</b> supprimez ce fichier du serveur.
  Il montre l’état de votre configuration, il n’a pas à rester en ligne.</div>
</main>
</body>
</html>
