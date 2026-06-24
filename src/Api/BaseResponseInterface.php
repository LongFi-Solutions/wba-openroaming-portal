<?php

namespace App\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

interface BaseResponseInterface
{
    public function toResponse(): JsonResponse;
}
