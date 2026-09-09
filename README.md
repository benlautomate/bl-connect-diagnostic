# Diagnostic B&L Connect

Questionnaire de 8 questions qui chiffre le coût annuel d'une tâche récurrente et propose
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
| `?metier=compta` | Cabinet comptable ou expertise |
| `?metier=juridique` | Cabinet juridique ou conseil |
| `?metier=etudes` | Bureau d'études, ingénierie |
| `?metier=courtage` | Courtage, assurance, financement |
| `?metier=formation` | Organisme de formation |
| `?metier=technique` | Services techniques, maintenance |
| `?metier=batiment` | Bâtiment, travaux, artisanat |

Sans paramètre, le questionnaire commence par le choix du métier.

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
