<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'factura')]
class Factura
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(name: 'nombre_resolucion_factura', type: 'string', length: 255)] private string $nombreResolucionFactura;
    #[ORM\Column(name: 'fecha_emision_resolucion_factura', type: 'date')] private \DateTime $fechaEmisionResolucionFactura;
    #[ORM\Column(name: 'fecha_vencimiento_resolucion_factura', type: 'date')] private \DateTime $fechaVencimientoResolucionFactura;
    #[ORM\Column(name: 'serie_resolucion_factura', type: 'string', length: 6)] private string $serieResolucionFactura;
    #[ORM\Column(name: 'minimo_resolucion_factura', type: 'bigint')] private int $minimoResolucionFactura;
    #[ORM\Column(name: 'maximo_resolucion_factura', type: 'bigint')] private int $maximoResolucionFactura;
    #[ORM\Column(name: 'valor_resolucion_factura', type: 'bigint')] private int $valorResolucionFactura;
    #[ORM\Column(name: 'nombre_resolucion_sistema', type: 'string', length: 255)] private string $nombreResolucionSistema;
    #[ORM\Column(name: 'fecha_emision_resolucion_sistema', type: 'date')] private \DateTime $fechaEmisionResolucionSistema;
    #[ORM\Column(name: 'fecha_vencimiento_resolucion_sistema', type: 'date')] private \DateTime $fechaVencimientoResolucionSistema;
    #[ORM\Column(type: 'boolean')] private bool $activo;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_id', referencedColumnName: 'id')] private ?Estacion $estacion = null;
    #[ORM\ManyToOne(targetEntity: Empresa::class)] #[ORM\JoinColumn(name: 'empresa_id', referencedColumnName: 'id')] private ?Empresa $empresa = null;
    #[ORM\ManyToOne(targetEntity: ServicioEstacion::class)] #[ORM\JoinColumn(name: 'servicio_estacion_id', referencedColumnName: 'id')] private ?ServicioEstacion $servicioEstacion = null;
    #[ORM\ManyToOne(targetEntity: Impresora::class)] #[ORM\JoinColumn(name: 'impresora_id', referencedColumnName: 'id')] private ?Impresora $impresora = null;
    public function getId(): ?int { return $this->id; }
}
