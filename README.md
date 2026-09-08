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
- Le financement CCI Oise ne s'affiche que pour l'Oise, avec ses conditions et la réserve
  d'acceptation.
- Une seule action de sortie : l'échange découverte.
