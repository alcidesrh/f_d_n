<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\BuscadorOpciones;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;

final class BuscadorOpcionesTest extends TestCase
{
    public function testPalabrasSinVaciosNiRepetidosNiFlecha(): void
    {
        self::assertSame(['guate', 'flor'], BuscadorOpciones::palabras('  guate  →  flor guate '));
        self::assertSame([], BuscadorOpciones::palabras('   '));
    }

    public function testPalabrasAcotadasACinco(): void
    {
        self::assertCount(5, BuscadorOpciones::palabras('a b c d e f g'));
    }

    public function testCamposSoloTextoYSinSecretos(): void
    {
        $metadata = new ClassMetadata(\stdClass::class);
        $metadata->mapField(['fieldName' => 'id', 'type' => 'integer', 'id' => true]);
        $metadata->mapField(['fieldName' => 'nombre', 'type' => 'string']);
        $metadata->mapField(['fieldName' => 'notas', 'type' => 'text']);
        $metadata->mapField(['fieldName' => 'password', 'type' => 'string']);
        $metadata->mapField(['fieldName' => 'apiToken', 'type' => 'string']);
        $metadata->mapField(['fieldName' => 'activo', 'type' => 'boolean']);

        self::assertSame(['r0.nombre', 'r0.notas'], BuscadorOpciones::campos($metadata, 'r0'));
    }
}
