<?php

namespace App\Services;

use App\Entity\Empresa;
use App\Entity\Reservacion;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Symfony\Component\String\u;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class SatRestService {


    public function __construct(private HttpClientInterface $eforconClient, private Empresa $empresa, private Reservacion $reservacion, #[Autowire('%credenciales%')] private $credenciales,) {
    }

    public function emitirDteJson(Reservacion $reservacion = null) {


        $reservacion = $reservacion ?? $this->reservacion;

        extract($this->extraerDatos($reservacion)); // Secrean las variables $empresaId, $facturaGenerada y $data

        $precio = $reservacion->getPrecio(); //getPrecioReal();

        $data['DatosEmision']['Adenda'] = $this->crearAdendaBoleto($reservacion);

        list($monto_gravable, $monto_impuesto) = $reservacion->getMontoGravableMasImpuesto();

        $data['DatosEmision']['items']['item'][] = array(
            'BienOServicio' => 'S',
            'NumeroLinea' => 1,
            'Cantidad' => 1,
            'UnidadMedida' => 'UNI',
            'Descripcion' => 'Asiento de bus',
            'PrecioUnitario' => $precio,
            'Precio' => $precio,
            'Descuento' => '0.0000000000',
            'Impuestos' => array(
                'Impuesto' => array(
                    array(
                        'NombreCorto' => 'IVA',
                        'CodigoUnidadGravable' => '1',
                        'MontoGravable' => $monto_gravable,
                        'MontoImpuesto' => $monto_impuesto
                    )
                )
            ),
            'Total' => $precio
        );
        $data['DatosEmision']['Totales']['TotalImpuestos']['TotalImpuesto'][] = array(
            'NombreCorto' => 'IVA',
            'TotalMontoImpuesto' => $monto_impuesto
        );
        $data['DatosEmision']['Adenda']['Encabezado']['DefinicionEncabezado'][3]['ValorEtiqueta'] .= $reservacion->getAsientosNumeros();
        $data['DatosEmision']['Adenda']['Encabezado']['DefinicionEncabezado'][6]['ValorEtiqueta'] .= $reservacion->getCliente()->getNombreFactura();


        $credenciales = $this->credenciales[$this->empresa->getSlug()];

        $response = $this->eforconClient->request(
            'POST',
            'EmitirDteJson',
            [
                'body' => $data,
                'auth_basic' => [$credenciales['eforcon_user'], $credenciales['eforcon_password']]

            ]
        );
        if ($response->getStatusCode() == 200 && \is_array($content = \json_decode($response->getContent(), true))) {

            if ($content['StatusCode'] == 'OK') {

                return $content;
            } else if ($content['StatusCode'] == 'BadRequest' || isset($content['Descipcion'])) {

                return ['error' => $content['Descripcion']];
            }

            return false;
        }

        $content = \json_decode($response->getContent(), true);

        return false;
    }

    public function anular(Reservacion $reservacion, $motivo = 'Reserva no terminada por el cliente en la página Web..') {

        if (!$factura = $reservacion->getFactura()) {
            return true;
        }

        if (!$factura->getDte() || $reservacion->isSatAnulada()) {
            return true;
        }

        $date = $factura->getFecha();
        $date = $date->format('Y-m-d') . 'T' . $date->format('H:i:s');

        // $date_anulacion = new \DateTime();
        // $date_anulacion = $date_anulacion->format('Y-m-d') . 'T' . $date_anulacion->format('H:i:s');

        $data = array(
            'DatosGeneralesAnulacion' => array(
                'FechaEmisionDocumentoAnular' => $date,
                'FechaHoraAnulacion' => $date,
                'IDReceptor' => $reservacion->getCliente()->getNitOrCF('CF'),
                'MotivoAnulacion' => $motivo,
                'NITEmisor' => preg_replace('([^A-Za-z0-9])', '', $this->empresa->getNit()),
                'NumeroDocumentoAAnular' => $factura->getUuid()
            )
        );

        $credenciales = $this->credenciales[$this->empresa->getSlug()];

        $response = $this->eforconClient->request(
            'POST',
            'AnularDteJson',
            [
                'body' => $data,
                'auth_basic' => [$credenciales['eforcon_user'], $credenciales['eforcon_password']]
            ]
        );
        if ($response->getStatusCode() == 200 && $content = \json_decode($response->getContent())) {
            // die(var_dump(array_merge([$credenciales['eforcon_user'], $credenciales['eforcon_password']], $data)));
            if ($content?->StatusCode == 'BadRequest' && !empty($content?->Descripcion)) {

                return ['error' => true, 'text' => $content?->Descripcion];
            }

            return $content?->Resultado || ($content?->StatusCode == 'BadRequest' && u(\strtolower($content?->Descripcion))->containsAny('evi-018'));
        }
        return false;
    }

    public function getDirecciones($nit, $numEsta) {

        // $url = 'https://pruebasfel.eforcon.com/catalogosfel/cat/Establecimiento?Nit=$nit&NoEstablecimiento=$numEsta';

        $response = $this->eforconClient->request(
            'GET',
            "Establecimiento?Nit=$nit&NoEstablecimiento=$numEsta",
            [
                'base_uri' => 'https://pruebasfel.eforcon.com/catalogosfel/cat/',
                'headers' => ['CAT_KEY' => 'AKDKNYZH0NYQVKX82F39x91E3NAXHLQJeZ56L-LE1YAWJAGBG9']
            ]

        );
        if ($response->getStatusCode() == 200 && \is_array($content = \json_decode($response->getContent(), true))) {

            if ((isset($content['StatusCode']) && $content['StatusCode'] == 'BadRequest') || isset($content["Descripcion"])) {
                return ['error' => $content['Descripcion']];
            }

            return $content;
        }

        return ['error' => 'Ha habido un error obteniedo el NIT mde la Sat.'];
    }

    public function extraerDatos(Reservacion $reservacion) {

        $date = $reservacion->getCreatedAt()->format('Y-m-d') . 'T' . $reservacion->getCreatedAt()->format('H:i:s');

        return array(
            'data' => array(
                'DatosEmision' => array(
                    'DatosGeneralesEmision' => array(
                        'CodigoMoneda' => Reservacion::MONEDA_GTQ, //$reservacion->getMoneda(),
                        'FechaHoraEmision' =>  $date,
                        'Tipo' => 'FACT',
                    ),
                    'Emisor' => array(
                        'AfiliacionIVA' => 'GEN',
                        'CodigoEstablecimiento' => $this->empresa->getSatId(),
                        'CorreoEmisor' => 'fuentedelnorte@fuentedelnorte.com',
                        'NITEmisor' => preg_replace('([^A-Za-z0-9])', '', $this->empresa->getNit()),
                        'NombreComercial' => $this->empresa->getNombreComercial(),
                        'NombreEmisor' => $this->empresa->getNombre(),
                        'DireccionEmisor' => array(
                            'Direccion' => $this->empresa->getDireccion(),
                            'CodigoPostal' => '18004',
                            'Municipio' => 'Morales',
                            'Departamento' => 'Izabal',
                            'Pais' => 'GT',
                        ),
                    ),
                    'Receptor' => array(
                        'CorreoReceptor' => '', //No lo tenemos- ------------------------
                        'IDReceptor' => $reservacion->getCliente()->getNitOrCF('CF'),
                        'NombreReceptor' => $reservacion->getCliente()->getNombreFactura(),
                        'DireccionReceptor' => array( //No lo tenemos niguno de los campos abajo
                            'Direccion' => 'Ciudad de Guatemala.',
                            'CodigoPostal' => '0',
                            'Pais' => 'GT'
                        ),
                    ),
                    'Frases' => array(
                        'Frase' => [
                            [
                                'CodigoEscenario' => '1',
                                'TipoFrase' => '1'
                            ]
                        ]
                    ),
                    'items' => array('item' => array()),
                    'Totales' => array(
                        'TotalImpuestos' => [
                            'TotalImpuesto' => []
                        ],
                        'GranTotal' => $reservacion->getPrecio()
                    )
                )
            )
        );
    }

    public function crearAdendaBoleto(Reservacion $reservacion) {



        // $sSalida = $item->getSalida()->getFecha();

        // $sFechaSalida = $sSalida->format('d/m/Y');

        // $sHoraSalida = $sSalida->format('h:i A');

        // $estacionOrigenId = '';
        // if ($estacionOrigen = $item->getEstacionOrigen()) {
        //     $estacionOrigenId = $estacionOrigen->getId();
        //     $estacionOrigen = $estacionOrigen->getNombre();
        // }
        // if ($estacionDestino = $item->getEstacionDestino()) {
        //     $estacionDestino = $estacionDestino->getNombre();
        // }
        // if ($estacionCreacion = $item->getEstacionCreacion()) {
        //     $estacionCreacion = $estacionCreacion->getNombre();
        // }

        return array(
            'Encabezado' =>
            array(
                'DefinicionEncabezado' =>
                array(
                    0 =>
                    array(
                        'CodigoEtiqueta' => '59',
                        'ValorEtiqueta' => uniqid($reservacion->getRuta()->getEstacionSalida()->getEstacionId() . '-'),
                    ),
                    1 =>
                    array(
                        'CodigoEtiqueta' => '127',
                        'ValorEtiqueta' => $reservacion->getSalida()->getSalidaFecha()->format('h:1 A'),
                    ),
                    2 =>
                    array(
                        'CodigoEtiqueta' => '128',
                        'ValorEtiqueta' => $reservacion->getSalida()->getSalidaFecha()->format('d/m/Y'),
                    ),
                    3 =>
                    array(
                        'CodigoEtiqueta' => '129',
                        'ValorEtiqueta' => '',
                    ),
                    4 =>
                    array(
                        'CodigoEtiqueta' => '130',
                        'ValorEtiqueta' => $reservacion->getRuta()->getEstacionSalida()->getNombre(),
                    ),
                    5 =>
                    array(
                        'CodigoEtiqueta' => '131',
                        'ValorEtiqueta' => $reservacion->getRuta()->getEstacionLlegada()->getNombre(),
                    ),
                    6 =>
                    array(
                        'CodigoEtiqueta' => '132',
                        'ValorEtiqueta' => '',
                    ),
                    7 =>
                    array(
                        'CodigoEtiqueta' => '133',
                        'ValorEtiqueta' => $reservacion->getRuta()->getEstacionSalida()->getNombre(),
                    ),
                    8 =>
                    array(
                        'CodigoEtiqueta' => '135',
                        'ValorEtiqueta' => 'T',
                    ),
                    9 =>
                    array(
                        'CodigoEtiqueta' => '198',
                        'ValorEtiqueta' => '',
                    )
                ),
            ),
        );
    }

    public function consultarNitReceptor($nitReceptor) {

        $credenciales = $this->credenciales[$this->empresa->getSlug()];


        $response = $this->eforconClient->request(
            'GET',
            '?NIT=' . $nitReceptor,
            [
                // 'base_uri' => 'https://fel.eforcon.com/catalogosfel/cat/',
                'base_uri' => 'https://fel.eforcon.com/apinitcontribuyente/receptor/Consulta/',
                // 'headers' => ['CAT_KEY' => $credenciales["CYBERSOURCE_MERCHANT_KEY_ID"]]
                'auth_basic' => [$credenciales['eforcon_user'], $credenciales['eforcon_password']]
            ]

        );
        if (\is_array($content = \json_decode($response->getContent(), true))) {

            if ($content['StatusCode'] == 'OK') {
                return $content;
            } else if ($content['StatusCode'] == 'BadRequest' || isset($content["Descripcion"])) {

                return ['error' => $content['Descripcion']];
            }
        }

        return ['error' => 'Ha habido un error obteniedo el NIT mde la Sat.'] . $response->getStatusCode() == 200;
    }
}
