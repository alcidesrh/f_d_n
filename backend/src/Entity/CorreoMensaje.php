<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\CarpetaCorreo;
use App\Entity\Enum\EnvioCorreo;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un correo en el buzón de una cuenta. Cada buzón tiene su copia (como en
 * cualquier servicio de correo): el enviado del remitente y lo recibido por
 * cada destinatario de la casa son filas distintas con el mismo
 * `messageId`, y marcarlo leído, destacarlo o borrarlo solo toca la propia.
 *
 * Un borrador es un correo en `Borradores`: nace al empezar a escribir, se
 * guarda solo mientras se escribe y al enviarlo pasa a `Enviados` (la misma
 * fila). Las direcciones son listas `{direccion, nombre}`.
 */
#[ORM\Entity]
#[ORM\Index(columns: ["cuenta_id", "carpeta", "fecha"], name: "idx_correo_mensaje_carpeta")]
#[ORM\Index(columns: ["cuenta_id", "message_id"], name: "idx_correo_mensaje_message_id")]
class CorreoMensaje
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private CorreoCuenta $cuenta;

    #[ORM\Column(length: 20, enumType: CarpetaCorreo::class)]
    private CarpetaCorreo $carpeta;

    /** Donde estaba antes de ir a la papelera (para restaurarlo). */
    #[ORM\Column(length: 20, nullable: true, enumType: CarpetaCorreo::class)]
    private ?CarpetaCorreo $carpetaPrevia = null;

    /** `Message-ID` sin `< >`; nulo en un borrador. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $messageId = null;

    /** `In-Reply-To` sin `< >`. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $enRespuestaA = null;

    /** `References`: ids separados por espacio, sin `< >`. */
    #[ORM\Column(type: "text", nullable: true)]
    private ?string $referencias = null;

    #[ORM\Column(length: 255)]
    private string $deDireccion;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $deNombre;

    /** @var list<array{direccion: string, nombre: ?string}> */
    #[ORM\Column(type: "json", options: ["jsonb" => true])]
    private array $para = [];

    /** @var list<array{direccion: string, nombre: ?string}> */
    #[ORM\Column(type: "json", options: ["jsonb" => true])]
    private array $cc = [];

    /** Solo en el enviado y en los borradores: los destinatarios no lo ven. @var list<array{direccion: string, nombre: ?string}> */
    #[ORM\Column(type: "json", options: ["jsonb" => true])]
    private array $cco = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $responderA = null;

    #[ORM\Column(length: 255)]
    private string $asunto = "";

    #[ORM\Column(type: "text")]
    private string $texto = "";

    /** El HTML tal como llegó (se muestra aislado en el navegador, sin scripts). */
    #[ORM\Column(type: "text", nullable: true)]
    private ?string $html = null;

    /** Recibido, enviado o (borrador) última vez que se guardó. Ordena el buzón. */
    #[ORM\Column]
    private \DateTimeImmutable $fecha;

    #[ORM\Column(options: ["default" => false])]
    private bool $leido = false;

    #[ORM\Column(options: ["default" => false])]
    private bool $destacado = false;

    /** El remitente (recibidos) o algún destinatario (enviados) es de fuera de la empresa. */
    #[ORM\Column(options: ["default" => false])]
    private bool $externo = false;

    #[ORM\Column(length: 20, nullable: true, enumType: EnvioCorreo::class)]
    private ?EnvioCorreo $envio = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $errorEnvio = null;

    /** El correo al que responde o que reenvía este borrador/enviado (de la misma cuenta). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?CorreoMensaje $origen = null;

    /** @var Collection<int, CorreoAdjunto> */
    #[ORM\OneToMany(mappedBy: "mensaje", targetEntity: CorreoAdjunto::class, cascade: ["persist"])]
    #[ORM\OrderBy(["id" => "ASC"])]
    private Collection $adjuntos;

    public function __construct(CorreoCuenta $cuenta, CarpetaCorreo $carpeta, string $deDireccion, ?string $deNombre, ?\DateTimeImmutable $fecha = null)
    {
        $this->cuenta = $cuenta;
        $this->carpeta = $carpeta;
        $this->deDireccion = strtolower($deDireccion);
        $this->deNombre = $deNombre !== null ? mb_substr($deNombre, 0, 160) : null;
        $this->fecha = $fecha ?? new \DateTimeImmutable();
        $this->adjuntos = new ArrayCollection();
        $this->leido = $carpeta !== CarpetaCorreo::Entrada;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCuenta(): CorreoCuenta
    {
        return $this->cuenta;
    }

    public function getCarpeta(): CarpetaCorreo
    {
        return $this->carpeta;
    }

    public function esBorrador(): bool
    {
        return $this->carpeta === CarpetaCorreo::Borradores;
    }

    public function aLaPapelera(): void
    {
        if ($this->carpeta !== CarpetaCorreo::Papelera) {
            $this->carpetaPrevia = $this->carpeta;
            $this->carpeta = CarpetaCorreo::Papelera;
        }
    }

    public function restaurar(): void
    {
        if ($this->carpeta === CarpetaCorreo::Papelera) {
            $this->carpeta = $this->carpetaPrevia ?? CarpetaCorreo::Entrada;
            $this->carpetaPrevia = null;
        }
    }

    /** El borrador sale: pasa a Enviados con su `Message-ID` y la hora del envío. */
    public function enviar(string $messageId, bool $externo, \DateTimeImmutable $ahora): void
    {
        $this->carpeta = CarpetaCorreo::Enviados;
        $this->messageId = $messageId;
        $this->externo = $externo;
        $this->fecha = $ahora;
        $this->leido = true;
        $this->envio = $externo ? EnvioCorreo::Pendiente : EnvioCorreo::Enviado;
        $this->errorEnvio = null;
    }

    public function entregado(): void
    {
        $this->envio = EnvioCorreo::Enviado;
        $this->errorEnvio = null;
    }

    public function fallo(string $error): void
    {
        $this->envio = EnvioCorreo::Fallido;
        $this->errorEnvio = mb_substr($error, 0, 2000);
    }

    public function getCarpetaPrevia(): ?CarpetaCorreo
    {
        return $this->carpetaPrevia;
    }

    public function getMessageId(): ?string
    {
        return $this->messageId;
    }

    public function setMessageId(?string $messageId): void
    {
        $this->messageId = $messageId !== null ? mb_substr($messageId, 0, 255) : null;
    }

    public function getEnRespuestaA(): ?string
    {
        return $this->enRespuestaA;
    }

    public function getReferencias(): ?string
    {
        return $this->referencias;
    }

    public function setHilo(?string $enRespuestaA, ?string $referencias): void
    {
        $this->enRespuestaA = $enRespuestaA !== null && $enRespuestaA !== "" ? mb_substr($enRespuestaA, 0, 255) : null;
        $this->referencias = $referencias !== null && $referencias !== "" ? $referencias : null;
    }

    public function getDeDireccion(): string
    {
        return $this->deDireccion;
    }

    public function getDeNombre(): ?string
    {
        return $this->deNombre;
    }

    /** @return list<array{direccion: string, nombre: ?string}> */
    public function getPara(): array
    {
        return $this->para;
    }

    /** @return list<array{direccion: string, nombre: ?string}> */
    public function getCc(): array
    {
        return $this->cc;
    }

    /** @return list<array{direccion: string, nombre: ?string}> */
    public function getCco(): array
    {
        return $this->cco;
    }

    /**
     * @param list<array{direccion: string, nombre: ?string}> $para
     * @param list<array{direccion: string, nombre: ?string}> $cc
     * @param list<array{direccion: string, nombre: ?string}> $cco
     */
    public function setDestinatarios(array $para, array $cc, array $cco = []): void
    {
        $this->para = $para;
        $this->cc = $cc;
        $this->cco = $cco;
    }

    public function getResponderA(): ?string
    {
        return $this->responderA;
    }

    public function setResponderA(?string $responderA): void
    {
        $this->responderA = $responderA !== null && $responderA !== "" ? mb_substr(strtolower($responderA), 0, 255) : null;
    }

    public function getAsunto(): string
    {
        return $this->asunto;
    }

    public function setAsunto(string $asunto): void
    {
        $this->asunto = mb_substr(trim(preg_replace('/[\r\n]+/', " ", $asunto) ?? ""), 0, 255);
    }

    public function getTexto(): string
    {
        return $this->texto;
    }

    public function setTexto(string $texto): void
    {
        $this->texto = $texto;
    }

    public function getHtml(): ?string
    {
        return $this->html;
    }

    public function setHtml(?string $html): void
    {
        $this->html = $html !== null && trim($html) !== "" ? $html : null;
    }

    public function getFecha(): \DateTimeImmutable
    {
        return $this->fecha;
    }

    public function setFecha(\DateTimeImmutable $fecha): void
    {
        $this->fecha = $fecha;
    }

    public function isLeido(): bool
    {
        return $this->leido;
    }

    public function setLeido(bool $leido): void
    {
        $this->leido = $leido;
    }

    public function isDestacado(): bool
    {
        return $this->destacado;
    }

    public function setDestacado(bool $destacado): void
    {
        $this->destacado = $destacado;
    }

    public function isExterno(): bool
    {
        return $this->externo;
    }

    public function setExterno(bool $externo): void
    {
        $this->externo = $externo;
    }

    public function getEnvio(): ?EnvioCorreo
    {
        return $this->envio;
    }

    public function getErrorEnvio(): ?string
    {
        return $this->errorEnvio;
    }

    public function getOrigen(): ?CorreoMensaje
    {
        return $this->origen;
    }

    public function setOrigen(?CorreoMensaje $origen): void
    {
        $this->origen = $origen;
    }

    /** @return Collection<int, CorreoAdjunto> */
    public function getAdjuntos(): Collection
    {
        return $this->adjuntos;
    }

    public function agregarAdjunto(CorreoAdjunto $adjunto): void
    {
        if (!$this->adjuntos->contains($adjunto)) {
            $this->adjuntos->add($adjunto);
        }
    }

    public function quitarAdjunto(CorreoAdjunto $adjunto): void
    {
        $this->adjuntos->removeElement($adjunto);
    }
}
