<?php

namespace App\Tests\Chat;

use App\Entity\ChatCanal;
use App\Entity\ChatMensaje;
use App\Entity\Enum\ModoGrupoChat;
use App\Entity\Usuario;
use PHPUnit\Framework\TestCase;

/** Reglas de los grupos del chat (entidades en memoria): quién escribe y quién llega por un grupo miembro. */
final class GruposChatTest extends TestCase
{
    private static function usuario(int $id): Usuario
    {
        $u = new Usuario();
        (new \ReflectionProperty(Usuario::class, "id"))->setValue($u, $id);

        return $u;
    }

    /** @return list<int> */
    private static function ids(ChatCanal $c, bool $directos = false): array
    {
        $ids = [];
        foreach ($c->getMiembros() as $m) {
            if (!$directos || $m->esDirecto()) {
                $ids[] = (int) $m->getUsuario()->getId();
            }
        }
        sort($ids);

        return $ids;
    }

    public function testEnUnAbiertoEscribenTodosLosMiembros(): void
    {
        [$dueno, $ana, $fuera] = [self::usuario(1), self::usuario(2), self::usuario(3)];
        $g = ChatCanal::grupo("Coordinación", $dueno, [$ana]);

        $this->assertSame(ModoGrupoChat::Abierto, $g->getModo());
        $this->assertTrue($g->puedeEscribir($ana));
        $this->assertFalse($g->puedeEscribir($fuera));
        $this->assertTrue($g->administra($dueno));
        $this->assertFalse($g->administra($ana));
    }

    public function testEnUnDirigidoEscribenElDuenoYLosEmisores(): void
    {
        [$dueno, $ana, $beto] = [self::usuario(1), self::usuario(2), self::usuario(3)];
        $g = ChatCanal::grupo("Avisos", $dueno, [$ana, $beto], ModoGrupoChat::Dirigido);
        $g->miembro($ana)->setEmisor(true);

        $this->assertTrue($g->puedeEscribir($dueno));
        $this->assertTrue($g->puedeEscribir($ana));
        $this->assertFalse($g->puedeEscribir($beto));
    }

    public function testLosMiembrosDeUnGrupoMiembroLleganPorEl(): void
    {
        [$dueno, $ana, $beto, $carla] = [self::usuario(1), self::usuario(2), self::usuario(3), self::usuario(4)];
        $peten = ChatCanal::grupo("Petén", $beto, [$carla]);
        $g = ChatCanal::grupo("Avisos", $dueno, [$ana], ModoGrupoChat::Dirigido);

        $g->agregarSubgrupo($peten);
        $g->sincronizar(50);

        $this->assertSame([1, 2, 3, 4], self::ids($g));
        $this->assertSame([1, 2], self::ids($g, directos: true));
        $this->assertSame($peten, $g->miembro($carla)->getVia());
        $this->assertSame(50, $g->miembro($carla)->getUltimoLeido(), "lo anterior a su llegada no le cuenta como no leído");
        $this->assertFalse($g->puedeEscribir($carla));

        $g->agregarSubgrupo($peten, emisor: true);
        $this->assertTrue($g->puedeEscribir($carla), "el grupo miembro emisor escribe");
    }

    public function testQuienSaleDelGrupoMiembroSaleDelOtro(): void
    {
        [$dueno, $beto, $carla] = [self::usuario(1), self::usuario(3), self::usuario(4)];
        $peten = ChatCanal::grupo("Petén", $beto, [$carla]);
        $g = ChatCanal::grupo("Avisos", $dueno, []);
        $g->agregarSubgrupo($peten);
        $g->sincronizar();

        $peten->quitar($carla);
        $g->sincronizar();
        $this->assertSame([1, 3], self::ids($g));

        $g->quitarSubgrupo($peten);
        $g->sincronizar();
        $this->assertSame([1], self::ids($g));
    }

    public function testQuitadoComoMiembroSigueSiEstaEnUnGrupoMiembro(): void
    {
        [$dueno, $beto, $carla] = [self::usuario(1), self::usuario(3), self::usuario(4)];
        $peten = ChatCanal::grupo("Petén", $beto, [$carla]);
        $g = ChatCanal::grupo("Avisos", $dueno, [$carla], ModoGrupoChat::Dirigido);
        $g->miembro($carla)->setEmisor(true);
        $g->agregarSubgrupo($peten);
        $g->sincronizar();
        $this->assertTrue($g->miembro($carla)->esDirecto(), "estando por sí misma no cuenta el grupo");

        $g->quitar($carla);
        $this->assertSame($peten, $g->miembro($carla)?->getVia());
        $this->assertFalse($g->puedeEscribir($carla), "por un grupo no emisor ya no escribe");

        $g->agregar($carla);
        $this->assertTrue($g->miembro($carla)->esDirecto());
    }

    public function testEditarEsResponderAlOriginalYEliminarLoVacia(): void
    {
        $ana = self::usuario(2);
        $g = ChatCanal::grupo("Coordinación", self::usuario(1), [$ana]);
        $original = new ChatMensaje($g, $ana, "Sale a las 8", [["tipo" => "Salida", "id" => 9]]);
        $edicion = new ChatMensaje($g, $ana, "Sale a las 9");
        $edicion->comoEdicionDe($original);

        $this->assertSame($original, $edicion->getEditaA());
        $this->assertSame($original, $edicion->getRespuestaA());

        $original->eliminar();
        $this->assertTrue($original->estaEliminado());
        $this->assertSame("", $original->getTexto());
        $this->assertSame([], $original->getAdjuntos());
    }
}
