<?php

declare(strict_types=1);

namespace App\Tests\Sincronizacion;

use App\Sincronizacion\Legado\InstaladorLegado;
use PHPUnit\Framework\TestCase;

final class InstaladorLegadoTest extends TestCase
{
    public function testLosLotesSeSeparanPorGoComoEnSqlcmd(): void
    {
        $lotes = InstaladorLegado::lotes("-- comentario\nCREATE TABLE a (id INT);\nGO\n\n  go  \nCREATE TRIGGER t ON a AFTER INSERT AS\nBEGIN\n  SELECT 'GO';\nEND\nGO\n-- solo comentario\nGO\n");
        $this->assertCount(2, $lotes);
        $this->assertStringContainsString("CREATE TABLE a", $lotes[0]);
        $this->assertStringContainsString("SELECT 'GO';", $lotes[1], "un GO dentro de una línea no corta");
    }

    public function testLosScriptsDelModuloSonValidos(): void
    {
        $dir = dirname(__DIR__, 2) . "/src/Sincronizacion/Legado/sql";
        $instalar = InstaladorLegado::lotes((string) file_get_contents("{$dir}/instalar.sql"));
        $desinstalar = InstaladorLegado::lotes((string) file_get_contents("{$dir}/desinstalar.sql"));
        foreach (["trg_sync_boleto", "trg_sync_encomienda", "trg_sync_encomienda_bitacora", "trg_sync_salida", "trg_sync_factura_generada"] as $trigger) {
            $this->assertNotEmpty(array_filter($instalar, static fn(string $l) => str_contains($l, "CREATE TRIGGER dbo.{$trigger}")), "instala {$trigger}");
            $this->assertNotEmpty(array_filter($desinstalar, static fn(string $l) => str_contains($l, "DROP TRIGGER dbo.{$trigger}")), "desinstala {$trigger}");
        }
        foreach ($instalar as $lote) {
            // Un INSERT con IDENTITY dentro de un trigger cambia @@IDENTITY, que el
            // legado lee como id de la fila que insertó: sync_cambio usa una secuencia.
            $this->assertStringNotContainsString("IDENTITY(", $lote, "sync_cambio sin IDENTITY");
            if (str_contains($lote, "CREATE TRIGGER")) {
                $this->assertStringContainsString("0x46444E53594E43", $lote, "todo trigger ignora lo que escribe FDN");
            }
        }
    }
}
