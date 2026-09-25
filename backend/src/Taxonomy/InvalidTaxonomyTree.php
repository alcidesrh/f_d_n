<?php

declare(strict_types=1);

namespace App\Taxonomy;

/** Árbol recibido que viola las invariantes de la taxonomía (el controlador lo traduce a 422). */
final class InvalidTaxonomyTree extends \InvalidArgumentException
{
}
