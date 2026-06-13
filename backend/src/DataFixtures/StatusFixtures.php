<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Status;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class StatusFixtures extends Fixture
{
    public const STATUS_ACTIVO = 'status-activo';

    public function load(ObjectManager $manager): void
    {
        $status = new Status();
        $status->setNombre('Activo');
        $manager->persist($status);

        $this->addReference(self::STATUS_ACTIVO, $status);

        $manager->flush();
    }
}
