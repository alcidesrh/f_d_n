<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acceso\Administracion\AdministracionAccesos;
use App\Entity\Enum\OperacionUsuario;
use App\Entity\ApiToken;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Cambio de contraseña de un usuario desde la lista de usuarios, con permiso
 * `usuario.editar` y solo de quien el administrador domina (podría otorgarle
 * cada asignación, ADR-039): nadie le cambia la contraseña a alguien con más
 * poder. Si el usuario no es quien hace el cambio, se le cierran las sesiones
 * abiertas (sus tokens dejan de servir) y queda en su bitácora.
 */
#[AsController]
final class CambiarPasswordController extends AbstractController
{
    public const EDITAR = 'usuario.editar';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AdministracionAccesos $accesos,
    ) {}

    /** `{ password }` */
    #[Route('/api/usuarios/{id<-?\d+>}/password', name: 'api_usuario_password', methods: ['POST'])]
    public function __invoke(Usuario $usuario, Request $request, #[CurrentUser] Usuario $actual): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::EDITAR);
        if (($motivo = $this->accesos->rechazoSobre($usuario)) !== null) {
            return $this->json(['error' => $motivo, 'codigo' => 'permiso'], Response::HTTP_FORBIDDEN);
        }

        $password = $request->toArray()['password'] ?? null;
        if (!is_string($password) || $password === '') {
            return $this->json(
                ['error' => 'La contraseña es obligatoria.'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $usuario->setPassword($this->passwordHasher->hashPassword($usuario, $password));

        $sesionesCerradas = 0;
        if ($usuario->getId() !== $actual->getId()) {
            $sesionesCerradas = $this->em->createQueryBuilder()
                ->update(ApiToken::class, 't')
                ->set('t.activo', ':no')
                ->where('t.usuario = :usuario')
                ->andWhere('t.activo = :si')
                ->setParameter('no', false)
                ->setParameter('si', true)
                ->setParameter('usuario', $usuario)
                ->getQuery()
                ->execute();
        }

        $this->em->flush();
        if ($usuario->getId() !== $actual->getId()) {
            $this->accesos->anotar($usuario, OperacionUsuario::CONTRASENA);
        }

        return $this->json(['ok' => true, 'sesionesCerradas' => $sesionesCerradas]);
    }
}
