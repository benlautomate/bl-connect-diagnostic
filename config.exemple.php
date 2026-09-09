<?php
/*
 * Copier ce fichier en "config.php" sur le serveur, puis y coller le mot de passe.
 *
 * config.php ne doit JAMAIS partir sur GitHub : il porte le mot de passe de la
 * boite mail. Il est deja dans .gitignore, ne l'en sortez pas.
 */

// Boite qui envoie, et qui recoit les diagnostics remplis.
define('BL_MAIL', 'benjamin@bl-connect.fr');

// Mot de passe d'appareil Infomaniak (Manager > Service Mail > l'adresse >
// Gerer l'adresse mail > Appareil connecte). Ce n'est ni le mot de passe du
// compte, ni un mot de passe d'application de compte : eux echouent.
define('BL_MDP', 'a-coller-ici');

// Serveur d'envoi. 587 en STARTTLS, ou 465 en SSL si le 587 est bloque.
define('BL_SMTP_HOTE', 'mail.infomaniak.com');
define('BL_SMTP_PORT', 587);

// Adresse publique du diagnostic, avec la barre finale. Sert a fabriquer le
// lien qui rouvre le rapport depuis le mail.
define('BL_SITE', 'https://www.bl-connect.fr/diagnostic/');

// Prise de rendez-vous, reprise dans les mails.
define('BL_RDV', 'https://calendly.com/benjcailhol/rdv-decouverte');

// Nom affiche comme expediteur du rapport envoye au visiteur.
define('BL_EXPEDITEUR', 'Benjamin Cailhol');
