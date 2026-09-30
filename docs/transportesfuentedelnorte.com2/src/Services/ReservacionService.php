<?php

namespace App\Services;

use App\Entity\Asiento;
use App\Entity\ClienteReservacion;
use App\Entity\Empresa;
use App\Entity\Factura;
use App\Entity\Reservacion;
use App\EntitySistemaFdn\BoletoPaginaTemp;
use App\Services\RemoteDatabaseQueries;
use App\Services\SatRestService;
use App\Services\Traits\ReservaUtilTrait;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Dompdf\Dompdf;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class ReservacionService {

    use ReservaUtilTrait;

    private EntityManagerInterface $entityManagerInterface;

    public function __construct(
        #[Autowire('%reservacion_minutos%')] private $reservacion_minutos,
        private Reservacion $reservacion,
        private SatRestService $satRestService,
        private RemoteDatabaseQueries $remoteDatabaseQueries,
        private RequestStack $requestStack,
        private TranslatorInterface $translatorInterface,
        private Filesystem $filesystem,
        private Environment $environment,
        private ManagerRegistry $doctrine,
        private Mails $mails
    ) {
        $this->entityManagerInterface = $this->doctrine->getManager();
    }

    public function getBoletoNombre(Reservacion $reservacion = null) {

        $this->reservacion =  $reservacion ?? $this->reservacion;

        $slugger = new AsciiSlugger();

        $format_nombre = fn($string) => \ucwords(\mb_strtolower($string), " -’'\t\r\n\f\v");

        $date = (new DateTime())->format('Y_m_d_H_i_s');

        return $slugger->slug($format_nombre($this->reservacion->getCliente()->getNombreFactura())) . '_' .

            $this->translatorInterface->trans('boleto') . '_' . ($this->reservacion->getId()) . '_' . $date . '.pdf';
    }

    public function getFacturaPdf($reservacion = null) {

        $reservacion ??= $this->reservacion;

        $pdf_nombre = $this->getBoletoNombre($reservacion);

        $dompdf = new Dompdf();

        $dompdf->setPaper('A4', 'portrait');
        // $dompdf->setPaper(array(0, 0, 600, $GLOBALS['bodyHeight'] + 50));
        $dompdf->loadHtml($this->environment->render('email/factura.html.twig', ['reservacion' => $reservacion, 'nit_emisor' => Empresa::nit_emisor]));

        $dompdf->render();

        $pdf_path = $reservacion->getFacturaPdfPath();

        if ($pdf_path && $this->filesystem->exists($pdf_nombre)) {

            $this->filesystem->remove($pdf_path);
        }

        $this->filesystem->dumpFile('facturas/' . $pdf_nombre, $dompdf->output());

        return $pdf_nombre;
    }

    public function anular(Reservacion $reservacion = null) {

        $reservacion = $reservacion ?: $this->reservacion;


        try {

            if ($reservacion->getAnularIntentos() >= Reservacion::ANULAR_INTENTOS && ($reservacion->getAnularIntentos() - Reservacion::ANULAR_INTENTOS) < 3) {

                $this->mails->notificacion("Error en anular reserva id: {$reservacion->getId()}. Se excedió el límite de intentos para anular.");
            } else {
                $reservacion->incrementarAnularIntentos();
            }

            if (!$reservacion->hasFactura() && !$reservacion->getBoletoTicketId() && !$reservacion->isClickPagar()) {
                $reservacion->setStatus(Reservacion::ANULADA);
            } else if (!$reservacion->isSatAnulada() && $reservacion->hasFactura()) {
                if ($result = $this->satRestService->anular($reservacion)) {
                    if (is_array($result) && isset($result['error'])) {
                        if (isset($result['text']) && \str_contains($result['text'], 'EVI-018')) {
                            $reservacion->setStatus(Reservacion::ANULADA_SAT);
                        } else {
                            $reservacion->setStatus(Reservacion::ANULADA_ERROR_SAT);
                        }
                    } else {
                        $reservacion->setStatus(Reservacion::ANULADA_SAT);
                    }
                } else {
                    $reservacion->setStatus(Reservacion::ANULADA_ERROR_SAT);
                }
            } else {
                $reservacion->setStatus(Reservacion::ANULADA_SAT);
            }

            if (!$reservacion->isSistemaAnulada()) {

                if ($reservacion->getBoletoTicketId() || $reservacion->isClickPagar()) {

                    if (\is_array($result = $this->remoteDatabaseQueries->anularReservacion($reservacion))) {

                        if (!isset($result['error']) && isset($result['anulado'])) {

                            $reservacion->setClickPagar(false);
                            $reservacion->setStatus(Reservacion::ANULADA_SISTEMA);
                        } else if (isset($result['noexist'])) {

                            $reservacion->setClickPagar(false);
                            $reservacion->setStatus(Reservacion::ANULADA_SISTEMA);
                        } else {
                            $reservacion->setStatus(Reservacion::ANULADA_ERROR_SISTEMA);

                            if (isset($result['error'])) {
                                echo $result['error'];
                            }
                        }
                    }
                } else {
                    $reservacion->setStatus(Reservacion::ANULADA_SISTEMA);
                }
            }

            $this->entityManagerInterface->flush();
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    public function anularVencidas() {

        try {

            if ($reservaciones = $this->entityManagerInterface->getRepository(Reservacion::class)->getReservacionVencidas($this->reservacion_minutos)) {
                foreach ($reservaciones as $reservacion) {
                    $cliente = $reservacion->getCliente();
                    if (!$reservacion->getTransaccionId() || ($cliente && $cliente->getEmail() == 'alcidesrh@gmail.com') || $reservacion->isClickPagar()) {
                        $this->anular($reservacion);
                        $reservacion->getRequests();
                        $this->entityManagerInterface->flush();
                    }
                }
            }
            echo "\n" . "Reservaciones encontradas: " . count($reservaciones) . ', ids: ' . implode(', ', array_map(fn($i) => $i->getId(), $reservaciones)) . "\n";
        } catch (\Exception $e) {
            $this->mails->notificacion('Anular Error: ' . $e->getMessage());
            return false;
        }

        return true;
    }

    public function reenviarEmail() {

        if ($reservaciones = $this->entityManagerInterface->getRepository(Reservacion::class)->getEmailNoEnviado($this->reservacion_minutos)) {
            foreach ($reservaciones as $reservacion) {
                try {
                    if ($this->mails->reservacionEmail($reservacion)) {
                        $reservacion->setEmailEnviado(true);
                    }
                } catch (\Exception $e) {
                    $this->mails->notificacion('Email Error: ' . $e->getMessage());

                    return false;
                }
            }
            $this->entityManagerInterface->flush();
        }
        return true;
    }

    public function crearBoleto(Reservacion $reservacion = null) {

        $this->reservacion = $reservacion ?: $this->reservacion;

        if ($this->reservacion->procederAnular('boleto')) {

            if (\is_array($result = $this->remoteDatabaseQueries->anularReservacion($reservacion))) {

                if (!isset($result['error']) && isset($result['anulado'])) {

                    $this->reservacion->setStatus(Reservacion::ANULADA_SISTEMA);
                } else {

                    $this->reservacion->setStatus(Reservacion::ANULADA_ERROR_SISTEMA);
                }
            } else if (!$result) {

                $this->reservacion->setStatus(Reservacion::ANULADA_ERROR_SISTEMA);

                $result = ['error' => 'No se pudo anular el boleto anterior. Inténtelo de nuevo o cancele la compra.'];
            }

            $this->entityManagerInterface->flush();

            if (isset($result['error']) && !isset($result['noexist'])) {

                return $result;
            }
        }

        $params = [
            'reserva_id' => $this->reservacion->getId(),
            'salidas' => [],
            'moneda' => $this->reservacion->getMoneda(),
            // 'factura' => $this->reservacion->getFactura()?->getData(),
            'cliente' => [
                'id' => $this->reservacion->getCliente()->getClienteId(),
                'nit' => $this->reservacion->getCliente()->getNit(),
                'nombre' => $this->reservacion->getCliente()->getNombreFactura(),
                'apellido' => $this->reservacion->getCliente()->getApellido(),
                'telefono' => $this->reservacion->getCliente()->getTelefono(),
                'email' => $this->reservacion->getCliente()->getEmail()
            ]
        ];

        foreach ($this->reservacion->getSalidasArray() as $key => $salida) {


            $params['salidas'] = [
                ...$params['salidas'],
                "nota$key" => $salida->getClienteNota(),
                $key => $salida->getSalidaId(),
                "{$key}_asientos" => $salida->getAsientos()->map(fn(Asiento $item) => [$item->getAsientoId(), $item->getPrecio(), $item->getId()])->toArray()
            ];
        }
        $this->reservacion->setClickPagar(true);
        $this->reservacion->setRequests();
        $this->entityManagerInterface->flush();
        if (!\is_array($result = $this->remoteDatabaseQueries->crearBoleto($params))) {
            return false;
        }

        if (isset($result['error'])) {

            if (1 == $result['error']) { // Error asientos ocupados

                return $this->eliminarAsientosRepetidos($result['data']);
            }
            return $result;
        }

        $data = json_decode($result['data'], true);


        $this->reservacion->setStatus(Reservacion::INCOMPLETA);

        $this->reservacion->setBoletoTicketId($data['boleto']);

        $this->entityManagerInterface->flush();

        foreach ($this->reservacion->getSalidasArray() as $key => $salida) {

            foreach ($salida->getAsientos() as $index => $asiento) {

                $asiento->setBoletoSistemaId($data['boletos_' . $key][$index]);
            }
        }

        $cliente = $this->reservacion->getCliente();

        if (!$cliente->getClienteId()) {

            $cliente->setClienteId($data['cliente_id']);
        }

        if ($factura = $this->reservacion->getFactura()) {
            if ($factura->getDte()) {
                if ($result = $this->satRestService->anular($reservacion)) {
                    if (is_array($result) && isset($result['error'])) {
                        $reservacion->setStatus(Reservacion::ANULADA_ERROR_SAT);
                        $this->entityManagerInterface->flush();
                        return ['error' => 'Error al anular la factura con DTE: ' . $factura->getDte() . ' ' . $result['text']];
                    }
                }
            }
            $this->entityManagerInterface->remove($factura);
        }
        $factura = new Factura();
        $this->entityManagerInterface->persist($factura);
        if ($data['factura_id']) {
            $factura->setIdSistema($data['factura_id']);
        }
        $this->reservacion->setFactura($factura);
        $this->entityManagerInterface->flush();
        return true;
    }

    public function eliminarAsientosRepetidos($data) {

        $asientos_numero = [];

        foreach ($data as $value) {

            foreach ($this->entityManagerInterface->getRepository(Asiento::class)->getAsientosPorSalida($value) as $asiento) {

                $this->entityManagerInterface->remove($asiento);

                $asientos_numero[] = $asiento->getNumero();
            }
        }
        $this->entityManagerInterface->flush();

        return ['asientos_ocupados_numeros' => $asientos_numero];
    }

    public function emitirFactura(Reservacion &$reservacion = null) {

        $reservacion ??= $this->reservacion;

        if (true !== ($result = $this->satRestService->anular($reservacion))) {
            if (is_array($result) && !\str_contains($result['text'], 'EVI-018')) {
                if (isset($result['error'])) {
                    return ['error' => 'no se pudo anular la factura previa. Por favor inténtelo de nuevo. Error: ' . $result['text']];
                }
                return ['error' => 'no se pudo anular la factura previa. Por favor inténtelo de nuevo.'];
            }
        }

        if (\is_array($result = $this->satRestService->emitirDteJson($reservacion))) {

            if (isset($result['NumeroDTE'])) {

                $factura = $reservacion->getFactura();

                $factura->setData($result);

                $this->remoteDatabaseQueries->enviarFacturaSistema($factura);

                $pdf = $this->getFacturaPdf($reservacion);

                $factura->setPdf($pdf);

                $this->entityManagerInterface->flush();

                return true;
            }
            if (isset($result['error'])) {

                return $result;
            }
        }

        return false;
    }

    public function limpiarSesion() {

        if ($session = $this->requestStack->getSession()) {

            $locale = $session->get('_locale', 'es');

            $session->clear();

            $session->set('_locale', $locale);
        }
    }

    public function reiniciar() {

        $cliente = $this->reservacion->getCliente();
        if (!$this->reservacion->isStatus(Reservacion::COMPLETADA) && (!$this->reservacion->getTransaccionId() || ($cliente && $cliente->getEmail() == 'alcidesrh@gmail.com'))) {

            $this->anular();
        }

        $this->sistemaEliminarReservacion();

        $this->limpiarSesion();

        $this->reservacion->setStatus(Reservacion::CANCELADA);

        $this->doctrine->getManager('systemfdn')->flush();
    }

    public function sistemaEliminarReservacion(?Reservacion $reservacion = null) {

        $reservacion = $reservacion ?? $this->reservacion;

        try {
            $this->doctrine->getRepository(BoletoPaginaTemp::class, 'systemfdn')->delete($reservacion);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'code' => $e->getCode()];
        }
    }
    static function precio_calcular($por_ciento, $precio) {

        return   $precio ? $precio + ($precio * $por_ciento / 100) : null;
    }

    public function test() {

        try {
            $qb = $this->entityManagerInterface->getRepository(ClienteReservacion::class);
            $qb = $qb->createQueryBuilder('c');
            $result = $qb->andWhere('c.id IN (:ids)')->setParameter(
                'ids',
                [
                    2795,
                    2793,
                    2792,
                    2791,
                    2790,
                    2788,
                    2784,
                    2783,
                    2782,
                    2781,
                    2775,
                    2774,
                    2773,
                    2772,
                    2771,
                    2770,
                    2769,
                    2767,
                    2764,
                    2763
                ]
            )->getQuery()->getResult();

            foreach ($result as $key => $value) {
                // $value = new ClienteReservacion();
                if ($nit = $value->getNit()) {
                    return $nit;
                    try {
                        $response = $this->satRestService->consultarNitReceptor(\strtoupper(\preg_replace("/[^A-Za-z0-9 ]/", '', $nit)));
                        $value->setNombreFactura($response['RazonSocial']);
                        return $response['RazonSocial'];
                        $this->entityManagerInterface->flush();
                    } catch (\Exception $e) {
                        return $e->getMessage();
                    }
                } else {
                    $value->setNit(null);
                }
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
        return true;
    }
    public function venta() {
        try {

            $conn = $this->entityManagerInterface->getConnection();

            $sql = "
            select sum(r.precio_real), DATE_TRUNC('month', r.created_at) from reservacion r where r.transaccion_id notnull and r.created_at >'20240101'
            GROUP BY DATE_TRUNC('month', r.created_at) order by date_trunc ASC;
            ";

            $resultSet = $conn->executeQuery($sql);

            // returns an array of arrays (i.e. a raw data set)
            return $resultSet->fetchAllAssociative();
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
