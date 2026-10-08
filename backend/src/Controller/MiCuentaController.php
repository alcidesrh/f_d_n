<?php

declare(strict_types=1);

namespace App\Controller;

use App\Cuenta\FotoPerfil;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * "Mi cuenta": lo que el usuario con sesión puede editar de su propio perfil
 * (datos personales, foto y contraseña). Usuario, roles y permisos no se
 * tocan aquí: eso es administración (`usuario.editar`).
 */
#[AsController]
final class MiCuentaController extends AbstractController
{
    public const MINIMO_PASSWORD = 6;

    /** Campo => [máximo de caracteres, obligatorio]. */
    private const CAMPOS = [
        "nombre" => [255, true],
        "apellido" => [50, false],
        "email" => [50, false],
        "telefono" => [15, false],
        "nit" => [20, false],
        "direccion" => [255, false],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FotoPerfil $fotos,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route("/api/me/cuenta", name: "api_me_cuenta", methods: ["GET"])]
    public function cuenta(#[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->json($this->datos($yo));
    }

    /** `{ nombre, apellido?, email?, telefono?, nit?, direccion? }` */
    #[Route("/api/me/cuenta", name: "api_me_cuenta_guardar", methods: ["PUT"])]
    public function guardar(Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        $entrada = $request->toArray();
        $valores = [];
        foreach (self::CAMPOS as $campo => [$maximo, $obligatorio]) {
            $valor = trim((string) ($entrada[$campo] ?? ""));
            if ($valor === "" && $obligatorio) {
                return $this->error("El nombre es obligatorio.");
            }
            if (mb_strlen($valor) > $maximo) {
                return $this->error(sprintf("«%s» admite como máximo %d caracteres.", $campo, $maximo));
            }
            $valores[$campo] = $valor === "" ? null : $valor;
        }
        if ($valores["email"] !== null && $this->validator->validate($valores["email"], new Email())->count() > 0) {
            return $this->error("El correo no es válido.");
        }

        $yo->setNombre($valores["nombre"])
            ->setApellido($valores["apellido"])
            ->setEmail($valores["email"])
            ->setTelefono($valores["telefono"])
            ->setNit($valores["nit"])
            ->setDireccion($valores["direccion"]);
        $this->em->flush();

        return $this->json($this->datos($yo));
    }

    /** `{ actual, password }` */
    #[Route("/api/me/password", name: "api_me_password", methods: ["POST"])]
    public function password(Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        $entrada = $request->toArray();
        $actual = $entrada["actual"] ?? null;
        $nueva = $entrada["password"] ?? null;

        if (!is_string($actual) || !$this->hasher->isPasswordValid($yo, $actual)) {
            return $this->error("La contraseña actual no es correcta.");
        }
        if (!is_string($nueva) || mb_strlen($nueva) < self::MINIMO_PASSWORD) {
            return $this->error(sprintf("La contraseña nueva debe tener al menos %d caracteres.", self::MINIMO_PASSWORD));
        }
        if ($nueva === $actual) {
            return $this->error("La contraseña nueva debe ser distinta de la actual.");
        }

        $yo->setPassword($this->hasher->hashPassword($yo, $nueva));
        $this->em->flush();

        return $this->json(["ok" => true]);
    }

    /** Multipart, campo `foto` (JPG, PNG o WebP, máximo 2 MB). */
    #[Route("/api/me/foto", name: "api_me_foto_subir", methods: ["POST"])]
    public function subirFoto(Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        $archivo = $request->files->get("foto");
        if ($archivo === null) {
            return $this->error("Falta la imagen.");
        }
        try {
            $yo->setFoto($this->fotos->guardar($archivo, $yo));
        } catch (\DomainException $e) {
            return $this->error($e->getMessage());
        }
        $this->em->flush();

        return $this->json($this->datos($yo));
    }

    #[Route("/api/me/foto", name: "api_me_foto_quitar", methods: ["DELETE"])]
    public function quitarFoto(#[CurrentUser] Usuario $yo): JsonResponse
    {
        $this->fotos->borrar($yo);
        $yo->setFoto(null);
        $this->em->flush();

        return $this->json($this->datos($yo));
    }

    /**
     * La imagen, por URL firmada y estable (pública: la usan las `<img>`, que
     * no llevan Bearer). Cambia al cambiar la foto, así que se cachea mucho.
     */
    #[Route("/api/usuarios/{id<-?\d+>}/foto/{firma<[0-9a-f]{32}>}", name: "api_usuario_foto", methods: ["GET"])]
    public function foto(int $id, string $firma): Response
    {
        $usuario = $this->em->find(Usuario::class, $id);
        if ($usuario === null || !$this->fotos->firmaValida($usuario, $firma)) {
            return new Response("Imagen no disponible.", Response::HTTP_NOT_FOUND);
        }
        $ruta = $this->fotos->absoluta((string) $usuario->getFoto());
        if (!is_file($ruta)) {
            return new Response("Imagen no disponible.", Response::HTTP_NOT_FOUND);
        }

        $respuesta = new BinaryFileResponse($ruta, headers: ["Content-Type" => $this->fotos->mime($ruta)]);
        $respuesta->setPrivate();
        $respuesta->setMaxAge(7 * 24 * 3600);
        $respuesta->headers->set("X-Content-Type-Options", "nosniff");
        $respuesta->headers->set("Content-Security-Policy", "sandbox; default-src 'none'; img-src 'self'");

        return $respuesta;
    }

    /** @return array<string, mixed> */
    private function datos(Usuario $u): array
    {
        return [
            "id" => $u->getId(),
            "username" => $u->getUsername(),
            "nombre" => $u->getNombre(),
            "apellido" => $u->getApellido(),
            "email" => $u->getEmail(),
            "telefono" => $u->getTelefono(),
            "nit" => $u->getNit(),
            "direccion" => $u->getDireccion(),
            "foto" => $this->fotos->url($u),
            "empresa" => $u->getEmpresa()?->getNombre(),
            "estacion" => $u->getEstacion()?->getNombre(),
            "agencia" => $u->getAgencia()?->getNombre(),
            "roles" => $u->getRoles(),
        ];
    }

    private function error(string $mensaje): JsonResponse
    {
        return $this->json(["error" => $mensaje], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
