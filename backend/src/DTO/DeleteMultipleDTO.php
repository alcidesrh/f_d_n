<?php

namespace App\DTO;

use ApiPlatform\Metadata\ApiProperty;

final class DeleteMultipleDTO
{
    #[ApiProperty(identifier: true, writable: false)]
    public function getId(): string
    {
        return (new \DateTime())->format('Ymdms');
    }
}
