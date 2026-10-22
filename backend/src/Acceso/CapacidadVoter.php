<?php

declare(strict_types=1);

namespace App\Acceso;

use App\Acceso\Catalogo\Catalogo;
use App\Entity\Usuario;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * `isGranted('<capacidad>', $sujeto)` (ADR-039). Responde solo por códigos
 * del catálogo. El sujeto decide dónde: nada (la tiene en algún ámbito), una
 * `Ubicacion`, o un registro de la API genérica (`Ubicador::de`). Una
 * colección (listados de API Platform) cuenta como nada: sus filas las acota
 * `Api\AlcanceEnColecciones`.
 */
final class CapacidadVoter extends Voter
{
    public function __construct(
        private readonly Catalogo $catalogo,
        private readonly Autorizacion $autorizacion,
        private readonly Ubicador $ubicador,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $this->catalogo->existe($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $usuario = $token->getUser();
        if (!$usuario instanceof Usuario) {
            return false;
        }
        $ubicacion = match (true) {
            $subject instanceof Ubicacion => $subject,
            is_object($subject) && !is_iterable($subject) => $this->ubicador->de($subject),
            default => null,
        };
        if ($this->autorizacion->de($usuario)->puede($attribute, $ubicacion)) {
            return true;
        }
        $vote?->addReason(Autorizacion::mensaje($attribute, $ubicacion));

        return false;
    }
}
