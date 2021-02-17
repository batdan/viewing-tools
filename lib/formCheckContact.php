<?php
namespace tools;

/**
 * Envoie de mails de contact
 * Permet de rebasculer un internautes dans un parcours de lead classique
 *
 * @author Daniel Gomes
 */
class formCheckContact extends formCheckSave
{
    /**
     * Attributs
     */
    private $_config;           // Configuration du site
    private $_stepEndForm;      // Dernière étape du formulaire


    /**
     * Constructeur
     *
     * @param       string      $cta        Formulaire sélectionné
     * @param       string      $step       Etape du formulaire
     * @param       string      $chp        Champs envoyés
     */
    public function __construct($cta, $step, $chp)
    {
        // Récupération de la configuration du site
        $this->_config = config::getConfig('config');

        parent::__construct($cta, $step, $chp, 'contact');

        // Dernière étape du formulaire
        $this->_stepEndForm = $this->_form['stepEndForm'];
    }


    /**
     * Sauvegarde du formulaire dans la variable de session et en base de données
     */
    protected function contact()
    {
        // Déclaration des variables -------------------------------------------
        $redir          = 'false';
        $action         = '';
        $req            = '';
        $hydrate        = '';
        $resultSendMail = '';


        // Redirection vers un autre form --------------------------------------
        if (isset($this->_form['steps'][$this->_step]['switch'])) {

            $keys       = array_keys($this->_chp);
            $keyName    = $keys[0];
            $switchKey  = $this->_chp[$keyName];
            $switchCta  = $this->_form['steps'][$this->_step]['nextStep'][$switchKey]['cta'];

            if ($switchCta != $this->_cta) {

                // Enregistrement du parcours classique du CTA appelé
                $_SESSION['form']['parcours'][] = array(
                    'cta'  => $switchCta,
                    'step' => '0',
                );

                $_SESSION['form']['parcours'][] = array(
                    'cta'  => $switchCta,
                    'step' => '1',
                );

                // Enregistrement du CTA
                $_SESSION['form']['cta']  = $switchCta;

                // On se recale sur la bonne étape
                $_SESSION['form']['step'] = 1;

                // Activation de la redirection
                $redir = 'true';
            }
        }


        // Le formulaire est rempli --------------------------------------------
        if ($this->_step == $this->_stepEndForm) {

            // Stockage du mail en BDD par sécurité ----------------------------
            $hydrate = array(
                                ':id_project'       => $this->_config['id_project'],
                                ':domain'           => $this->_config['domain'],
                                ':ip'               => $_SERVER['REMOTE_ADDR'],
                                ':civilite'         => $_SESSION['form']['civilite'],
                                ':nom'              => $_SESSION['form']['nom'],
                                ':prenom'           => $_SESSION['form']['prenom'],
                                ':email'            => $_SESSION['form']['email'],
                                ':objet'            => $_SESSION['form']['objet'],
                                ':message'          => $_SESSION['form']['message'],
            );

            // Stockage du mail en BDD par sécurité
            $req = "INSERT INTO contact_mail
                    ( id_project,  domain,  ip,  civilite,  prenom,  nom,  email,  objet,  message, date_crea, date_modif)
                    VALUES
                    (:id_project, :domain, :ip, :civilite, :prenom, :nom, :email, :objet, :message, NOW(),     NOW())";

            $sql = $this->_dbh->prepare($req);
            $sql->execute($hydrate);

            // Mise en forme de la requête pour le débug
            foreach($hydrate as $k => $v) {
                if (! is_numeric($v)) {
                    $v = "'" . $v . "'";
                }
                $req = str_replace($k, $v, $req);
            }


            // Envoi du mail avec swiftMailer ----------------------------------

            // Connexion comte SMTP avec StartTLS
            $transport = \Swift_SmtpTransport::newInstance( $this->_config['smtp-addr'],
                                                            $this->_config['smtp-port'],
                                                            'tls');

            $transport->setUsername($this->_config['smtp-account']);
            $transport->setPassword($this->_config['smtp-passwd']);
            $transport->setStreamOptions(array('ssl' => array('allow_self_signed' => true, 'verify_peer' => false)));

            // Instance du Mailer avec la configuration des mails sortants
            $mailer = \Swift_Mailer::newInstance($transport);

            // Création du mail
            $mail = \Swift_Message::newInstance();

            // Contenu du message
            $nom_complet = $_SESSION['form']['nom'] . ' ' . $_SESSION['form']['prenom'];

            $corps = array();
            $corps[] = 'Nom : ' . $nom_complet;
            $corps[] = 'Email : ' . $_SESSION['form']['email'];
            $corps[] = 'Objet : ' . $_SESSION['form']['objet'];

            $corpsHtml  = '<html><body>';
            $corpsHtml .= implode('<br>', $corps);
            $corpsHtml .=  '<br><br>';
            $corpsHtml .= str_replace(chr(10), '<br>', $_SESSION['form']['message']);
            $corpsHtml .= '</body></html>';

            $corpsTxt  = implode(chr(10), $corps) . chr(10) . chr(10) . $_SESSION['form']['message'];

            # Filtre de nos adresses mail pour test
            if (in_array( $_SESSION['form']['email'], $this->_config['emailTest'] )) {
                $this->_config['email'] = array();
            }

            $mail->setSubject($_SESSION['form']['objet'] . ' - ' . $nom_complet);                                   // Objet du message
            $mail->setFrom(array($this->_config['email-from'] => 'Contact ' . $this->_config['domain']));           // Adresse de d'expéditeur
            $mail->setReplyTo(array($_SESSION['form']['email']));                                                   // Adresse de réponse
            $mail->setTo($this->_config['email']);                                                                  // Adresse du destinataire
            $mail->setBcc($this->_config['emailCci']);                                                              // Adresse destinataires en copie

            $mail->setBody($corpsHtml);                                                                             // Corps du message - Version HTML
            $mail->setContentType('text/html');

            $mail->addPart($corpsTxt, 'text/plain');                                                                // Corps du message - Version alternative en texte
            //$mail->attach(Swift_Attachment::fromPath('my-document.pdf'));                                         // Possibilité d'attacher une pièce jointe

            // Envoi du mail
            $resultSendMail = $mailer->send($mail);

            $action = 'sendMail';
        }

        // Retour --------------------------------------------------------------
        return array(
            /* Debug */
            //'parcours'      => $_SESSION['form']['parcours'],
            //'req'           => $req,
            'cta'             => $_SESSION['form']['cta'],
            //'stepEndForm'   => $this->_stepEndForm,
            //'step'          => $this->_step,
            //'chp'           => $this->_chp,
            //'switchCta'     => $switchCta,
            //'switchStep'    => $switchStep,

            /* Important */
            'resultSendMail'    => $resultSendMail,
            'action'            => $action,
            'redir'             => $redir,
        );
    }
}
