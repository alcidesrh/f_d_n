<?php

namespace App\Repository;

use App\Entity\Asiento;
use App\Entity\Reservacion;
use App\Entity\SalidaReservacion;
use App\EntitySistemaFdn\BoletoPaginaAsientoTemp;
use App\EntitySistemaFdn\BoletoPaginaTemp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityRepository;

/**
 * @extends ServiceEntityRepository<BoletoPaginaTemp>
 *
 * @method BoletoPaginaTemp|null find($id, $lockMode = null, $lockVersion = null)
 * @method BoletoPaginaTemp|null findOneBy(array $criteria, array $orderBy = null)
 * @method BoletoPaginaTemp[]    findAll()
 * @method BoletoPaginaTemp[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BoletoPaginaTempRepository extends EntityRepository {

    public function save(BoletoPaginaTemp $entity, bool $flush = false): void {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(BoletoPaginaTemp $entity, bool $flush = false): void {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function insert($table, $values) {
        return $this->tryCatchExecuteQuery(
            $this->getEntityManager()->getConnection()->createQueryBuilder()->insert($table)
                ->values($values)
        );
    }

    public function tryCatchExecuteQuery(QueryBuilder $queryBuilder) {
        try {
            return $queryBuilder->executeQuery();
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'code' => $e->getCode()];
        }
    }

    public function update($table, $values, $where) {
        $conn = $this->getEntityManager()->getConnection()->createQueryBuilder()->update($table)->where($where);

        foreach ($values as $key => $value) {
            $conn->set($key, $value);
        }

        return $this->tryCatchExecuteQuery(
            $conn
        );
    }

    public function execute_delete($table, $where) {
        return $this->tryCatchExecuteQuery(
            $this->getEntityManager()->getConnection()->createQueryBuilder()
                ->delete($table)->where($where)
        );
    }

    public function deleteSalida(SalidaReservacion $salida) {

        return  $this->execute_delete(

            $this->getClassMetadata(BoletoPaginaTemp::class)->table['name'],

            "regreso = {$salida->getRegreso()} and reservacion_id = {$salida->getReservacion()->getId()}"

        );
    }

    public function deleteAsiento(Asiento $asiento) {

        return 1 == $this->execute_delete(
            $this->getEntityManager()->getClassMetadata(BoletoPaginaAsientoTemp::class)->table['name'],
            "reservacion_id = {$asiento->getSalidaReservacion()->getReservacion()->getId()} and asiento_id = {$asiento->getAsientoId()}"

        )->rowCount();
    }

    public function delete(Reservacion $reservacion) {

        if (!empty($salidas = $reservacion->getSalidasArray())) {

            foreach ($salidas as $salida) {

                if (!empty($asientos = $salida->getAsientos()->toArray())) {

                    $asientos_sql = "";

                    foreach ($asientos as $asiento) {

                        $asientos_sql .= ($asientos_sql ? ' or ' : '') . "asiento_id = {$asiento->getAsientoId()}";
                    }

                    $this->execute_delete(

                        $this->getEntityManager()->getClassMetadata(BoletoPaginaAsientoTemp::class)->table['name'],

                        "reservacion_id = {$reservacion->getId()} and ($asientos_sql)"

                    );
                }
                $this->execute_delete(

                    $this->getClassMetadata(BoletoPaginaTemp::class)->table['name'],

                    "regreso = {$salida->getRegreso()} and reservacion_id = {$salida->getReservacion()->getId()}"
                );
            }
        }
    }
}
