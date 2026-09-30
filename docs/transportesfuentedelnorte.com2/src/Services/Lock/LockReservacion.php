<?php

namespace App\Services\Lock;

use App\Entity\Reservacion;
use Doctrine\DBAL\Connection;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\Store\DoctrineDbalStore;

class LockReservacion {
    private ?Lock $lock = null;

    public function __construct(private Reservacion $reservacion, private Connection $connection) {
    }

    public function acquire() {
        if (!$this->lock) {
            $key = new Key('reservacion.' . $this->reservacion->getId());

            $this->lock = new Lock(
                $key,
                new DoctrineDbalStore($this->connection),
                300,  // ttl
                false // autoRelease
            );
        }
        return $this->lock->acquire();
    }

    public function release() {
        if ($this->lock) {
            $this->lock->release();
            return true;
        }
        return false;
    }
}
