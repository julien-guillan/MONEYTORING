<?php

namespace ApplicationBundle\Controller;

//ENTITE CONTROLLER ET ERREUR

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use ApplicationBundle\Entity\Utilisateur;
use ApplicationBundle\Entity\Fraction;
use ApplicationBundle\Entity\Mouvement;
use Doctrine\ORM\NoResultException;

//CONTENU FORMULAIRE
use Symfony\Component\Form\Button;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;


//POUR ENCODAGE PASSWORD
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

//POUR LES SESSIONS

use Symfony\Component\HttpFoundation\Session\Session;

class UserController extends Controller
{
    public function connexionAction(Request $request)
    {
        $error = null; // pour éviter le "undefined variable error"

        $user = new Utilisateur(); //création d' un objet utilisater vide

        $formBuilder = $this->get('form.factory')->createBuilder(FormType::class, $user); // Initisalisation du form builder

        //CREATION DU FORMULAIRE
        $formBuilder
            ->add('email', EmailType::class, ['label'=> false, 'attr' => ['placeholder' => "Adresse e-mail"]])
            ->add('motDePasseClair', PasswordType::class, ['label'=> false, 'attr' => ['placeholder' => "Mot de Passe"]])
            ->add('Se connecter', SubmitType::class, ['attr' => ['class'=> 'btn btn-primary']]);

        $form = $formBuilder->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {
            $user = $form->getData();
            $motDePasseSaisie = $user->getMotDePasseClair();
            $manager = $this->getDoctrine()->getManager();
            $repositoryUsers = $manager->getRepository('ApplicationBundle:Utilisateur');
            //VERIFICATION HASH PASSWORD
            $passwordEncoder = $this->container->get('security.password_encoder');
            //Récupération du mit de passe crypté
            $hashedPassword = $repositoryUsers->findByEmail($user->getEmail()/*, $user->getMotDePasseClair()*/);

            //verification du resultat de la requete
            if ($hashedPassword != "NoResultException")
            {
                $user->setMotDePasse($hashedPassword["motDePasse"]);
            }
            else
            {
                $error = "NoResultException";

            }
            //Verification du mot de passe récupéré avec celui saisie
            if ($passwordEncoder->isPasswordValid($user, $motDePasseSaisie))
            {
                 $user = $repositoryUsers->findOneByEmail($user->getEmail());
                 $error = "NoError";

                 //passage de l'utilisateur dans une session
                 $session = new session();
                 $session->set('id', $user->getId());
                 $session->set('prenom', $user->getPrenom());
                 $session->set('email', $user->getEmail());
                 $session->set('budgetGlobal', $user->getBudgetGlobal());
                 $session->set('utilisateur', $user);

                 return $this->redirectToRoute('application_homepage');
            }
            else
            {
                $error = "NoResultException";
            }
        }

        return $this->render('@Application/User/connexion.html.twig', ['form'=> $form->createView(), 'utilisateur' => $user, 'error' => $error]);
    }

    public function deconnexionAction(Request $request)
    {
        $session = $request->getSession();
        $session->invalidate();

        return $this->redirectToRoute('connexion');
    }

