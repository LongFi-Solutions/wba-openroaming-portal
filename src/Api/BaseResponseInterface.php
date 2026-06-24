<?php

declare(strict_types=1);

namespace App\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

interface BaseResponseInterface
{
    public function toResponse(): JsonResponse;
}
