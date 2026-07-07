<?php

namespace App\Validator\Constraints;

use App\DTO\NetworkDTO;
use Doctrine\DBAL\Connection;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidNetworkGeometryValidator extends ConstraintValidator
{
    public function __construct(
        private Connection $connection
    ) {}

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidNetworkGeometry) {
            throw new UnexpectedTypeException($constraint, ValidNetworkGeometry::class);
        }

        if (!$value instanceof NetworkDTO) {
            return;
        }

        if (!$value->geometryJson || !$value->networkId) {
            return;
        }

        $sql = '
            SELECT ssid 
            FROM access_point 
            WHERE network_id = :network_id 
              AND ST_Contains(ST_GeomFromGeoJSON(:new_geometry), location) = 0
        ';

        try {
            $pointsOutside = $this->connection->fetchFirstColumn($sql, [
                'network_id' => $value->networkId,
                'new_geometry' => $value->geometryJson,
            ]);
        } catch (\Exception $e) {
            $this->context->buildViolation('invalidGeometryFormat')
                ->atPath('geometryJson')
                ->addViolation();
            return;
        }

        if (count($pointsOutside) > 0) {
            $pointsList = implode(', ', $pointsOutside);

            $message = count($pointsOutside) === 1
                ? $constraint->messageSingle
                : $constraint->messageMultiple;

            $this->context->buildViolation($message)
                ->setParameter('%s', $pointsList)
                ->atPath('geometryJson')
                ->addViolation();
        }
    }
}