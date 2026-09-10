# Diagnostic B&L Connect

Questionnaire de 9 questions qui chiffre le coût annuel de tâches récurrentes et propose
trois pistes concrètes. Utilisé comme lead magnet en prospection.

La page, `index.html`, se suffit à elle-même : aucune bibliothèque, aucune installation.
`diagnostic.php` s'occupe des envois quand l'hébergement exécute PHP.

## Mise en ligne sur l'hébergement IONOS

Déposer par FTP, dans un sous-dossier du site (`/diagnostic/` par exemple) :

    index.html          la page
    diagnostic.php      envoie les deux mails
    verification.php    page de contrôle, à supprimer après usage
    config.php          vos réglages, à créer depuis config.exemple.php
    web.config          réglages IIS (dossier par défaut, fichiers protégés)
    favicon.png         icône de l'onglet
    partage.png         image des liens partagés
    lib/                PHPMailer, trois fichiers

Ensuite :

1. Copier `config.exemple.php` en `config.php` et y coller le mot de passe d'appareil
   Infomaniak, ainsi que l'adresse publique du diagnostic.
2. Ouvrir `verification.php` dans le navigateur. La page dit ce qui manque, teste la
   connexion à la boîte mail et envoie un message d'essai.
3. Une fois tout au vert, **supprimer `verification.php` du serveur**.
4. Reprendre `og:image` et `og:url` dans le `<head>` de `index.html` avec l'adresse
   définitive, sans quoi les liens partagés n'afficheront pas d'aperçu.

`config.php` porte le mot de passe de la boîte : il est dans `.gitignore` et ne doit
jamais partir sur GitHub. Le dépôt est public.

Si le diagnostic reste aussi servi par GitHub Pages, penser à y couper la publication :
deux versions en ligne, dont une sans PHP, finissent par diverger.

## Ce qui se règle dans config.php

| Constante | Rôle |
|---|---|
| `BL_MAIL` | boîte qui envoie et qui reçoit les diagnostics |
| `BL_MDP` | mot de passe **d'appareil** Infomaniak, ni celui du compte, ni un mot de passe d'application |
| `BL_SMTP_HOTE`, `BL_SMTP_PORT` | `mail.infomaniak.com`, 587 en STARTTLS (465 en SSL si le 587 est bloqué) |
| `BL_SITE` | adresse publique du diagnostic, base du lien de reprise |
| `BL_RDV` | lien de prise de rendez-vous |
| `BL_EXPEDITEUR` | nom affiché comme expéditeur du rapport |

Le reste se règle en haut du script de `index.html`, dans `var CONFIG` : `depot` (chemin
du script d'envoi, `diagnostic.php` à côté de la page) et `semaines` (semaines travaillées
par an, base du calcul).

## Ce que reçoit une personne qui remplit le diagnostic

Deux mails partent depuis la boîte B&L Connect :

- **au visiteur** : son rapport complet dans le corps du message, plus un lien qui rouvre
  la page sur son résultat, d'où il enregistre son PDF ;
- **à Benjamin** : le même rapport, ses coordonnées, toutes ses réponses, `Répondre` déjà
  réglé sur son adresse, et un bloc de données encodé que le script du CRM sait lire.

Le message est écrit pour être lu sur un téléphone : chaque tâche est un bloc, pas une
ligne de tableau à cinq colonnes, et rien ne dépend d'une media query pour rester lisible,
certains clients les supprimant. Vérifié sans débordement jusqu'à 320 points de large.

Pas de pièce jointe : les filtres anti-spam inspectent davantage les messages qui en
portent, et les passerelles de sécurité des cabinets, qui sont la cible ici, sont les plus
strictes. La recommandation constante des sources consultées est d'héberger le document et
d'envoyer le lien.

Ce lien est `?d=<code>` : tout l'état du diagnostic tient dans ce code, environ 170
caractères, donc rien n'est conservé côté serveur et le lien reste valable tant que le
format ne change pas (il porte un numéro de version). Un lien abîmé ramène simplement à
l'accueil.

L'adresse du lien est reconstruite **par le script**, à partir de `BL_SITE` et du seul code
filtré. Le lien envoyé par le navigateur n'est jamais repris tel quel : sinon n'importe qui
ferait partir l'adresse de son choix depuis la boîte B&L Connect.

