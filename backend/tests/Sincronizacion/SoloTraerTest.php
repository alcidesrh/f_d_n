<?php

declare(strict_types=1);

namespace App\Tests\Sincronizacion;

use App\Sincronizacion\Legado\ConexionLegado;
use App\Sincronizacion\Legado\InstaladorLegado;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SoloTraerTest extends TestCase
{
    public function testLaInstalacionYLasTablasSyncPasan(): void
    {
        $dir = dirname(__DIR__, 2) . "/src/Sincronizacion/Legado/sql";
        foreach (["instalar.sql", "desinstalar.sql"] as $archivo) {
            foreach (InstaladorLegado::lotes((string) file_get_contents("{$dir}/{$archivo}")) as $lote) {
                ConexionLegado::exigirSoloSync($lote);
            }
        }
        ConexionLegado::exigirSoloSync("UPDATE sync_cambio SET procesado = GETDATE() WHERE id IN (1)");
        ConexionLegado::exigirSoloSync("DELETE FROM sync_cambio WHERE procesado < DATEADD(day, -7, GETDATE())");
        ConexionLegado::exigirSoloSync("UPDATE sync_config SET activo = 0 WHERE id = 1");
        $this->addToAssertionCount(1);
    }

    /** @return iterable<array{string}> */
    public static function escriturasDelNegocio(): iterable
    {
        yield ["SET NOCOUNT ON; DECLARE @ids TABLE (id BIGINT); INSERT INTO [boleto] ([a]) OUTPUT INSERTED.id INTO @ids VALUES (:a)"];
        yield ["UPDATE boleto SET estado_id = 4 WHERE id = 1"];
        yield ["update dbo.encomienda set x = 1"];
        yield ["DELETE FROM [dbo].[cliente] WHERE id = 1"];
        yield ["INSERT boleto_bitacora (a) VALUES (1)"];
    }

    #[DataProvider("escriturasDelNegocio")]
    public function testNoSeEscribeEnTablasDelLegado(string $sql): void
    {
        $this->expectException(\LogicException::class);
        ConexionLegado::exigirSoloSync($sql);
    }
}
