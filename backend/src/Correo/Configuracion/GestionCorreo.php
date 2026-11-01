<?php

declare(strict_types=1);

namespace App\Correo\Configuracion;

use App\Correo\Direcciones;
use App\Correo\Envio\TransporteCorreo;
use App\Entity\ConfiguracionCorreo;
use App\Entity\Usuario;
use App\Venta\CifradoCredenciales;
use App\Venta\Excepcion\VentaRechazada;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Ver, cambiar y probar `ConfiguracionCorreo` (ADR-048), desde la
 * administración (`ConfiguracionProveedoresController`). Los tokens nunca
 * salen: solo si cada dominio tiene uno y si se puede leer.
 */
final class GestionCorreo
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AjustesCorreo $ajustes,
        private readonly CifradoCredenciales $cifrado,
        private readonly TransporteCorreo $transporte,
        private readonly ClockInterface $reloj,
    ) {}

    /** @return array<string, mixed> */
    public function datos(string $webhook): array
    {
        $c = $this->ajustes->actual();
        $dominios = [];
        foreach ($c->getTokens() as $dominio => $cifrado) {
            $dominios[] = [
                "dominio" => (string) $dominio,
                // Ilegible: cifrado con otro APP_SECRET (hay que volver a cargarlo).
                "token" => $cifrado === null ? null : ($this->cifrado->descifrar(TransporteCorreo::CONTEXTO, $cifrado) !== null ? "guardado" : "ilegible"),
            ];
        }

        return [
            "activo" => $c->isActivo(),
            "servidor" => $c->getServidor(),
            "puerto" => $c->getPuerto(),
            "dominiosEnvio" => $dominios,
            "dominio" => $c->getDominio(),
            "dominioEntrada" => $c->getDominioEntrada(),
            "claveWebhook" => $c->getClaveWebhook(),
            "webhook" => $webhook,
            "remitenteBoletos" => $c->getRemitenteBoletos(),
            "remitenteReportes" => $c->getRemitenteReportes(),
            "buzonContacto" => $c->getBuzonContacto(),
            "actualizadaEn" => $c->getActualizadaEn()?->format(\DATE_ATOM),
            "actualizadaPor" => $c->getActualizadaPor()?->getLabel(),
        ];
    }

    /**
     * Cambia solo lo que viene: `activo`, `servidor` con `puerto`,
     * `dominiosEnvio` (`[{ dominio, token }]`: token vacío conserva el
     * guardado; un dominio que no viene se quita con su token), `dominio` con
     * `dominioEntrada` (vacío = el mismo), `claveWebhook` (vacía = sin correo
     * entrante) y las direcciones `remitenteBoletos`, `remitenteReportes`,
     * `buzonContacto`.
     *
     * @param array<string, mixed> $datos
     *
     * @throws VentaRechazada si algo no es válido (no guarda nada)
     */
    public function guardar(array $datos, ?Usuario $por): ConfiguracionCorreo
    {
        $config = $this->em->find(ConfiguracionCorreo::class, ConfiguracionCorreo::ID);
        $nueva = $config === null;
        $config ??= new ConfiguracionCorreo();

        if (array_key_exists("servidor", $datos) || array_key_exists("puerto", $datos)) {
            $servidor = strtolower(trim((string) ($datos["servidor"] ?? $config->getServidor())));
            $puerto = filter_var($datos["puerto"] ?? $config->getPuerto(), FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 65535]]);
            if (!self::esDominio($servidor) || $puerto === false) {
                throw new VentaRechazada("El servidor SMTP o su puerto no son válidos.", "servidor_invalido");
            }
            $config->setServidor($servidor, $puerto);
        }
        if (array_key_exists("dominiosEnvio", $datos)) {
            $config->setTokens($this->tokens($datos["dominiosEnvio"], $config->getTokens()));
        }
        if (array_key_exists("dominio", $datos) || array_key_exists("dominioEntrada", $datos)) {
            $dominio = strtolower(trim((string) ($datos["dominio"] ?? $config->getDominio())));
            $entrada = strtolower(trim((string) ($datos["dominioEntrada"] ?? $config->getDominioEntrada() ?? "")));
            if (!self::esDominio($dominio) || ($entrada !== "" && !self::esDominio($entrada))) {
                throw new VentaRechazada("El dominio de las direcciones o el de entrada no son válidos.", "dominio_invalido");
            }
            $config->setDominios($dominio, $entrada !== "" ? $entrada : null);
        }
        if (array_key_exists("claveWebhook", $datos)) {
            $clave = trim((string) ($datos["claveWebhook"] ?? ""));
            $cruda = base64_decode($clave, true);
            if ($clave !== "" && ($cruda === false || strlen($cruda) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES)) {
                throw new VentaRechazada("La clave pública del webhook debe ser la que da MailPace (base64, 32 bytes).", "clave_invalida");
            }
            $config->setClaveWebhook($clave !== "" ? $clave : null);
        }
        if (array_intersect(["remitenteBoletos", "remitenteReportes", "buzonContacto"], array_keys($datos)) !== []) {
            $direccion = static function (mixed $valor, string $actual, string $nombre): string {
                $d = strtolower(trim((string) ($valor ?? $actual)));
                if (!Direcciones::valida($d)) {
                    throw new VentaRechazada(sprintf("%s no es una dirección válida.", $nombre), "direccion_invalida");
                }

                return $d;
            };
            $config->setDirecciones(
                $direccion($datos["remitenteBoletos"] ?? null, $config->getRemitenteBoletos(), "El remitente de los boletos"),
                $direccion($datos["remitenteReportes"] ?? null, $config->getRemitenteReportes(), "El remitente de los reportes"),
                $direccion($datos["buzonContacto"] ?? null, $config->getBuzonContacto(), "El buzón de contacto"),
            );
        }
        if (array_key_exists("activo", $datos)) {
            $config->setActivo((bool) $datos["activo"]);
        }
        if ($config->isActivo() && array_filter($config->getTokens()) === []) {
            throw new VentaRechazada("Para enviar por MailPace cargue el token de al menos un dominio.", "sin_token");
        }

        $config->actualizada($por, $this->reloj->now());
        if ($nueva) {
            $this->em->persist($config);
        }
        $this->em->flush();

        return $config;
    }

    /**
     * Manda un correo de prueba por MailPace desde `no-responder@{dominio}`
     * con el token guardado, aunque MailPace esté apagado.
     *
     * @throws VentaRechazada si los datos no valen o MailPace lo rechaza
     */
    public function probar(string $dominio, string $para): void
    {
        $dominio = strtolower(trim($dominio));
        $para = trim($para);
        if (!Direcciones::valida($para)) {
            throw new VentaRechazada("Escriba la dirección a la que llega la prueba.", "prueba_invalida");
        }
        $config = $this->ajustes->actual();
        $de = "no-responder@" . $dominio;
        try {
            $this->transporte->mailpace($config, $de)->send(
                (new Email())
                    ->from(new Address($de, "Sistema FDN"))
                    ->to($para)
                    ->subject("Prueba de MailPace")
                    ->text(sprintf("Si recibe esto, el sistema envía por MailPace (%s:%d) con el token de %s.", $config->getServidor(), $config->getPuerto(), $dominio)),
            );
        } catch (\Throwable $e) {
            throw new VentaRechazada("MailPace no aceptó la prueba: " . $e->getMessage(), "prueba_fallida");
        }
    }

    /**
     * @param array<string, ?string> $actuales
     *
     * @return array<string, ?string>
     *
     * @throws VentaRechazada
     */
    private function tokens(mixed $lista, array $actuales): array
    {
        if (!is_array($lista)) {
            throw new VentaRechazada("Los dominios de envío no son válidos.", "dominios_invalidos");
        }
        $tokens = [];
        foreach ($lista as $fila) {
            $dominio = strtolower(trim((string) (is_array($fila) ? ($fila["dominio"] ?? "") : "")));
            $token = trim((string) (is_array($fila) ? ($fila["token"] ?? "") : ""));
            if (!self::esDominio($dominio)) {
                throw new VentaRechazada(sprintf("«%s» no es un dominio válido.", $dominio), "dominios_invalidos");
            }
            if (array_key_exists($dominio, $tokens)) {
                throw new VentaRechazada(sprintf("%s aparece dos veces.", $dominio), "dominios_invalidos");
            }
            if (mb_strlen($token) > 200 || preg_match('/\s/', $token)) {
                throw new VentaRechazada(sprintf("El token de %s no es válido.", $dominio), "dominios_invalidos");
            }
            $tokens[$dominio] = $token !== "" ? $this->cifrado->cifrar(TransporteCorreo::CONTEXTO, $token) : ($actuales[$dominio] ?? null);
        }

        return $tokens;
    }

    public static function esDominio(string $dominio): bool
    {
        return strlen($dominio) <= 253
            && preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $dominio) === 1;
    }
}
