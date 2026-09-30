<?php

namespace App\Entity;

use App\Repository\ReservacionRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: ReservacionRepository::class)]
// #[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
class Reservacion {

    use TimestampableEntity;

    public const ANULAR_INTENTOS = 10;
    public const INCOMPLETA = 'incompleta';
    public const COMPLETADA = 'completada';
    public const ANULADA = 'anulada';
    public const ANULADA_SAT = 'anulada_sat';
    public const ANULADA_SISTEMA = 'anulada_sistema';
    public const ANULADA_LOCAL = 'anulada_local';
    public const ANULADA_ERROR_SISTEMA = 'anulada_error_sistema';
    public const ANULADA_ERROR_NO_REGISTRADO = 'anular_error_no_registrado';
    public const CANCELADA = 'cancelada';
    public const SISTEMA_NO_EXISTE = 'sistema_no_existe';
    public const ANULADA_MAX_INTENTOS = 'anulada_max_intentos';
    public const ANULADA_ERROR_SAT = 'anulada_error_sat';
    public const ANULAR_SAT_ERROR = 'anular_sat_error';

    public const MONEDA_GTQ = 'GTQ';
    public const MONEDA_USD = 'USD';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reservaciones', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'set null')]
    private ?RutaReservacion $ruta = null;

    #[ORM\OneToOne(inversedBy: 'salida_reservacion', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(onDelete: 'set null')]
    private ?SalidaReservacion $salida = null;

    #[ORM\OneToOne(inversedBy: 'regreso_reservacion', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(onDelete: 'set null')]
    private ?SalidaReservacion $regreso = null;

    #[ORM\Column(nullable: true)]
    private ?bool $ida_vuelta = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $paso_completado = null;

    #[ORM\ManyToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(onDelete: 'set null')]
    private ?ClienteReservacion $cliente = null;

    #[ORM\Column(nullable: true)]
    private ?float $precio = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $transaccion_id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $moneda = null;

    #[ORM\Column(nullable: true)]
    private ?int $boleto_ticket_id = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $anular_intentos = null;

    #[ORM\Column(nullable: true)]
    private ?float $precio_dolar = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?float $compra_porciento_actual = null;

    #[ORM\Column(nullable: true)]
    private ?float $dolar_cambio_actual = null;

