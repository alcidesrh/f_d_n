<?php

namespace App\EventListener\Doctrine;

use App\Entity\Asiento;
use App\Entity\Reservacion;
use App\Entity\SalidaReservacion;
use App\EntitySistemaFdn\BoletoPaginaAsientoTemp;
use App\EntitySistemaFdn\BoletoPaginaTemp;
use App\Repository\BoletoPaginaTempRepository;
use App\Services\Factories\ReservacionFactory;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: SalidaReservacion::class)]
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: SalidaReservacion::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemove', entity: SalidaReservacion::class)]

#[AsEntityListener(event: Events::postPersist, method: 'postPersistAsiento', entity: Asiento::class)]
#[AsEntityListener(event: Events::preRemove, method: 'preRemoveAsiento', entity: Asiento::class)]


class ReservacionListener {


    private ?Reservacion $reservacion = null;

    private ?BoletoPaginaTempRepository $boleto_pagina_temp_repository = null;

    public function __construct(private EntityManagerInterface $systemfdnEntityManager, private ReservacionFactory $reservacionFactory) {
        try {

            $this->reservacion = $reservacionFactory();

            $this->boleto_pagina_temp_repository = $systemfdnEntityManager->getRepository(BoletoPaginaTemp::class);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function postUpdate(SalidaReservacion $salidaReservacion, PostUpdateEventArgs $event) {
        try {


            if (!$salidaReservacion->getSalidaId() || !$salidaReservacion->getReservacion()) {
                return;
            }

            // if (!$salida_vieja = $event->getOldValue('salida_id')) {

            //     return $this->postPersist($salidaReservacion);
            // } else
            // if ($event->getNewValue('salida_id')) {

            $respond = $this->boleto_pagina_temp_repository->update(

                $this->systemfdnEntityManager->getClassMetadata(BoletoPaginaTemp::class)->table['name'],
                [
                    'salida_id' => $salidaReservacion->getSalidaId(),
                    'fecha_actualizacion' => "'" . (new DateTime())->format('Y-m-d H:i:s') . "'",
                    'fecha_salida' => "'" . $salidaReservacion->getSalidaFechaConHora()->format('Y-m-d H:i:s') . "'"
                ],

                "salida_id = {$salidaReservacion->getSalidaId()} and reservacion_id = {$salidaReservacion->getReservacion()->getId()}"

            );
            if (!\is_array($respond)) {

                if ($respond->rowCount() == 0) {
                    $this->postPersist($salidaReservacion);
                }
            }


            // }
            // else if ($id = $event->getOldValue('salida_id')) {

            //     $salidaReservacion_clone = clone $salidaReservacion;

            //     $salidaReservacion_clone->setSalidaId($id);

            //     return $this->preRemove($salidaReservacion_clone);
            // }

        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function postPersist(SalidaReservacion $salidaReservacion) {
        try {


            if ($salidaReservacion->getSalidaId()) {
                try {
                    return 1 == $this->boleto_pagina_temp_repository->insert(

                        $this->systemfdnEntityManager->getClassMetadata(BoletoPaginaTemp::class)->table['name'],
                        [
                            'salida_id' => $salidaReservacion->getSalidaId(),
                            'fecha_creacion' =>    $fecha = "'" . (new DateTime())->format('Y-m-d H:i:s') . "'",
                            'fecha_actualizacion' => $fecha,
                            'reservacion_id' => $this->reservacion->getId(),
                            'regreso' => $salidaReservacion->getRegreso(),
                            'fecha_salida' => "'" . $salidaReservacion->getSalidaFechaConHora()->format('Y-m-d H:i:s') . "'"

                        ]
                    )->rowCount();
                } catch (\Throwable $th) {
                    throw $th;
                }
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function preRemove(SalidaReservacion $salidaReservacion) {
        try {
            $this->boleto_pagina_temp_repository->deleteSalida($salidaReservacion);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function postPersistAsiento(Asiento $asiento) {
        try {


            $salida_reservacion = $asiento->getSalidaReservacion();

            if ($bolet_pagina_temp = $this->systemfdnEntityManager->getRepository(BoletoPaginaTemp::class)->findOneBy([
                'salida' => $salida_reservacion->getSalidaId(),
                'reservacion' => $this->reservacion->getId()
            ])) {
                return 1 == $this->boleto_pagina_temp_repository->insert(

                    $this->systemfdnEntityManager->getClassMetadata(BoletoPaginaAsientoTemp::class)->table['name'],
                    [
                        'reservacion_id' => $this->reservacion->getId(),
                        'asiento_id' => $asiento->getAsientoId(),
                        'boleto_pagina_temp_id' =>  $bolet_pagina_temp->getId(),
                        'salida_id' => $salida_reservacion->getSalidaId(),
                        'fecha_creacion' => "'" . (new DateTime())->format('Y-m-d H:i:s') . "'",

                    ]
                )->rowCount();
            }
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function preRemoveAsiento(Asiento $asiento) {
        try {
            return $this->boleto_pagina_temp_repository->deleteAsiento($asiento);
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
