<?php

namespace App\Validator\Constraints;

use App\DTO\SMSProviderParamDTO;
use App\Enum\ParamType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class ValueMatchesParamTypeValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValueMatchesParamType) {
            throw new UnexpectedTypeException($constraint, ValueMatchesParamType::class);
        }

        if (!$value instanceof SMSProviderParamDTO) {
            throw new UnexpectedValueException($value, SMSProviderParamDTO::class);
        }

        // Let NotNull/NotBlank handle empty type or value on their own
        if ($value->type === null || $value->value === null || $value->value === '') {
            return;
        }

        match ($value->type) {
            ParamType::JSON => $this->validateJson($value->value, $constraint),
            ParamType::BOOLEAN => $this->validateBoolean($value->value, $constraint),
            ParamType::XML => $this->validateXml($value->value, $constraint),
            ParamType::STRING => null, // any non-blank string is already valid
        };
    }

    private function validateJson(string $value, ValueMatchesParamType $constraint): void
    {
        if (!json_validate($value)) {
            $this->context->buildViolation($constraint->jsonMessage)
                ->atPath('value')
                ->addViolation();
        }
    }

    private function validateBoolean(string $value, ValueMatchesParamType $constraint): void
    {
        $normalized = strtolower(trim($value));

        if (!in_array($normalized, ['true', 'false', '1', '0'], true)) {
            $this->context->buildViolation($constraint->booleanMessage)
                ->atPath('value')
                ->addViolation();
        }
    }

    private function validateXml(string $value, ValueMatchesParamType $constraint): void
    {
        $previousSetting = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($value);
        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        if ($doc === false) {
            $this->context->buildViolation($constraint->xmlMessage)
                ->atPath('value')
                ->addViolation();
        }
    }
}
