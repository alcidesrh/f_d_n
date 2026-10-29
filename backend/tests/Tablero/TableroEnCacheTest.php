<?php

declare(strict_types=1);

namespace App\Tests\Tablero;

use App\Programador\RegistroTareas;
use App\Tablero\ConsultaTablero;
use App\Tablero\TableroEnCache;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;

/** El tablero se sirve de una copia que la tarea rehace mientras alguien lo mira. */
final class TableroEnCacheTest extends TestCase
{
    private MockClock $reloj;
    private ArrayAdapter $cache;
    /** Veces que se calculó: una consulta de movimientos por cálculo. */
    private int $calculos = 0;

    protected function setUp(): void
    {
        $this->reloj = new MockClock("2026-10-29 10:00:00");
        $this->cache = new ArrayAdapter();
        $this->calculos = 0;
    }

    private function tablero(): TableroEnCache
    {
        $db = $this->createStub(Connection::class);
        $db->method("fetchAllAssociative")->willReturnCallback(function (string $sql) {
            if (str_contains($sql, "WITH m AS")) {
                $this->calculos++;
            }

            return [];
        });
        $db->method("fetchAssociative")->willReturn(false);
        $db->method("fetchOne")->willReturn(0);

        return new TableroEnCache(new ConsultaTablero($db, new RegistroTareas($db, $this->reloj)), $this->cache, $this->reloj);
    }

    public function testLaCopiaSirveMientrasEsFrescaYDeHoy(): void
    {
        $t = $this->tablero();

        $primera = $t->leer(7, null);
        $this->reloj->sleep(TableroEnCache::FRESCA - 1);
        $this->assertSame($primera, $t->leer(7, null), "sin calcular otra vez");
        $this->assertSame(1, $this->calculos);

        $this->reloj->sleep(2);
        $t->leer(7, null);
        $this->assertSame(2, $this->calculos, "vieja: se calcula en la petición");
    }

    public function testUnaCopiaPorPeriodoYEmpresa(): void
    {
        $t = $this->tablero();

        $t->leer(7, null);
        $t->leer(30, null);
        $t->leer(7, 3);
        $t->leer(7, 3);

        $this->assertSame(3, $this->calculos);
    }

    public function testLaTareaRehaceLoQueSeMiraYOlvidaLoDemas(): void
    {
        $t = $this->tablero();
        $t->leer(7, null);
        $this->reloj->sleep(TableroEnCache::INTERES - 300);
        $t->leer(7, 3);
        $this->calculos = 0;

        $this->assertSame(2, $t->refrescar());
        $this->assertSame(2, $this->calculos);

        // La de todas las empresas nadie la mira desde hace más de INTERES.
        $this->reloj->sleep(301);
        $this->calculos = 0;
        $this->assertSame(1, $t->refrescar());
        $this->assertSame(1, $this->calculos);
        $this->assertFalse($this->cache->hasItem(TableroEnCache::clave(7, null)), "olvidada");
    }

    public function testLaCopiaDeAyerNoSirve(): void
    {
        $ayer = ["calculado" => (new \DateTimeImmutable("2026-10-28 23:59:30"))->getTimestamp(), "dia" => "2026-10-28", "datos" => []];

        $this->assertFalse(TableroEnCache::sirve($ayer, new \DateTimeImmutable("2026-10-29 00:00:30")));
        $this->assertFalse(TableroEnCache::sirve(null, new \DateTimeImmutable("2026-10-29 00:00:30")));
    }
}
