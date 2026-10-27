<?php

declare(strict_types=1);

namespace App\Tests\Metadata;

use App\Entity\CollectionFieldConfig;
use App\Entity\EntityConfiguration;
use App\Entity\FormFieldConfig;
use App\Entity\ListConfiguration;
use App\Metadata\ConfiguracionesTocadas;
use App\Metadata\VigilanteConfiguracion;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;

final class VigilanteConfiguracionTest extends TestCase
{
    /**
     * @param list<object> $insertadas
     * @param list<object> $actualizadas
     * @param list<object> $eliminadas
     */
    private function flush(ConfiguracionesTocadas $tocadas, array $insertadas = [], array $actualizadas = [], array $eliminadas = []): void
    {
        $uow = $this->createStub(UnitOfWork::class);
        $uow->method("getScheduledEntityInsertions")->willReturn($insertadas);
        $uow->method("getScheduledEntityUpdates")->willReturn($actualizadas);
        $uow->method("getScheduledEntityDeletions")->willReturn($eliminadas);
        $uow->method("getScheduledCollectionUpdates")->willReturn([]);
        $uow->method("getScheduledCollectionDeletions")->willReturn([]);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method("getUnitOfWork")->willReturn($uow);

        (new VigilanteConfiguracion($tocadas))->onFlush(new OnFlushEventArgs($em));
    }

    public function testAnotaLaEntidadDeCadaConfiguracionGuardada(): void
    {
        $bus = new EntityConfiguration("Bus");
        $columna = (new CollectionFieldConfig(["codigo", "text"]))->setListConfig(new ListConfiguration($bus, "Por defecto"));
        $campo = (new FormFieldConfig(["piloto", "select", "Piloto"]))->setEntityConfig(new EntityConfiguration("Piloto"));
        $tocadas = new ConfiguracionesTocadas();

        $this->flush($tocadas, insertadas: [$columna, new \stdClass()], actualizadas: [$bus], eliminadas: [$campo]);
        $this->flush($tocadas, actualizadas: [$bus]);

        $this->assertSame(["Bus", "Piloto"], $tocadas->tomar(), "una vez cada una, en todos los flush de la petición");
        $this->assertFalse($tocadas->hay(), "tomarlas las olvida");
    }

    public function testOtrasEntidadesNoCuentan(): void
    {
        $tocadas = new ConfiguracionesTocadas();
        $this->flush($tocadas, insertadas: [new \stdClass()]);
        $this->assertFalse($tocadas->hay());
        $this->assertSame([], $tocadas->tomar());
    }

    public function testSinSaberDeQueEntidadEsSeRevisanTodas(): void
    {
        $tocadas = new ConfiguracionesTocadas();
        $this->flush($tocadas, insertadas: [new FormFieldConfig(["nombre", "text"])]);
        $this->assertTrue($tocadas->hay());
        $this->assertNull($tocadas->tomar());

        $tocadas->anotar("Bus");
        $tocadas->reset();
        $this->assertFalse($tocadas->hay(), "al terminar la petición (modo worker) no queda nada para la siguiente");
    }
}
