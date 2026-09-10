# Mise en ligne du diagnostic — brief

Bonjour Loïc,

Ce dossier est un questionnaire de neuf questions qui chiffre ce qu'une tâche récurrente
coûte à une entreprise, et lui propose trois pistes. Il sert de porte d'entrée en
prospection : la personne le remplit, laisse son nom et son email, et reçoit son rapport
par mail. Benjamin reçoit la même chose de son côté.

Il n'y a **ni base de données, ni framework, ni build**. Une page HTML, un script PHP
d'envoi, et la bibliothèque PHPMailer. Tout se dépose par FTP et fonctionne tel quel.

## Ce qu'il faut sur le serveur

- **PHP** actif sur le dossier (7.2 ou plus récent, avec `openssl`) ;
- un accès **FTP/SFTP**.

C'est tout. L'hébergement web IONOS du site couvre les deux.

## Le dossier à déposer

Tu l'as reçu en ZIP. Il vient du dépôt **github.com/benlautomate/bl-connect-diagnostic**,
si tu préfères y récupérer la dernière version.

Emplacement prévu : un sous-dossier du site, `/diagnostic/`, pour que l'adresse publique
soit `https://www.bl-connect.fr/diagnostic/`.

| Fichier | Rôle |
|---|---|
| `index.html` | la page, elle se suffit à elle-même |
| `diagnostic.php` | reçoit le formulaire et envoie les deux mails |
| `lib/` | PHPMailer, trois fichiers. **Sans lui, aucun mail ne part** |
| `config.exemple.php` | modèle des réglages, à copier |
| `verification.php` | page de contrôle, **à supprimer une fois la mise en ligne faite** |
| `web.config` | réglages IIS : document par défaut, fichiers protégés |
| `favicon.png`, `partage.png` | icône de l'onglet, image des liens partagés |

`README.md` et ce brief peuvent rester ou non, ils ne gênent rien.

## Les cinq étapes

**1. Déposer tout le contenu du ZIP** dans `/diagnostic/`, en gardant la structure :
`lib/` doit rester un sous-dossier.

**2. Créer `config.php`** à partir de `config.exemple.php` (le renommer, ou le copier).
Six lignes à renseigner :

```php
define('BL_MAIL', 'benjamin@bl-connect.fr');        // la boîte qui envoie et reçoit
define('BL_MDP', '...');                            // le mot de passe que Benjamin t'a donné
define('BL_SMTP_HOTE', 'mail.infomaniak.com');      // ne pas changer
define('BL_SMTP_PORT', 587);                        // 465 si le 587 est bloqué
define('BL_SITE', 'https://www.bl-connect.fr/diagnostic/');
define('BL_RDV', 'https://calendly.com/benjcailhol/rdv-decouverte');
define('BL_EXPEDITEUR', 'Benjamin Cailhol');
```

Le mot de passe est un **mot de passe d'appareil Infomaniak**, pas celui du compte : les
autres échouent avec `535 Invalid login or password`. Benjamin te le transmet directement.

**3. Ouvrir `https://www.bl-connect.fr/diagnostic/verification.php`.** La page liste tout
ce qui doit être en place, ligne par ligne, et dit ce qui manque le cas échéant. Quand tout
est vert, un bouton envoie un message d'essai à la boîte de Benjamin.

**4. Supprimer `verification.php` du serveur.** Elle montre l'état de la configuration,
elle n'a pas à rester en ligne.

**5. Faire un vrai passage** : remplir le questionnaire de bout en bout avec ta propre
adresse, et vérifier que le mail arrive avec le rapport dedans. Le bouton
« Enregistrer mon rapport en PDF » n'apparaît qu'après avoir donné nom et email, c'est
voulu.

## Si le dossier atterrit ailleurs que `/diagnostic/`

Trois endroits à reprendre, sinon l'aperçu des liens partagés et le bouton « revoir ce
diagnostic » du mail pointent à côté :

- `BL_SITE` dans `config.php` ;
- `og:url` et `og:image`, en haut de `index.html` (lignes 19 et 22).

Le reste est en chemins relatifs et suit le dossier tout seul.

## Deux choses à ne pas faire

- **`config.php` ne quitte pas le serveur.** Il porte le mot de passe de la boîte mail : ni
  renvoyé par mail, ni déposé sur un partage, ni publié sur le dépôt, qui est public. Le
  `.gitignore` fourni l'exclut déjà.
- **Ne pas laisser `verification.php`** une fois la vérification faite.

## Si quelque chose ne marche pas

La page ne plante jamais : quand l'envoi échoue, elle ouvre la messagerie du visiteur avec
son diagnostic déjà écrit. Donc si tu vois la messagerie s'ouvrir au lieu du message
« C'est envoyé », c'est que le PHP n'a pas répondu. Trois causes, dans l'ordre de
fréquence :

1. **`config.php` absent ou mot de passe vide** → `verification.php` le dit tout de suite ;
2. **PHP inactif sur le dossier** → l'adresse `diagnostic.php` renvoie le code source ou
   une erreur 404/500 au lieu d'un `{"ok":...}` ;
3. **Port 587 bloqué en sortie** → passer `BL_SMTP_PORT` à 465, la page de vérification
   affiche alors l'erreur de connexion exacte.

Les erreurs PHP partent dans le journal du serveur, jamais à l'écran : la page ne renvoie
que du JSON.

## Ce qu'il y a d'autre dans le dossier, et qu'il ne faut pas supprimer

`diagnostic.php` écrit aussi un fichier `mesures.php` au premier passage : c'est le journal
des écrans atteints, qui sert à savoir où les visiteurs abandonnent. Il commence par
`<?php exit; ?>` pour que le serveur refuse de le servir. Il grossit lentement, il se
récupère par FTP, il ne se supprime pas.

## Quand c'est en ligne

Redonne à Benjamin **l'adresse exacte** du diagnostic, et dis-lui si tu as dû changer le
port SMTP. C'est tout ce dont il a besoin de son côté.

Merci,