    public function inscriptionAction(Request $request)
    {
        $error = null;

        $user = new Utilisateur();

        $date = new \DateTime();
        $date->format('\O\n Y-m-d');

        $formBuilder = $this->get('form.factory')->createBuilder(FormType::class, $user);

        $formBuilder
            ->add('nom', TextType::class, ['label'=> false, 'attr' => ['placeholder'=> "Nom"]])
            ->add('prenom', TextType::class, ['label'=> false, 'attr' => ['placeholder'=> "Prenom"]])
            ->add('email', EmailType::class, ['label'=> false, 'attr' => ['placeholder'=> "Adresse e-mail"]])
            ->add('dateNaiss', BirthdayType::class, ['label'=> "Date de naissance : ", 'format' => 'dd-MM-yyyy'])
            ->add('MotDePasseClair', RepeatedType::class, ['type' => PasswordType::class, 'first_options' => ['label'=> "Mot de passe", 'attr' => ['placeholder' => "Mot de Passe"]], 'second_options' => ['label'=> "Répetez mot de passe", 'attr' => ['placeholder' => "Vérification"]]])
            ->add('budgetGlobal', MoneyType::class , ['label' => false, 'currency' => null,'scale' => 4, 'attr'=>['placeholder' => "Solde disponible"]])
            ->add('Inscription', SubmitType::class, ['attr' => ['class'=> 'btn btn-primary']] );



        $form = $formBuilder->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {
            $user = $form->getData();
            //HASHAGE
            $passwordEncoder = $this->get('security.password_encoder');
            $motDePasse = $passwordEncoder->encodePassword($user, $user->getMotDePasseClair());
            $user->setMotDePasse($motDePasse);

            $manager = $this->getDoctrine()->getManager();
            $repositoryUsers = $manager->getRepository('ApplicationBundle:Utilisateur');
            //CREATION D'UNE FRACTION PAR DEFAUT POUR LE BUDGET RESTANT
            $repositoryFraction = $manager->getRepository('ApplicationBundle:Fraction');
            $fractionBudgetRestant = new Fraction();
            $fractionBudgetRestant->setNom("Budget Restant");
            $fractionBudgetRestant->setMontant($user->getBudgetGlobal());
            $fractionBudgetRestant->setCouleur("gray");
            $fractionBudgetRestant->setPriorite(5);
            $fractionBudgetRestant->setUtilisateur($user);

            //CREATION D'UN MOUVEMENT POUR LA PARTITION BUDGET RESTANT
            $repositoryMouvement = $manager->getRepository('ApplicationBundle:Mouvement');
            $mouvementBudgetRestant = new Mouvement();
            $mouvementBudgetRestant->setNom('Budget Restant');
            $mouvementBudgetRestant->setMontant($fractionBudgetRestant->getMontant());
            $mouvementBudgetRestant->setType('Sortie');
            $mouvementBudgetRestant->setDate($date);
            $mouvementBudgetRestant->setFraction($fractionBudgetRestant->getId());

            $manager->persist($user);
            $manager->persist($fractionBudgetRestant);
            $manager->persist($mouvementBudgetRestant);

            try
            {
                $manager->flush();
                return $this->redirectToRoute('connexion');
            }
            catch (PDOException $e)
            {
                $error = "UniqueConstraintViolationException";
            }
            catch (UniqueConstraintViolationException $e)
            {
                $error = "UniqueConstraintViolationException";

            }
        }

        return $this->render('@Application/User/inscription.html.twig', ['form'=> $form->createView(), 'error'=> $error]);
    }

    public function reinitialisationMotDePasseAction(Request $request)
    {
        $error = null;

        $manager = $this->getDoctrine()->getManager();
        $repositoryUsers = $manager->getRepository('ApplicationBundle:Utilisateur');

        $user = new Utilisateur();

        $formBuilder = $this->get('form.factory')->createBuilder(FormType::class, $user);

        $formBuilder
                    ->add('email', EmailType::class, ['label'=> false, 'attr' => ['placeholder'=> "Veuillez saisir votre adresse e-mail"]])
                    ->add('réinitialiser', SubmitType::class, ['attr' => ['class'=> 'btn btn-primary']] );

        $form = $formBuilder->getForm();

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid())
        {

            $user = $form->getData();
            //HASHAGE
            $passwordEncoder = $this->get('security.password_encoder');

            //GENERATION MOT DE PASSE ALEATOIRE
            $randomPassword = random_bytes(10);
            $randomPassword = base64_encode($randomPassword); //codage en base 64 de la chaine générée plus haut

            //HASHAGE DU PASSWORD
            $motDePasse = $passwordEncoder->encodePassword($user, $randomPassword);//encodage du mot de passe généré

            //recuperation de l'utilisateur
            try
            {
                $user = $repositoryUsers->findOneByEmail($user->getEmail());
                if ($user != NULL)
                {
                    $emailUser = $user->getemail();
                }
            }
            catch (NoResultException $e)
            {
                $error = "NoResultException";
            }

            if ($user != NULL)
            {
                //ENVOI DU MOT DE PASSE GENERE PAR EMAIL

                $transport = (new \Swift_SmtpTransport('smtp.gmail.com', 587))
                            ->setUsername('moneytoring.iutbayonne@gmail.com')
                            ->setPassword('moneytoring1997')
                            ->setEncryption('tls')
                                ;
                $mailer = new \Swift_Mailer($transport);

                $message = (new \Swift_Message('Réinitialisation du mot de passe MONEYTORING.'))
                            ->setFrom('moneytoring.iutbayonne@gmail.com')
                            ->setTo($emailUser)
                            ->setBody('Mot de passe généré : '. $randomPassword)
                            ;
                $result = $mailer->send($message);

                //APPLICATION DU NOUVEAU MOT DE PASSE :
                $user->setMotDePasse($motDePasse);

                $manager->persist($user);
                $manager->flush();

                $error = "NoError";
            }
            else
            {
                $error = "NoResultException";
            }

        }

