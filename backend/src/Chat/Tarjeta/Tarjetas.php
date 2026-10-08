<?php

declare(strict_types=1);

namespace App\Chat\Tarjeta;

use App\Chat\ChatRechazado;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Convierte los adjuntos `{tipo, id}` de los mensajes en tarjetas para quien
 * lee: una consulta por tipo para todos los mensajes de la página, y cada
 * tarjeta con su estado (`ok`, `sin_acceso`, `no_existe`). El acceso se mira
 * con los permisos del lector, no del autor: compartir no abre permisos.
 */
final class Tarjetas
{
    public const MAXIMO = 20;

    /** @var array<string, Tarjeta> */
    private array $propias = [];

    /** @param iterable<Tarjeta> $tarjetas */
    public function __construct(
        #[AutowireIterator("app.chat.tarjeta")] iterable $tarjetas,
        private readonly TarjetaGenerica $generica,
    ) {
        foreach ($tarjetas as $t) {
            $this->propias[$t->tipo()] = $t;
        }
    }

    /**
     * Valida los adjuntos de un mensaje nuevo: tipo conocido, que existan y
     * que el autor los pueda ver.
     *
     * @return list<array{tipo: string, id: int}>
     */
    public function normalizar(mixed $adjuntos): array
    {
        if (!is_array($adjuntos)) {
            return [];
        }
        $unicos = [];
        foreach ($adjuntos as $a) {
            $tipo = is_array($a) ? (string) ($a["tipo"] ?? "") : "";
            $id = is_array($a) ? filter_var($a["id"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) : false;
            if ($id === false || !$this->conocido($tipo)) {
                throw new ChatRechazado("Adjunto no válido.");
            }
            $unicos["$tipo:$id"] = ["tipo" => $tipo, "id" => $id];
        }
        if (count($unicos) > self::MAXIMO) {
            throw new ChatRechazado(sprintf("Se pueden enviar hasta %d registros por mensaje.", self::MAXIMO));
        }
        foreach ($this->presentar(array_values($unicos)) as $t) {
            if ($t["estado"] !== "ok") {
                throw new ChatRechazado(sprintf("No puede compartir %s %d.", $t["tipo"], $t["id"]), 403);
            }
        }

        return array_values($unicos);
    }

    /**
     * Lo que este usuario puede compartir (el selector de registros del
     * chat): primero los tipos con tarjeta propia, luego el resto por nombre.
     *
     * @return list<array{tipo: string, propia: bool}>
     */
    public function compartibles(): array
    {
        $tipos = array_values(array_unique([...array_keys($this->propias), ...$this->generica->tipos()]));
        $lista = [];
        foreach ($tipos as $tipo) {
            if ($this->puedeVer($tipo)) {
                $lista[] = ["tipo" => $tipo, "propia" => isset($this->propias[$tipo])];
            }
        }

        return $lista;
    }

    /**
     * @param list<array{tipo: string, id: int}> $adjuntos
     *
     * @return list<array<string, mixed>>
     */
    public function presentar(array $adjuntos): array
    {
        return $this->presentarVarios([0 => $adjuntos])[0];
    }

    /**
     * @param array<int, list<array{tipo: string, id: int}>> $porMensaje
     *
     * @return array<int, list<array<string, mixed>>> mismas claves
     */
    public function presentarVarios(array $porMensaje): array
    {
        $ids = [];
        foreach ($porMensaje as $adjuntos) {
            foreach ($adjuntos as $a) {
                $ids[$a["tipo"]][$a["id"]] = $a["id"];
            }
        }
        $datos = $acceso = [];
        foreach ($ids as $tipo => $lista) {
            $acceso[$tipo] = $this->conocido($tipo) && $this->puedeVer($tipo);
            $datos[$tipo] = $acceso[$tipo] ? $this->resolver($tipo, array_values($lista)) : [];
        }

        return array_map(static fn(array $adjuntos) => array_map(static fn(array $a) => [
            "tipo" => $a["tipo"],
            "id" => $a["id"],
            "estado" => match (true) {
                !$acceso[$a["tipo"]] => "sin_acceso",
                !isset($datos[$a["tipo"]][$a["id"]]) => "no_existe",
                default => "ok",
            },
            "datos" => $datos[$a["tipo"]][$a["id"]] ?? null,
        ], $adjuntos), $porMensaje);
    }

    private function conocido(string $tipo): bool
    {
        return isset($this->propias[$tipo]) || $this->generica->existe($tipo);
    }

    private function puedeVer(string $tipo): bool
    {
        return isset($this->propias[$tipo]) ? $this->propias[$tipo]->puedeVer() : $this->generica->puedeVer($tipo);
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolver(string $tipo, array $ids): array
    {
        return isset($this->propias[$tipo]) ? $this->propias[$tipo]->resolver($ids) : $this->generica->resolver($tipo, $ids);
    }
}
