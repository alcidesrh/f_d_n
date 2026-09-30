<?php

namespace App\Services;

use App\Entity\Asiento;
use App\Entity\Empresa;
use App\Entity\Factura;
use App\Entity\Reservacion;
use App\EntitySistemaFdn\BoletoPaginaAsientoTemp;
use App\EntitySistemaFdn\BoletoPaginaTemp;
use DateTime;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RemoteDatabaseQueries
{
    public function __construct(
        private Reservacion $reservacion,
        private EntityManagerInterface $systemfdn,
        private EntityManagerInterface $entityManagerInterface,
        private HttpClientInterface $fdnClient,
        private ServerSentEvent $serverSentEvent,
        #[Autowire('%reservacion_minutos%')] private $reservacion_minutos,
    ) {}

    public function getEstaciones(): array
    {

        // $sql = 'SELECT DISTINCT e.id as estacion_id, e.nombre as estacion_nombre, e.direccion as estacion_direccion, e.alias as estacion_alias, e.inicia_ruta as estacion_activa, d.nombre as departamento_nombre, d.id as departamento_id
        //         from estacion e
        //         inner join departamento d on e.departamento_id = d.id';

        $sql = 'SELECT DISTINCT e.id as estacion_id, e.nombre as estacion_nombre, e.direccion as estacion_direccion, e.alias as estacion_alias, e.inicia_ruta as estacion_activa, d.nombre as departamento_nombre, d.id as departamento_id
                from salida s
                inner join itineario on itineario.id = s.itinerario_id
                inner join ruta on itineario.ruta_codigo = ruta.codigo
                inner join estacion e on ruta.estacion_origen_id = e.id
                inner join departamento d on e.departamento_id = d.id
                where s.fecha > ?';

        $sql2 = 'SELECT DISTINCT e.id as estacion_id, e.nombre as estacion_nombre, e.direccion as estacion_direccion, e.alias as estacion_alias, e.inicia_ruta as estacion_activa, d.nombre as departamento_nombre, d.id as departamento_id
                from salida s
                inner join itineario on itineario.id = s.itinerario_id
                inner join ruta on itineario.ruta_codigo = ruta.codigo
                inner join estacion e on ruta.estacion_destino_id = e.id
                inner join departamento d on e.departamento_id = d.id
                where s.fecha > ?';

        $ids = [];

        $date = ((new \DateTime())->sub(new \DateInterval('P1D')))->format('Ymd H:i');

        return \array_filter(
            [...$this->execute_query($sql, [$date]), ...$this->execute_query($sql2, [$date])],
            function ($item) use (&$ids) {
                if (!\in_array($item['estacion_id'], $ids)) {
                    $ids[] = $item['estacion_id'];

                    return true;
                }

                return false;
            }
        );
    }

    public function getSalidas($salida, $llegada, $fecha, $salida_minutos_antes): array|null
    {

        $fecha->setTime(0, 0, 0);
        $fecha2 = clone $fecha;
        $fecha2->setTime(23, 59, 59);

        $fecha_minima = (new DateTime('now'))->add(new \DateInterval("PT{$salida_minutos_antes}M"));

        $fecha = $fecha->format('Ymd H:i');
        $fecha2 = $fecha2->format('Ymd H:i');
        $fecha_minima = $fecha_minima->format('Ymd H:i');
        $sql = 'SELECT DISTINCT
        porciento_tarifa_agencia, salida.empresa_id,
        salida.id as salida_id,
         salida.fecha as horario, bus_clase.nombre as bus_clase, ruta.kilometros,
          ruta.codigo,
          (select tiempo.minutos from tiempo where tiempo.clasebus_id = bus_clase.id and tiempo.ruta_codigo = ruta.codigo and tiempo.estacion_destino_id = ruta.estacion_destino_id) as minutos,
        (select count(ba.id) from bus_asiento ba join bus_tipo bt on bt.id = ba.tipoBus_id join salida s on s.tipo_bus_id = bt.id where s.id = salida.id) as total_asientos,
        (select count(b.id) from boleto b where b.salida_id = salida.id and b.estado_id in (1,2,3)) as boletos,
        -- (select count(r.id) from reservacion r where r.salida_id = salida.id and r.estado_id in (1,2)) as reservaciones,
        (select top 1 tb.tarifaValor from tarifas_boleto tb where tb.estacion_origen_id = ruta.estacion_origen_id and tb.estacion_destino_id = ruta.estacion_destino_id  and tb.clase_asiento_id = 2 and tb.clase_bus_id = bus_clase.id) as TarifaB,
        (select top 1 tb.tarifaValor from tarifas_boleto tb where tb.estacion_origen_id = ruta.estacion_origen_id and tb.estacion_destino_id = ruta.estacion_destino_id and tb.clase_asiento_id = 1 and tb.clase_bus_id = bus_clase.id) as TarifaA

        from salida
        left join bus_tipo on salida.tipo_bus_id = bus_tipo.id
        left join bus_clase on bus_tipo.clase_id = bus_clase.id
        left join itineario on itineario.id = salida.itinerario_id
        left join ruta on itineario.ruta_codigo = ruta.codigo
        left join salida_estado on salida_estado.id = salida.estado_id
        left join estacion e on e.id = ruta.estacion_origen_id
        where
        salida_estado.id in (1,2,3) and ruta.estacion_origen_id = ? and ruta.estacion_destino_id = ? and salida.fecha > ? and salida.fecha between ? and ?
        order by salida.fecha asc';

        return $this->execute_query($sql, [$salida, $llegada, $fecha_minima, $fecha, $fecha2]);
    }

    public function getAsientos($salida_id): array|null
    {

        $sql_asientos = 'SELECT DISTINCT ba.id, ba.nivel2 , ba.coordenadaX , ba.coordenadaY , ba.numero, b.id as boleto, r.id as reservacion,
                ca.id as clase, bp.id as boleto_pagina from bus_asiento as ba
                left join bus_tipo bt on bt.id = ba.tipoBus_id
                left join salida s on s.tipo_bus_id  = bt.id
                left join clase_asiento ca on ca.id = ba.clase_id
                left join reservacion r on r.asiento_bus_id = ba.id and r.salida_id = ? and r.estado_id = 1
                left join boleto b on b.asiento_bus_id = ba.id and b.estado_id IN (1, 2, 3) and b.salida_id = ? and ( b.pagina_web_reserva_id is NULL or b.pagina_web_reserva_id != ? )
                left join boleto_pagina_asiento_temp bp on bp.asiento_id = ba.id and bp.reservacion_id != ? and bp.salida_id = ?
                where s.id = ?';

        $sql_senales = 'SELECT bus_senal.nivel2, bus_senal.coordenadaX, bus_senal.coordenadaY, bus_senal_tipo.id as conductor_puerta from bus_tipo
                left join salida on bus_tipo.id = salida.tipo_bus_id
                left join bus_senal on bus_senal.tipoBus_id = bus_tipo.id
                left join bus_senal_tipo on bus_senal_tipo.id = bus_senal.tipo_id
                where salida.id = ?';

        if ($asientos = $this->execute_query($sql_asientos, [...\array_fill(0, 2, $salida_id), ...\array_fill(0, 2, $this->reservacion->getId()), ...\array_fill(0, 2, $salida_id)])) {
            $senales = $this->execute_query($sql_senales, [$salida_id]);

            return [$asientos, $senales];
        }

        return null;
    }

    public function test()
    {
        $sql = 'SELECT * from estacion e where e.activo = 1 and e.numEstablecimientoSatMayaDeOro  is not null and e.numEstablecimientoSat is null';

        $estaciones =  $this->execute_query($sql);

        $sql = 'SELECT * from empresa e where e.id in (2) and e.activo = 1';
        $empresas =  $this->execute_query($sql);

        return ['estaciones' => $estaciones, 'empresas' => $empresas];
    }

    public function updateDireccionEstacion($id, $direccion)
    {

        $sql = "UPDATE estacion SET direccion  = '$direccion' WHERE id = $id";

        return $this->execute_query_update($sql);
    }


    public function enviarFacturaSistema(?Factura $factura = null)
    {
        $factura = $factura ?? $this->reservacion->getFactura();
        $sql = "UPDATE factura_generada SET sNumeroDTEsat  = '{$factura->getDte()}', sAutorizacionUUIDsat = '{$factura->getUuid()}', sSerieDTEsat = '{$factura->getSerie()}', sFechaCertificaDTEsat = '{$factura->getFecha()->format('Y-m-d H:i:s')}'
        WHERE id = '{$factura->getIdSistema()}'";

        return $this->execute_query($sql);
    }

    public function getAsientosOcupados()
    {

        $sql = "SELECT ba.id from bus_asiento ba where ba.id in ids and ( ba.id in (
                    select b.asiento_bus_id  from boleto b where b.salida_id = ? and b.estado_id in (1 ,2 ,3)
                )
                or ba.id in (
                    select r.asiento_bus_id  from reservacion r where r.salida_id = ? and r.estado_id  = 1
                )
                or ba.id in (
                    select bp.asiento_id  from boleto_pagina_asiento_temp bp where bp.salida_id = ? and bp.reservacion_id != ?
                ) )";

        $asientos_ocupados = [];

        foreach ($this->reservacion->getSalidasArray() as $salida) {
            $ids = '(' . implode(', ', $salida->getAsientosIds()) . ')';
            $sql = str_replace('ids', $ids, $sql);

            $asientos_ocupados = $this->execute_query($sql, [...\array_fill(0, 3, $salida->getSalidaId()), $this->reservacion->getId()]);
        }

        return $asientos_ocupados;
    }

    public function getAsientosTempPrecios(Reservacion $reservacion)
    {
        try {
            $datos = [];

            foreach ($reservacion->getSalidasArray() as $salida) {
                $datos[] = [
                    'salida' => $salida->getSalidaId(),
                    'asientos' => $salida->getAsientos()->map(fn(Asiento $item) => [$item->getAsientoId(), $item->getId()])->toArray()
                ];
            }

            return $this->request('calcularImporteTotalMonedaBase.json', ['datos' => $datos]);
        } catch (TransportExceptionInterface $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function getSalidasAsientoPrecio($salidas)
    {
        try {
            $datos = \array_map(function ($salida) {
                return ['salida' => $salida['salida_id']];
            }, $salidas);

            return $this->request('calcularAsientosSalidas.json', ['datos' => $datos]);
        } catch (TransportExceptionInterface $e) {
            throw new \Exception($e->getMessage());
        }
    }


    public function crearBoleto($param)
    {



        try {
            return  $this->request('emitirBoletos.json', $param);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function anularReservacion(Reservacion|int $reservacion = null)
    {

        $reservacion = $reservacion ?: $this->reservacion;

        $id_pagina = 0;

        if (!\is_numeric($reservacion)) {
            $id_pagina = $reservacion->getId();
        }

        try {
            return $this->request('anularBoleto', [
                'id' =>  match (true) {
                    \is_numeric($reservacion) => $reservacion,
                    default => $reservacion->getBoletoTicketId()
                },
                'id_pagina' => $id_pagina
            ]);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }

        return false;
    }

    public function setAsientosTemp(Reservacion $reservacion = null)
    {
        $reservacion = $reservacion ?? $this->reservacion;


        $result = $this->request('salida-asientos-temp', [
            'salida' => 545534,
            'asientos' => json_encode([2502, 2503])
        ]);

        return isset($result['anulado']) && $result['anulado'];
    }

    public function anularPorId(int $id = 5902389)
    {
        $param = ['id' => $id];

        try {
            $result = $this->request('anularBoleto', $param);

            if (isset($result['anulado'])) {
                if ($result['anulado']) {
                    return true;
                }
            }
            if (isset($result['error'])) {
                $error = ['error' => $result['error']];
            } else {
                $error = ['error' => 'desconocido'];
            }

            return $error;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }

        return false;
    }

    public function getEmpresa()
    {
        $sql = 'SELECT x.* FROM empresa x where id = 1 or id = 2 or id = 7';

        if ($result = $this->execute_query($sql)) {
            foreach ($result as $key => $value) {
                $empresa = $this->entityManagerInterface->getRepository(Empresa::class)->findOneBy(['nombre' => $value['nombre']]);
                $empresa->setAlias($value['alias'])->setDireccion($value['direccion'])->setNit($value['nit'])->setEmpresaId($value['id'])->setNombreComercial($value['nombreComercial']);

                $this->entityManagerInterface->persist((new Empresa)->setNombre($value['nombre']));
            }
        }
    }

    public function deleteAsientoTemp($reservacion_id = null)
    {

        $repo = $this->systemfdn->getRepository(BoletoPaginaTemp::class);

        if ($reservacion_id) {

            $repo->execute_delete(
                $this->systemfdn->getClassMetadata(BoletoPaginaAsientoTemp::class)->table['name'],
                "reservacion_id = $reservacion_id"
            );

            return $repo->execute_delete(
                $this->systemfdn->getClassMetadata(BoletoPaginaTemp::class)->table['name'],
                "reservacion_id = $reservacion_id"
            );
        }

        $date = (new \DateTime())->sub(new \DateInterval("PT{$this->reservacion_minutos}M"));
        $date = $date->format('Y-m-d H:i:s');


        $repo->execute_delete(
            $this->systemfdn->getClassMetadata(BoletoPaginaAsientoTemp::class)->table['name'],
            "fecha_creacion < '{$date}'"
        );

        return $repo->execute_delete(
            $this->systemfdn->getClassMetadata(BoletoPaginaTemp::class)->table['name'],
            "fecha_creacion < '{$date}'"

        );
    }

    public function getEmpresaIdPorBoletoId($boleto_id)
    {

        $sql = "select e.id from empresa e
                join salida s on s.empresa_id = e.id
                join boleto b on b.salida_id = s.id
                where b.id = $boleto_id
                ";

        return $this->execute_query($sql);
    }


    public function execute_query($sql, $params = [], $types = [ParameterType::STRING])
    {

        try {
            return $this->systemfdn->getConnection()->executeQuery($sql, $params, $types)->fetchAllAssociative();
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    public function execute_query_update($sql)
    {

        try {
            return $this->systemfdn->getConnection()->executeStatement($sql);
        } catch (\Throwable $e) {
            throw $e;
        }
    }
    public function request(string $endpoint, array $params, string $method = 'POST')
    {

        try {

            return json_decode(
                ($this->fdnClient->request(
                    $method,
                    $endpoint,
                    [
                        'body' => $params,
                    ]
                )
                )->getContent(),
                true
            );
        } catch (\Throwable $e) {

            throw $e;

            // return ['error' => $e->getMessage(), 'code' => $e->getCode()];
        }

        return false;
    }
}
