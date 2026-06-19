<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class CambiarPasswordController extends AbstractController {

    public function __construct(
        private UsuarioRepository $usuarioRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/api/change-password', name: 'api_change_password', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;

        if (!$username || !$password) {
            return $this->json(['error' => 'Faltan campos requeridos: username, password'], 400);
        }

        $user = $this->usuarioRepository->findOneBy(['username' => $username]);

        if (!$user) {
            return $this->json(['error' => 'Usuario no encontrado'], 404);
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }
}
