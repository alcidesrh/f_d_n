<?php

namespace App\Repository;

use App\Services\Collection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

class CustomEntityRepository extends ServiceEntityRepository {

  protected Collection $items;
  private EntityManagerInterface $em;

  public function __construct(ManagerRegistry $registry, string $entityClass = "") {
    $this->items = new Collection();
    parent::__construct($registry, $entityClass);
    $this->em = $this->getEntityManager();
  }
  public function save($object) {

    $this->em->persist($object);
    $this->flush();
  }
  public function persist($object): self {
    $this->em->persist($object);
    return $this;
  }
  public function flush() {
    $this->em->flush();
  }
  public function clear(): self {
    $this->items->clear();
    return $this;
  }

  public function all(): self {
    $this->clear()->items->value(parent::findAll());
    return $this;
  }
  public function remove($item = null): self {
    if ($item) {
      if ($temp = $this->items->findFirstKeyAndValue(fn($v) => $v->getId() == $item->getId())) {
        $this->em->remove($temp['value']);
        $this->flush();
        $this->items->remove($temp['key']);
      }
    } else {
      $this->items->each(fn($v) => $this->em->remove($v));
      $this->flush();
      $this->clear();
    }
    return $this;
  }

  public function __toString() {
    return 'aa';
    $this->items->toArray();
  }
}
