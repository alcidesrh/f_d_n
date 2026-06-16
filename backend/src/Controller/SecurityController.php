<?php

namespace App\Controller;

use ApiPlatform\Metadata\IriConverterInterface;
use App\Entity\ApiToken;
use App\Entity\Role;
use App\Repository\ApiTokenRepository;
use App\Repository\RoleRepository;
use App\Security\PermissionManager;
use Doctrine\ORM\EntityManagerInterface;
use Dom\Entity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class SecurityController extends AbstractController {

  public function __construct(private ApiTokenRepository $apiTokenRepository, private RoleRepository $roleRepo, private EntityManagerInterface $manager) {
  }

  #[Route('/api/login', name: 'app_login', methods: ['POST'])]
  public function login(IriConverterInterface $iriConverter, PermissionManager $permissionManager, #[CurrentUser()] $user = null): Response {

    if (!$user) {
      return $this->json([
        'error' => 'Acceso denegado',
        2
      ], 401);
    }


    if (!$user->getRoles()) {
      if (!$role = $this->roleRepo->findOneBy(['nombre' => Role::user])) {
        $role = (new Role())->setNombre(Role::user);
        $this->roleRepo->save($role);
      }
      $user->addUserRole($role);
      $this->roleRepo->flush();
    }
    if (!$token = $user->getToken()) {
      $token = new ApiToken();
      $token->setUsuario($user);
      $token->setActivo(true);
      $user->addApiToken($token);
      $this->manager->persist($token);
      $this->apiTokenRepository->persist($token)->flush();
    } else {
      $token->setActivo(true);
      $this->apiTokenRepository->flush();
    }
    return $this->json([
      'token' => $token->getToken(),
      'username' => $user->getUsername(),
      'uri' => $iriConverter->getIriFromResource($user),
      'permissions' => $permissionManager->getEffectiveActions($user)

    ], 200);
  }

  #[Route('/logout', name: 'app_logout')]
  public function logout(Security $security): Response {
    // logout the user in on the current firewall
    $response = $security->logout();

    // you can also disable the csrf logout
    $response = $security->logout(false);

    return new Response();
  }

  #[Route('/auth', name: 'auth', methods: ['POST'])]
  public function auth(#[CurrentUser] $user = null): Response {
    if (!$user = $this->getUser()) {
      return $this->json([
        'error' => 'Acceso denegado',
      ], 401);
    }
    return $this->json([
      'token' => $user->getValidTokenStrings(),
      'username' => $user->getUsername()

    ], 200);
  }

  #[Route('/user-invalid', name: 'user_invalid')]
  public function userInvalid(#[CurrentUser] $user = null): Response {

    if ($token = $user->getToken()) {
      $token->setActivo(false);
      $this->apiTokenRepository->persist($token)->flush();
    }
    return $this->json([
      'logout' => true

    ], 200);
  }
}