    #[ORM\Column(nullable: true)]
    private ?float $precio_real = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'set null')]
    private ?Tarjeta $tarjeta = null;

    #[ORM\Column(nullable: true)]
    private ?string $status_cybersources = null;

    #[ORM\Column(nullable: true)]
    private ?bool $email_enviado = null;

    #[ORM\OneToOne(inversedBy: 'reservacion', cascade: ['persist', 'remove'])]
    private ?Factura $factura = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $locale = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Empresa $empresa_factura = null;

    #[ORM\Column(nullable: true)]
    private ?bool $factura_conjunta = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $uri = null;

    #[ORM\Column(nullable: true)]
    private ?bool $clickPagar = null;

    #[ORM\Column(nullable: true)]
    private ?int $requests = null;

    public function __construct($locale = 'es') {
        $this->status = self::INCOMPLETA;
        $this->paso_completado = 0;
        $this->anular_intentos = 0;
        $this->moneda = !$locale || 'es' == $locale ? self::MONEDA_GTQ : self::MONEDA_USD;
        $this->locale = $locale;
        $this->requests = 0;
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getRuta(): ?RutaReservacion {
        return $this->ruta;
    }

    public function setRuta(?RutaReservacion $ruta): self {
        $this->ruta = $ruta;

        return $this;
    }

    public function getSalida(): ?SalidaReservacion {
        return $this->salida;
    }

    public function setSalida(?SalidaReservacion $salida): self {
        $this->salida = $salida;

        return $this;
    }

    public function getRegreso(): ?SalidaReservacion {
        return $this->regreso;
    }

    public function setRegreso(?SalidaReservacion $regreso): self {
        $this->regreso = $regreso;

        return $this;
    }

    public function isIdaVuelta(): ?bool {
        return $this->ida_vuelta;
    }

    public function setIdaVuelta(?bool $ida_vuelta): self {
        $this->ida_vuelta = $ida_vuelta;

        return $this;
    }

    public function getPasoCompletado(): ?int {
        return $this->paso_completado > -1 ? $this->paso_completado : 0;
    }

    public function setPasoCompletado(?int $paso_completado): self {

        if ($this->transaccion_id) {
            $this->status = self::COMPLETADA;
            $this->paso_completado = 4;
            return $this;
        }
        $this->paso_completado = $this->paso_completado == 4 ?: $paso_completado;

        if ($this->paso_completado == 4) {
            $this->status = self::COMPLETADA;
        }

        return $this;
    }

    public function getCliente(): ?ClienteReservacion {
        return $this->cliente;
    }

    public function getPais(): ?string {
        return $this->cliente?->getPais()?->getName();
    }

    public function setCliente(?ClienteReservacion $cliente): self {
        $this->cliente = $cliente;

        return $this;
    }

    public function getPrecio(): ?float {
        return number_format($this->precio, 2, '.', '');
    }

    public function setPrecio(?float $precio): self {
        $this->precio = $precio; // ? $precio + ($precio * $this->compra_porciento_actual / 100) : null;

        return $this;
    }

    public function getPrecioCobrar(): ?float {
        return round(self::MONEDA_GTQ == $this->moneda ? $this->precio : $this->precio_dolar, 2);
    }

    public function getPrecioConMoneda(): ?string {
        return round(self::MONEDA_GTQ == $this->moneda ? $this->precio : $this->precio_dolar, 2) . ' ' . $this->moneda;
    }

    public function getTransaccionId(): ?string {
        return $this->transaccion_id;
    }

    public function setTransaccionId(?string $transaccion_id): self {
        $this->transaccion_id = $transaccion_id;

        return $this;
    }

    public function getStatus(): ?string {
        return $this->status;
    }

    public function setStatus(?string $status): self {

        if ($this->transaccion_id) {
            $this->status = self::COMPLETADA;
            $this->paso_completado = 4;
            return $this;
        }
        if (($this->status == self::ANULADA_SISTEMA && $status == self::ANULADA_SAT) ||
            ($this->status == self::ANULADA_SAT && $status == self::ANULADA_SISTEMA)
        ) {
            $this->status = self::ANULADA;
        } else {
            $this->status = $status;
        }
        return $this;
    }

    public function getMoneda(): ?string {
        return $this->moneda;
    }

    public function setMoneda(?string $moneda): self {
        $this->moneda = $moneda;

        return $this;
    }

    public function getRutaForAdmin() {
        return $this->ruta?->getEstacionSalida()->getNombre() . ' - ' . $this->ruta?->getEstacionLlegada()->getNombre();
    }

    public function getAnularIntentos(): ?int {
        return $this->anular_intentos;
    }

    public function setAnularIntentos(?int $anular_intentos): self {
        $this->anular_intentos = $anular_intentos;

        return $this;
    }

    public function incrementarAnularIntentos(): self {
        ++$this->anular_intentos;

        return $this;
    }

    public function getPrecioDolar(): ?float {
        return number_format($this->precio_dolar, 2);
    }

    public function setPrecioDolar($precio = null): self {
        $this->precio_dolar =  ($precio ?? $this->precio ?? 0) / $this->dolar_cambio_actual;

        return $this;
    }

    public function getCompraPorcientoActual(): ?float {
        return $this->compra_porciento_actual;
    }

    public function setCompraPorcientoActual(?float $compra_porciento_actual): self {
        $this->compra_porciento_actual = $compra_porciento_actual;

        return $this;
    }

    public function getDolarCambioActual(): ?float {
        return $this->dolar_cambio_actual;
    }

    public function setDolarCambioActual(?float $dolar_cambio_actual): self {
        $this->dolar_cambio_actual = $dolar_cambio_actual;

        return $this;
    }

    public function getPrecioReal(): ?float {
        return $this->precio_real;
    }

    public function setPrecioReal(?float $precio_real): self {
        $this->precio_real = $precio_real;

        return $this;
    }

    public function getTarjeta(): ?Tarjeta {
        return $this->tarjeta;
    }

    public function setTarjeta(?Tarjeta $tarjeta): self {
        $this->tarjeta = $tarjeta;

        return $this;
    }

    public function getStatusCybersources(): ?string {
        return $this->status_cybersources;
    }

    public function setStatusCybersources(string $status_cybersources): self {
        $this->status_cybersources = $status_cybersources;

        return $this;
    }

    public function isEmailEnviado(): ?bool {
        return $this->email_enviado;
    }

    public function isStatus($status): bool {

        return $this->status == $status;
    }

    public function setEmailEnviado(?bool $email_enviado): self {
        $this->email_enviado = $email_enviado;

        return $this;
    }

    public function getMontoGravableMasImpuesto() {
        // $monto_gravable = round($this->precio_real / 1.12, 2);
        $monto_gravable = round($this->precio / 1.12, 2);
        $monto_impuesto = round($monto_gravable * 0.12, 2);
        return [$monto_gravable, $monto_impuesto];
    }

    public function getAsientosNumeros() {
        return \implode(',', \array_map(
            function (Asiento $asiento) {
                return $asiento->getNumero();
            },
            \array_merge($this->salida->getAsientos()->toArray(), $this->regreso?->getAsientos()->toArray() ?? [])
        ));
    }

    public function getFactura(): ?Factura {
        return $this->factura;
    }

    public function hasFactura(): ?bool {
        return isset($this->factura);
    }

    public function setFactura(?Factura $factura): self {
        $this->factura = $factura;

        return $this;
    }

    public function setSalidaRegreso(SalidaReservacion $salidaReservacion, $ida_vuelta = false) {
        if ($ida_vuelta) {
            $this->regreso = $salidaReservacion;
        } else {
            $this->salida = $salidaReservacion;
        }
    }

    public function getSalidaORegreso($ida_vuelta): ?SalidaReservacion {
        return $ida_vuelta ? $this->regreso : $this->salida;
    }

    public function getSalidasArray(): ?array {

        return \array_filter(

            $this->ida_vuelta ? ['salida' => $this->salida, 'regreso' => $this->regreso] : ['salida' => $this->salida],

            fn($i) => $i
        );
    }

    /**
     * Get the value of boleto_ticket_id
     */
    public function getBoletoTicketId() {
        return $this->boleto_ticket_id;
    }

    /**
     * Set the value of boleto_ticket_id
     *
     * @return  self
     */
    public function setBoletoTicketId($boleto_ticket_id) {
        $this->boleto_ticket_id = $boleto_ticket_id;

        return $this;
    }

    public function getFacturaPdfPath($value = null): ?string {

        return $this->factura?->getPath();
    }

    public function getLocale(): ?string {
        return $this->locale;
    }

    public function setLocale(?string $locale): self {
        $this->moneda = 'es' == $locale ? self::MONEDA_GTQ : self::MONEDA_USD;
        $this->locale = $locale;

        return $this;
    }

    public function isSistemaAnulada(): bool {

        return $this->status == self::ANULADA || $this->status == self::ANULADA_SISTEMA;
    }

    public function isSatAnulada(): bool {

        return $this->status == self::ANULADA || $this->status == self::ANULADA_SAT;
    }

    public function isParcialAnulada(): bool {

        return $this->isSatAnulada() || $this->isSistemaAnulada();
    }

    public function getSalidaFacturaFecha($tipo = false, $format = false) {

        if ($tipo == 'regreso') {

            return $this->regreso->getSalidaFechaFactura($this->locale, $format);
        }

        return $this->salida->getSalidaFechaFactura($this->locale, $format);
    }

    public function getMinutosEditadaCreada($edit = false): int {

        $date = !$edit ? $this->createdAt : $this->updatedAt;

        $d = (clone $date)->diff(new DateTime());

        return $d->i + ($d->h * 60);
    }

    public function getAsientosCantidad($value = null) {
        return $this->salida?->getAsientosCantidad() + $this->regreso?->getAsientosCantidad();
    }

    public function getAsientos() {
        return \array_merge(
            $this->salida?->getAsientos()->toArray() ?? [],
            $this->regreso?->getAsientos()->toArray() ?? [],
        );
    }

    public function getBoletosSistemaId() {


        return \implode(',', \array_map(
            function (Asiento $asiento) {
                return $asiento->getBoletoSistemaId();
            },
            \array_merge($this->salida->getAsientos()->toArray(), $this->regreso?->getAsientos()->toArray() ?? [])
        ));
    }

    public function getEmpresaFactura(): ?Empresa {
        return $this->empresa_factura;
    }

    public function setEmpresaFactura(?Empresa $empresa_factura): self {
        $this->empresa_factura = $empresa_factura;

        return $this;
    }

    public function isFacturaConjunta(): ?bool {
        return $this->factura_conjunta;
    }

    public function setFacturaConjunta(?bool $factura_conjunta): self {
        $this->factura_conjunta = $factura_conjunta;

        return $this;
    }

    public function procederAnular($tipo = null) {

        return match ($tipo) {
            'boleto'  => (bool) $this->boleto_ticket_id || $this->isClickPagar(),
            'factura' => (bool) $this->factura,
            default => (bool)$this->boleto_ticket_id || (bool)$this->factura
        };
    }

    public function getUri(): ?string {
        return $this->uri;
    }

    public function setUri(?string $uri): static {
        $this->uri = $uri;

        return $this;
    }

    public function getEmpresaAdmin(): string {
        $salidaE = $this->getSalida();
        if (!$salidaE || !($salidaE = $salidaE->getEmpresa())) {
            return '';
        }
        if ($this->factura_conjunta) {
            $regresoE = $this->getRegreso()->getEmpresa();
            return 'Salida: ' . $salidaE->getAlias() . '. <br/>' .
                'Regreso: ' . $regresoE->getAlias();
        }
        return $salidaE->getAlias();
    }

    public function isClickPagar(): ?bool {
        return $this->clickPagar;
    }

    public function setClickPagar(?bool $clickPagar): static {
        $this->clickPagar = $clickPagar;

        return $this;
    }

    public function getRequests(): ?int {
        return $this->requests;
    }

    public function setRequests(): static {
        $this->requests++;

        return $this;
    }
}
