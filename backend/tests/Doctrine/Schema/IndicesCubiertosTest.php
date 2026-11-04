<?php

declare(strict_types=1);

namespace App\Tests\Doctrine\Schema;

use App\Doctrine\Schema\IndicesCubiertos;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;

final class IndicesCubiertosTest extends TestCase
{
    public function testSobraElSimpleQueOtroCubrePorLaIzquierda(): void
    {
        $t = self::tabla("salida_parada", ["id", "salida_id", "orden", "enclave_id", "hora"]);
        $t->addIndex(["salida_id"], "idx_salida");
        $t->addUniqueIndex(["salida_id", "orden"], "uq_salida_orden");
        $t->addIndex(["enclave_id"], "idx_enclave");
        $t->addIndex(["enclave_id", "hora"], "idx_enclave_hora");

        $this->assertEqualsCanonicalizing(["idx_salida", "idx_enclave"], IndicesCubiertos::cubiertos($t));
    }

    public function testLaClavePrimariaTambienCubre(): void
    {
        $t = self::tabla("lectura_gps", ["bus_id", "instante"]);
        $t->setPrimaryKey(["bus_id", "instante"]);
        $t->addIndex(["bus_id"], "idx_bus");

        $this->assertSame(["idx_bus"], IndicesCubiertos::cubiertos($t));
    }

    public function testNoTocaUnicosNiParcialesNiLosQueNoSonPrefijo(): void
    {
        $t = self::tabla("x", ["a", "b", "c"]);
        $t->addUniqueIndex(["a"], "uq_a");
        $t->addIndex(["a", "b"], "idx_ab");
        $t->addIndex(["b"], "idx_b_parcial", [], ["where" => "c IS NULL"]);
        $t->addIndex(["b", "a"], "idx_ba");
        $t->addIndex(["c"], "idx_c");
        $t->addIndex(["a", "c"], "idx_ac");

        $this->assertSame([], IndicesCubiertos::cubiertos($t), "el único, el parcial, el de igual largo y los que no son prefijo se quedan");
    }

    /** @param list<string> $columnas */
    private static function tabla(string $nombre, array $columnas): Table
    {
        $t = new Table($nombre);
        foreach ($columnas as $c) {
            $t->addColumn($c, "integer");
        }

        return $t;
    }
}
