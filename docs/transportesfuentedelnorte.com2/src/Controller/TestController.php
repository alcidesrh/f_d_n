<?php

namespace App\Controller;

use App\Services\RemoteDatabaseQueries;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/test')]
class TestController extends AbstractController
{
    #[Route('/estaciones', name: 'estaciones-test')]
    public function estaciones(RemoteDatabaseQueries $systemfdnEntityManager): JsonResponse
    {
        try {
            $result = $systemfdnEntityManager->getEstaciones();
        } catch (\Throwable $e) {

            return $this->json([
                'message' => $e->getMessage(),
            ]);
        }

        dd($result);

        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/TestController.php',
        ]);
    }

    #[Route('/salidas', name: 'salidas-test')]
    public function salidas(RemoteDatabaseQueries $systemfdnEntityManager): JsonResponse
    {
        try {
            $result = $systemfdnEntityManager->getSalidas();
        } catch (\Throwable $e) {

            return $this->json([
                'message' => $e->getMessage(),
            ]);
        }

        dd($result);

        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/TestController.php',
        ]);
    }

    #[Route('/asientos', name: 'asientos-test')]
    public function asientos(RemoteDatabaseQueries $systemfdnEntityManager): Response
    {
        try {
            // $result = $systemfdnEntityManager->getAsientos();
        } catch (\Throwable $e) {

            return $this->json([
                'message' => $e->getMessage(),
            ]);
        }

        // dd($result);

        return $this->render('base.html.twig');

        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/TestController.php',
        ]);
    }
}
