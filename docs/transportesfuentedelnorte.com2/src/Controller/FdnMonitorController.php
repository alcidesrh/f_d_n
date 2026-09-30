<?php

namespace App\Controller;

use App\Entity\Empresa;
use App\Entity\MonitorDatos;
use App\Repository\EmpresaRepository;
use App\Repository\MonitorDatosRepository;
use App\Services\RemoteDatabaseQueries;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FdnMonitorController extends AbstractController {

    #[Route('/monitor', name: 'app_fdn_monitor')]
    public function index(EntityManagerInterface $entityManagerInterface, Request $request): Response {


        if ($session = $request->getSession()) {

            if (!$monitor = $entityManagerInterface->getRepository(MonitorDatos::class)->findOneBy(['session_id' => $session->getId()])) {
                $monitor = new MonitorDatos();
                $entityManagerInterface->persist($monitor);
            }
            if (!$monitor->getSessionId() || $monitor->getUpdatedAt() < (new \DateTime())->sub(new \DateInterval('PT15M'))) {
                $monitor->setIp($request->getClientIp())->setVisitas()->setSessionId($session->getId());
                $entityManagerInterface->flush();
            }
        }
        return $this->render('fdn_monitor/index.html.twig');
    }

    #[Route('/fdn/monitor/{empresa_id?3}/{date?now}/{month?0}', name: 'app_fdn_monitor_data')]
    public function getData(RemoteDatabaseQueries $remoteDatabaseQueries, Empresa $empresa_id, DateTime $date, $month, EmpresaRepository $empresaRepository): Response {


        $empresa = $empresa_id;

        $sql = "SELECT
                    e.nombre as estacion1,
                    e2.nombre as estacion2,
                    FORMAT (s.fecha, 'dd/MM/yyyy HH:mm') as fecha ,
                    DATEPART(HOUR, s.fecha) as hora,
                    DATEPART(year, s.fecha) as 'year',
                    DATEPART(mm, s.fecha) as mes,
                    DATEPART(dd, s.fecha) as dia,
                    emp.alias as empresa,
                    (select count(b.id) from boleto b where b.salida_id = s.id and b.estado_id in (1,2,3)) as boletos,
                    (select count(b.id) from boleto b where b.salida_id = s.id and b.estado_id in (1,2,3) and b.tipo_documento_id = 1) as boletos_factura,
                    (select count(b.id) from boleto b where b.salida_id = s.id and b.estado_id in (1,2,3) and b.tipo_documento_id = 2 ) as boletos_factura_especial,
                    (select count(b.id) from boleto b where b.salida_id = s.id and b.estado_id = 4 ) as anulados,
                    (select count(b.id) from boleto b where b.salida_id = s.id and b.estado_id = 5 ) as reasignados,
                    (select count(b.id) from boleto b where b.salida_id = s.id and b.estado_id in (1,2,3) and b.tipo_documento_id = 3  ) as boletos_cortesia,
                    (select count(b.id) from boleto b where b.salida_id = s.id and b.estado_id in (1,2,3) and b.tipo_documento_id = 4 ) as boletos_factura_otra_estacion,
                    (select count(b.id) from boleto b where b.salida_id = s.id and b.estado_id in (1,2,3) and b.tipo_documento_id = 5 ) as boletos_voucher_agencia,
                    (select count(b.id) from boleto b where b.salida_id = s.id and b.estado_id in (1,2,3) and b.tipo_documento_id = 6  ) as boletos_voucher,
                    (select count(b.id) from boleto b where b.salida_id = s.id and b.estado_id in (1,2,3) and b.tipo_documento_id = 7  ) as boletos_voucher_otra_estacion,
                    (select count(b.id) from boleto b where b.salida_id = s.id and b.estado_id in (1,2,3) and b.tipo_documento_id = 8  ) as boletos_voucher_agencia,
                    (select count(b.id) from boleto b where b.salida_id = s.id and b.estado_id in (1,2,3) and b.tipo_documento_id = 11  ) as boletos_pagina_web,
                    (select count(e.id) from encomienda e where e.primera_salida_id = s.id  and e.tipo_documento_id = 1  ) as encomienda_facturada,
                    (select count(e.id) from encomienda e where e.primera_salida_id = s.id  and e.tipo_documento_id = 2  ) as encomienda_porcobrar,
                    (select count(e.id) from encomienda e where e.primera_salida_id = s.id  and e.tipo_documento_id =  4 ) as encomienda_auth_interna,
                    (select count(e.id) from encomienda e where e.primera_salida_id = s.id  and e.tipo_documento_id =  3 ) as encomienda_cortesia,
                    (select count(r.id) from reservacion r where r.salida_id = s.id and r.estado_id in (1,2)) as reservaciones,
                    (select count(ba.id) from bus_asiento ba join bus_tipo bt on bt.id = ba.tipoBus_id join salida s2 on s2.tipo_bus_id = bt.id where s2.id = s.id) as asientos,
                    s.id
                    from salida s
                    join itineario i on i.id = s.itinerario_id
                    join ruta r on r.codigo = i.ruta_codigo
                    join estacion e on e.id = r.estacion_origen_id
                    join estacion e2 on e2.id = r.estacion_destino_id
                    join empresa emp on emp.id = s.empresa_id
                    where s.estado_id in (1,2,3) and s.fecha BETWEEN ? and ? and s.empresa_id = ?
                    order by e.nombre, s.fecha";



        if ($month) {
            $date->setDate($date->format('y'), $date->format('m'), 1);
            $date2 = (clone $date)->modify('1 month')->format('Ymd');
        } else {
            $date2 = (clone $date)->add(new DateInterval('P1D'))->format('Ymd');
        }
        $date1 = $date->format('Ymd');


        $result = $remoteDatabaseQueries->execute_query($sql, [$date1, $date2, $empresa->getEmpresaId()]);

        $estacion = null;

        $data = [];
        $cont = 0;
        $salidas_cont = 0;
        $expanded = 1;
        $expanded_array = [];
        foreach ($result as $value) {

            if ($value['estacion1'] != $estacion) {

                if (isset($temp)) {
                    $data[$cont++] = $temp;
                }
                $temp = [
                    'label' => 'expanded' . $expanded++,
                    'name' => $value['estacion1'],
                    'icon' => 'restaurant_menu',
                    'header' => 'root',
                    'children' => [],
                ];
                $estacion = $value['estacion1'];
                $expanded_array[] = $temp['label'];
            }
            $temp['children'][] = [
                'id' => $value['id'],
                'label' => 'expanded' . $expanded++,
                'name' =>  $value['estacion2'],
                'icon' => 'restaurant_menu',
                'header' => 'generic',
                'asientos' => $value['asientos'],
                'boletos' => $this->getBoletos($value),
                'encomienda' => $this->getEncomiendas($value),
                'reasignados' => $value["reasignados"],
                'anulacion' => $value["anulados"],
                'fecha' => DateTime::createFromFormat('d/m/Y H:i', $value['fecha'])->format('d/m/Y H:i'),
                'children' => [

                    [
                        'label' => 'Ver Boleto',
                        'header' => 'generic-b',
                        'children' => [
                            [
                                'header' => 'content-b',
                                'body' => 'boletos',
                                'label' => 'Estación',
                                'cant' => $value["boletos_factura"],
                            ],
                            [
                                'header' => 'content-b',
                                'label' => 'Otra estación',
                                'cant' => $value["boletos_factura_otra_estacion"]
                            ],

                            [
                                'header' => 'content-b',
                                'label' => 'Factura Especial',
                                'cant' => $value["boletos_factura_especial"]
                            ],
                            [
                                'header' => 'content-b',
                                'label' => 'cortesia',
                                'cant' => $value["boletos_cortesia"]
                            ],
                            [
                                'header' => 'content-b',
                                'label' => 'voucher',
                                'cant' => $value["boletos_voucher"]
                            ],
                            [
                                'header' => 'content-b',
                                'label' => 'voucher otra E',
                                'cant' => $value["boletos_voucher_otra_estacion"]
                            ],
                            [
                                'header' => 'content-b',
                                'label' => 'pagina web',
                                'cant' => $value["boletos_pagina_web"]
                            ],
                            [
                                'header' => 'content-b',
                                'label' => 'reservaciones',
                                'cant' => $value["reservaciones"]
                            ]

                        ],

                    ],

                    [
                        'label' => 'Ver Encomienda',
                        'header' => 'generic-b',
                        'children' => [
                            [
                                'header' => 'content-b',
                                'label' => 'facturada',
                                'cant' => $value["encomienda_facturada"]
                            ],
                            [
                                'header' => 'content-b',
                                'label' => 'cortesia',
                                'cant' => $value["encomienda_cortesia"]
                            ],
                            [
                                'header' => 'content-b',
                                'label' => 'autorz interna',
                                'cant' => $value["encomienda_auth_interna"]
                            ],

                            [
                                'header' => 'content-b',
                                'label' => 'por cobrar',
                                'cant' => $value["encomienda_porcobrar"]
                            ],

                        ],
                    ],
                    [
                        'label' => 'Anulados',
                        'header' => 'generic-ar',
                        'cant' => $value["anulados"],
                    ],
                    [
                        'header' => 'generic-ar',
                        'label' => 'Reasignados',
                        'cant' => $value["reasignados"]
                    ],
                ],
            ];
            $expanded_array[] = $temp['label'];

            $salidas_cont++;
        }

        if (isset($temp) && !($data[$cont - 1] === $temp)) {
            $data[$cont++] = $temp;
        }

        \usort($data, function ($a, $b) {
            $cant = \count($b['children']);
            if (count($a['children']) == $cant) {
                return 0;
            }
            return count($a['children']) > $cant ? -1 : 1;
        });
        $aux = [];
        foreach ($empresaRepository->findAll() as  $value) {
            $aux[] = [
                'nombre' => \strtolower($value->getAlias()),
                'id' => $value->getId(), 'salidas' => $value->getEmpresaId() == $empresa->getEmpresaId() ? $data : [],
                'activa' => $value->getEmpresaId() == $empresa->getEmpresaId() ?: false, 'salidas_total' => $salidas_cont
            ];
        };

        // $e = new Filesystem();

        $return = ['empresas_data' => [$aux[2], $aux[0], $aux[1]], 'expanded' => $expanded_array];

        // $e->dumpFile('empresa2.json', \json_encode($return));

        return new JsonResponse($return);
    }

    function getBoletos($data) {
        $keys = [
            'boletos_factura', 'boletos_factura_otra_estacion', 'boletos_factura_especial', 'boletos_cortesia', 'boletos_voucher', 'boletos_voucher_otra_estacion', 'boletos_pagina_web'
        ];

        $sum = 0;
        foreach ($keys as $value) {
            $sum += \intval($data[$value]);
        }

        return $sum;
    }

    function getEncomiendas($data) {
        $keys = [
            'encomienda_facturada', 'encomienda_cortesia', 'encomienda_auth_interna', 'encomienda_porcobrar',
        ];

        $sum = 0;
        foreach ($keys as $value) {
            $sum += \intval($data[$value]);
        }

        return $sum;
    }
}
