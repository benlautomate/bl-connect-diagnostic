# Diagnostic B&L Connect

Questionnaire de 9 questions qui chiffre le coût annuel d'une tâche récurrente et propose
des solutions concrètes. Utilisé comme lead magnet en prospection.

Une page, aucune dépendance à installer. `index.html` se suffit à lui-même.

## Déploiement

Netlify est branché sur ce dépôt : chaque push sur `main` redéploie automatiquement.
Rien à configurer, `index.html` est servi à la racine.

## Liens de prospection par métier

Ajouter `?metier=` à l'URL fait démarrer le questionnaire directement sur les tâches
du métier concerné, avec le vocabulaire correspondant :

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
reprend simplement par le choix du métier, comme sans paramètre.

## Ce qui se règle en haut du fichier

Dans `index.html`, chercher `var CONFIG` :

- `rdv` : lien de prise de rendez-vous
- `landing` : page visée par le QR code
- `email` : destinataire des diagnostics envoyés
- `semaines` : semaines travaillées par an, base du calcul

## Règles tenues par la page

Elles ne sont pas décoratives, elles évitent de promettre ce qui ne peut pas être tenu.

- Aucun gain annoncé. La page chiffre le coût actuel de la tâche, calculé sur les tranches
  que la personne déclare elle-même.
- Aucune mention de RGPD ni de conformité.
- Le financement CCI Oise ne s'affiche que pour l'Oise, et seulement le taux de prise en
  charge : ni le tarif du diagnostic de la CCI, qui se lirait comme le nôtre, ni les
  critères d'éligibilité, qui ne se tranchent pas depuis un questionnaire. La réserve
  d'acceptation reste.
- Une seule action de sortie : l'échange découverte.

## Envoi automatique du diagnostic

Quand quelqu'un laisse son prénom et son email, la page envoie ses réponses à
`netlify/functions/diagnostic.js`, qui poste deux mails depuis `benjamin@bl-connect.fr` :

- au prospect : son rapport complet, à la charte, avec le lien de rendez-vous ;
- à Benjamin : le même rapport plus toutes les réponses, avec `Répondre` pré-réglé
  sur l'adresse du prospect.

Le rapport imprimable est la contrepartie de l'email : tant qu'il n'est pas donné,
le bouton PDF reste caché et un Ctrl+P ne sort qu'un message d'invitation.

### À régler une fois sur Netlify

Site settings → Environment variables → `BL_SMTP_PASS` : le mot de passe d'appareil
Infomaniak de la boîte. Sans lui la fonction répond 500 et la page bascule sur
l'ouverture de la messagerie du visiteur.

Trois variables facultatives couvrent un changement d'hébergeur mail :
`BL_SMTP_USER` (défaut `benjamin@bl-connect.fr`), `BL_SMTP_HOST`
(défaut `mail.infomaniak.com`), `BL_SMTP_PORT` (défaut 587, STARTTLS).

### Si l'envoi échoue

La page attend douze secondes, puis ouvre la messagerie du visiteur avec le
diagnostic prérempli, et débloque quand même son rapport. Rien n'est perdu, mais
le mail ne part que s'il clique sur Envoyer.

## Savoir où les visiteurs s'arrêtent

Chaque écran atteint envoie une ligne à la fonction : l'écran, un identifiant tiré au
sort qui vit le temps de la visite, et le métier choisi. Aucun cookie, aucun stockage,
rien qui suive quelqu'un d'une visite à l'autre.

Ces lignes se lisent sur Netlify, Logs → Functions, en filtrant sur `DIAG-ETAPE`.
Comparer le nombre de lignes `metier` et `result` donne le taux d'abandon, et le détail
par écran dit où ça coince.

## Aperçu des liens partagés

`partage.png` (1200 × 630) est l'image que montrent LinkedIn, WhatsApp ou un client mail
quand le lien est collé. Elle est déclarée dans `og:image` en chemin relatif : **dès que
l'adresse définitive du site est connue, la passer en URL absolue**
(`https://votre-domaine/partage.png`), certains réseaux refusant les chemins relatifs.

Pour la régénérer après un changement de charte, elle est produite par capture d'une page
HTML à part, pas dessinée à la main.

## Le format du diagnostic envoyé, et pourquoi

Le rapport part **dans le corps du mail**, en HTML sobre, avec une version texte
en double. Pas de pièce jointe : les filtres anti-spam appliquent une inspection
supplémentaire aux messages qui en portent, et les passerelles de sécurité des
cabinets, qui sont la cible ici, sont les plus strictes. La recommandation
constante des sources consultées est d'héberger le document et d'envoyer le lien.

Ce lien est le second élément : `?d=<code>` rouvre la page sur le résultat exact,
d'où le visiteur enregistre son PDF lui-même. Tout l'état du diagnostic tient dans
ce code, environ 170 caractères, donc rien n'est conservé de notre côté et le lien
reste valable tant que le format ne change pas (il porte un numéro de version).

L'adresse du lien est reconstruite **dans la fonction**, à partir de `process.env.URL`
que Netlify renseigne, et du seul code filtré. Le lien complet envoyé par le
navigateur n'est jamais repris tel quel : sinon n'importe qui pourrait faire partir
l'adresse de son choix depuis benjamin@bl-connect.fr.
