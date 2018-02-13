<?php
namespace App\DataFixtures;

use ApplicationBundle\Entity\Utilisateur;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
public function load(ObjectManager $manager)
{

$utilisateur = new Utilisateur();
$utilisateur->setNom('Chinnapong');
$utilisateur->setPrenom('Didier');
$utilisateur->setMotDePasse('couille');
$utilisateur->setEmail('didier.chinnapong@gmail.com');
$utilisateur->setDateNaiss(new \DateTime('03/10/1997'));
$utilisateur->setBudgetGlobal(0);
$utilisateur->setDaltonisme(false);
$utilisateur->setAdmin(false);

}

$manager->flush();
}
}