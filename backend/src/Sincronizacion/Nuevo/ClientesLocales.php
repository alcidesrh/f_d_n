<?php

declare(strict_types=1);

namespace App\Sincronizacion\Nuevo;

use App\Sincronizacion\Legado\ConexionLegado;
use Doctrine\DBAL\Connection;

/**
 * Clientes creados aquí sin el legado (ADR-042): con la sincronización apagada
 * el id sale de la secuencia de aquí, y allá ese id puede ser de otra persona.
 * Se anotan en `sync_cliente_local`; al encender se crean en el legado y pasan
 * a su id, con todo lo que los referencia. Hasta entonces no se importa nada.
 *
 * Un solo proceso a la vez (bloqueo consultivo). Si falla a medias, lo que
 * faltó sigue anotado y lo retoma el ciclo.
 */
class ClientesLocales
{
    private const BLOQUEO = 4204203;

    /** Columnas que apuntan a `cliente`. */
    private const REFERENCIAS = [
        ["boleto_asiento", "cliente_id"],
        ["boleto_venta", "cliente_id"],
        ["encomienda", "remitente_id"],
        ["encomienda", "destinatario_id"],
        ["encomienda", "receptor_id"],
        ["encomienda", "cliente_factura_id"],
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly EscritorLegado $escritor,
        private readonly ConexionLegado $legado,
    ) {}

    public function anotar(int $id): void
    {
        $this->db->executeStatement("INSERT INTO sync_cliente_local (cliente_id) VALUES (:id) ON CONFLICT DO NOTHING", ["id" => $id]);
    }

    public function pendientes(): int
    {
        return (int) $this->db->fetchOne("SELECT COUNT(*) FROM sync_cliente_local");
    }

    /**
     * Lleva al legado los clientes anotados. En modo «solo traer» no se
     * escribe allá: quedan como están.
     *
     * @return int cuántos quedan sin llevar
     */
    public function realinear(): int
    {
        if ($this->legado->soloTraer || !$this->db->fetchOne("SELECT pg_try_advisory_lock(:k)", ["k" => self::BLOQUEO])) {
            return $this->pendientes();
        }
        try {
            foreach ($this->db->fetchFirstColumn("SELECT cliente_id FROM sync_cliente_local ORDER BY cliente_id") as $id) {
                $this->llevar((int) $id);
            }
        } finally {
            $this->db->executeStatement("SELECT pg_advisory_unlock(:k)", ["k" => self::BLOQUEO]);
        }

        return $this->pendientes();
    }

    /**
     * Apagada, la secuencia de aquí vuelve a dar ids: desde el mayor conocido.
     * Si el legado no responde, desde el mayor de aquí; lo que llegue a chocar
     * se resuelve al encender.
     */
    public function alinearSecuencia(): void
    {
        try {
            $legado = (int) $this->legado->valor("SELECT IDENT_CURRENT('cliente')");
        } catch (\Throwable) {
            $this->legado->cerrar();
            $legado = 0;
        }
        $this->db->executeStatement(
            "SELECT setval(pg_get_serial_sequence('cliente', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM cliente), 0), :legado) + 1, false)",
            ["legado" => $legado],
        );
    }

    private function llevar(int $id): void
    {
        $fila = $this->db->fetchAssociative(
            "SELECT nombre, apellido, nit, email, telefono, direccion, numero_documento, tipo_documento_id, nacionalidad_id FROM cliente WHERE id = :id",
            ["id" => $id],
        );
        if ($fila === false) {
            $this->db->executeStatement("DELETE FROM sync_cliente_local WHERE cliente_id = :id", ["id" => $id]);

            return;
        }
        $legado = $this->escritor->altaCliente(EscritorLegado::datosDeFila($fila));
        $this->db->transactional(function () use ($id, $legado): void {
            if ($legado !== $id) {
                $this->mudar($id, $legado);
            }
            $this->db->executeStatement("DELETE FROM sync_cliente_local WHERE cliente_id = :id", ["id" => $id]);
        });
    }

    /** Si el legado ya tenía esa persona y está aquí, las dos quedan en una. */
    private function mudar(int $id, int $nuevo): void
    {
        // Bloqueada: una venta en curso con este cliente espera y falla, no queda apuntando al viejo.
        $this->db->fetchOne("SELECT id FROM cliente WHERE id = :id FOR UPDATE", ["id" => $id]);
        $this->db->executeStatement(
            "INSERT INTO cliente SELECT (jsonb_populate_record(NULL::cliente, to_jsonb(c) || jsonb_build_object('id', CAST(:nuevo AS int)))).*
               FROM cliente c WHERE c.id = :id ON CONFLICT (id) DO NOTHING",
            ["id" => $id, "nuevo" => $nuevo],
        );
        foreach (self::REFERENCIAS as [$tabla, $columna]) {
            $this->db->executeStatement("UPDATE {$tabla} SET {$columna} = :nuevo WHERE {$columna} = :id", ["id" => $id, "nuevo" => $nuevo]);
        }
        $this->db->executeStatement("DELETE FROM cliente WHERE id = :id", ["id" => $id]);
    }
}
