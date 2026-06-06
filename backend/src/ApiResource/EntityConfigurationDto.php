<?php

namespace App\ApiResource;

use ApiPlatform\Doctrine\Orm\Filter\ExactFilter;
use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Entity\Configuration\CollectionFieldConfig;
use App\Entity\Configuration\EntityConfiguration;
use App\Entity\Configuration\FormFieldConfig;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\ObjectMapper\Attribute\Map;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
  shortName: 'EntityConfigurationDto',
  normalizationContext: ['groups' => ['read:dto']],
  stateOptions: new Options(entityClass: EntityConfiguration::class),
  // operations: [
  //   new GetCollection(
  //     order: ['collectionFieldConfig.position' => 'ASC', 'formFields.position' => 'ASC'],
  //     paginationEnabled: false,
  //     parameters: [
  //       'entityClass' => new QueryParameter(
  //         filter: new ExactFilter(),
  //         property: 'entityClass'
  //       ),
  //     ],
  //   ),
  // ]
)]
#[Map(source: EntityConfiguration::class)]
final class EntityConfigurationDto {

  #[Groups(['read:dto'])]
  // #[Map(transform: [self::class, 'collectionFieldConfiToArray'])]
  public array $collectionFieldConfig;

  #[Groups(['read:dto'])]
  // #[Map(transform: [self::class, 'formFieldsToArray'])]
  public array $formFields;

  public static function formFieldsToArray(Collection  $formFields, object $source): mixed {
    return $formFields->filter(fn(FormFieldConfig $formField) => $formField->isVisible())->map(function (FormFieldConfig $formField) {
      $temp = (array) $formField;
      $temp2 = ['input' => ['id' => $formField->getField()]];
      foreach ($temp as $key => $value) {
        if (!$value || $key === 'entityConfig') continue;
        if (in_array($key, ['label', 'attrs'])) {
          $temp2['input'][$key] = $value;
        } else {
          $temp2[$key] = $value;
        }
      }
      return $temp2;
    })->toArray();
  }

  public static function collectionFieldConfiToArray(Collection $collectionFieldConfig, object $source): mixed {
    return $collectionFieldConfig->filter(fn(CollectionFieldConfig $v) => $v->isVisible())->toArray();
  }
}
