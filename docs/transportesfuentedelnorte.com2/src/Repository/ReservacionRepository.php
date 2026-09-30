<?php

namespace App\Repository;

use App\Entity\Reservacion;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservacion>
 *
 * @method null|Reservacion find($id, $lockMode = null, $lockVersion = null)
 * @method null|Reservacion findOneBy(array $criteria, array $orderBy = null)
 * @method Reservacion[]    findAll()
 * @method Reservacion[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReservacionRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Reservacion::class);
    }

    public function add(Reservacion $entity, bool $flush = false): void {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Reservacion $entity, bool $flush = false): void {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     *  @return Reservacion[] Returns an array of Reservacion objects
     **/
    public function getReservacionVencidas(int $minutos): array|string {

        $date = (new \DateTime())->sub(new \DateInterval("PT{$minutos}M"));

        return $this->createQueryBuilder('r')
            ->leftJoin('r.cliente', 'c')
            ->andWhere("( r.status != :COMPLETADA and r.status != :ANULADA and r.status != :ANULADA_LOCAL and r.createdAt < :date and c.email != 'noanular@fdn.com')")
            ->setParameter('COMPLETADA', Reservacion::COMPLETADA)
            ->setParameter('ANULADA', Reservacion::ANULADA)
            ->setParameter('ANULADA_LOCAL', Reservacion::ANULADA_LOCAL)
            ->setParameter('date', $date)
            ->orderBy('r.createdAt', 'desc')
            ->setMaxResults(100)
            ->getQuery()->getResult();
    }

    public function getEmailNoEnviado(int $minutos) {
        $inicio_del_dia = new DateTime('now');
        $inicio_del_dia->setTime(0, 0, 0);
        return $this->createQueryBuilder('r')
            ->leftJoin('r.cliente', 'c')
            ->andWhere("(r.transaccion_id is not NULL) and (r.email_enviado is null and r.createdAt > :createdAt) ")
            ->setParameter('createdAt', $inicio_del_dia)
            // ->setParameter('true', 1)
            ->orderBy('r.createdAt', 'desc')
            ->setMaxResults(100)
            ->getQuery()->getResult();
    }

    public function findOneBySomeField($value) {
        return $this->createQueryBuilder('r')
            ->andWhere('r.createdAt > :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getResult();
    }
}