        return $this->render('@Application/User/reinitialisation.html.twig', ['form'=> $form->createView(), 'error' => $error]);

    }

    public function parametresUtilisateurAction(Request $request)
    {
        //VERIFICATION DE CONNEXION
        $session = $request->getSession();
        $id = $session->get('id');
        $prenom = $session->get('prenom');
        $email = $session->get('email');
         if ($id == null)
        {
            $error = "ConnexionNeeded";
        }
        else
        {
            $error = null;
        }

        $user = new Utilisateur();
        $utilisateur = null;

        $formBuilder = $this->get('form.factory')->createBuilder(FormType::class, $user);

        $formBuilder
                ->add('MotDePasseClair', RepeatedType::class, ['type' => PasswordType::class, 'first_options' => ['label'=> "Mot de passe", 'attr' => ['placeholder' => "Mot de Passe"]], 'second_options' => ['label'=> "Répetez mot de passe", 'attr' => ['placeholder' => "Vérification"]]])
                ->add('Changer mot de passe', SubmitType::class, ['attr' => ['class'=> 'btn btn-primary']]);
        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        $manager = $this->getDoctrine()->getManager();
        $repositoryUsers = $manager->getRepository('ApplicationBundle:Utilisateur');

        if ($form->isSubmitted() && $form->isValid())
        {
          $user = $form->getData();

          $password = $user->getMotDePasseClair();

          //encodage du mot de passe :
          $passwordEncoder = $this->get('security.password_encoder');
          $motDePasse = $passwordEncoder->encodePassword($user, $password);

          $utilisateur = $repositoryUsers->findOneByEmail($email);
          $utilisateur->setMotDePasse($motDePasse);

            try
            {
                $manager->flush();
                return $this->redirectToRoute('connexion');
            }
            catch (PDOException $e)
            {
                $error = "UniqueConstraintViolationException";
            }
            catch (UniqueConstraintViolationException $e)
            {
                $error = "UniqueConstraintViolationException";

            }


        }

        $formBuilder2 = $this->get('form.factory')->createBuilder(FormType::class, $user);
        $formBuilder2
              ->add('email', EmailType::class, ['label'=> false, 'attr' => ['placeholder'=> "Veuillez saisir votre adresse e-mail"]])
              ->add('Changer adresse email', SubmitType::class, ['attr' => ['class'=> 'btn btn-primary']]);
        $formChangementMail = $formBuilder2->getForm();
        $formChangementMail->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
          $user = $form->getData();

          $utilisateur = $repositoryUsers->findOneById($id);

          try
          {
              $manager->flush();
              return $this->redirectToRoute('connexion');
          }
          catch (PDOException $e)
          {
              $error = "UniqueConstraintViolationException";
          }
          catch (UniqueConstraintViolationException $e)
          {
              $error = "UniqueConstraintViolationException";

          }


        }

        return $this->render('@Application/User/parametresUtilisateur.html.twig', ['form'=> $form->createView(), 'formChangementMail' => $formChangementMail->createView(),'prenom' => $prenom, 'error'=> $error, 'user'=>$utilisateur]);
    }
}