**Sans PHP** (GitHub Pages, hébergement statique), mettre `depot: ""` dans `CONFIG` : la
page ouvre alors la messagerie du visiteur avec son diagnostic et son lien déjà écrits, et
débloque quand même son rapport. Rien ne se perd, mais c'est lui qui appuie sur Envoyer.

## Savoir où les visiteurs s'arrêtent

Chaque écran atteint envoie une ligne à `diagnostic.php` : l'écran, un identifiant tiré au
sort qui vit le temps de la visite, et le métier choisi. Aucun cookie, aucun stockage, rien
qui suive quelqu'un d'une visite à l'autre.

Ces lignes s'écrivent dans `mesures.php`, que le serveur refuse de servir et qui se
récupère par FTP. Comparer le nombre de lignes `metier` et `result` donne le taux
d'abandon, et le détail par écran dit où ça coince.

## Liens de prospection par métier

Ajouter `?metier=` à l'URL fait démarrer le questionnaire directement sur les tâches du
métier concerné, avec le vocabulaire correspondant :

| Paramètre | Métier |
|---|---|
| `?metier=compta` | Cabinet comptable & paie |
| `?metier=juridique` | Cabinet juridique, conseil & audit |
| `?metier=etudes` | Bureau d'études, ingénierie & architecture |
| `?metier=courtage` | Banque, assurance, courtage & immobilier |
| `?metier=industrie` | Industrie, négoce & distribution |
| `?metier=transport` | Transport, logistique & supply chain |
| `?metier=digital` | Agence & prestataires digitaux |
| `?metier=formation` | Formation, RH & recrutement |
| `?metier=batiment` | Bâtiment, travaux & maintenance |

Les appellations courantes sont rattrapées : `technique`, `maintenance`, `artisan` et
`travaux` mènent à `batiment`, `avocat` et `notaire` à `juridique`, `rh` et `recrutement`
à `formation`, et ainsi de suite. Un identifiant inconnu ne casse rien : le questionnaire
reprend par le choix du métier, comme sans paramètre.

## Règles tenues par la page

Elles ne sont pas décoratives, elles évitent de promettre ce qui ne peut pas être tenu.

- Aucun gain annoncé. La page chiffre le coût actuel des tâches, sur les tranches que la
  personne déclare elle-même, et ne dépasse jamais ce que l'effectif indiqué peut y
  consacrer.
- Le rapport complet est la contrepartie de l'email : tant qu'il n'est pas donné, le bouton
  PDF reste caché et un Ctrl+P ne sort qu'un message d'invitation.
- Aucune mention de RGPD ni de conformité.
- Le financement CCI Oise ne s'affiche que pour l'Oise, et seulement le taux de prise en
  charge : ni le tarif du diagnostic de la CCI, qui se lirait comme le nôtre, ni les
  critères d'éligibilité, qui ne se tranchent pas depuis un questionnaire. La réserve
  d'acceptation reste.
- Une seule action de sortie : l'échange découverte.

## Aperçu des liens partagés

`partage.png` (1200 × 630) est l'image que montrent LinkedIn, WhatsApp ou un client mail
quand le lien est collé. Elle est déclarée en URL absolue dans `og:image`, à reprendre en
cas de changement de domaine. Elle est produite par capture d'une page HTML à part, pas
dessinée à la main.

## Du diagnostic à la fiche CRM

Le CRM est une base locale, le diagnostic tourne sur le serveur du site : le serveur ne peut
pas y écrire. Le pont est le mail interne, qui porte un bloc de données encodé.

Côté machine de Benjamin, `AGENT_MAIL/diagnostics_vers_crm.py` lit ces mails et écrit dans
le CRM : contact déjà connu par son email, une ligne de journal et une suite datée ; contact
inconnu, la fiche est créée en `source=Diagnostic`, `tunnel=Entrant`, `chaleur=Hot`,
`statut=Intéressé`, avec un rappel à deux jours ouvrés ; homonyme possible, rien n'est écrit
et le cas part dans `a_arbitrer/`.

Sans `--pour-de-vrai`, le script montre seulement ce qu'il ferait, sans rien écrire nulle part.

C'est pour ce script que le formulaire demande le **nom complet** et non le seul prénom :
sur un prénom seul, l'anti-doublon du CRM refuse la création dès qu'un homonyme existe, et
la base en compte déjà plusieurs.
