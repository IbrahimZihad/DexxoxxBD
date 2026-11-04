<?php
declare(strict_types=1);

namespace App\Security;

use App\Exceptions\ValidationException;

/**
 * Rule-string validator: 'name' => 'required|string|max:120'.
 * A missing field is ignored; a field sent as null or empty against a `nullable`
 * rule is kept as an explicit null so callers can clear a value. validated()
 * returns type-cast values so controllers never touch the raw request again.
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $validated = [];

    /** @var list<string> rules for the field currently being checked */
    private array $activeRules = [];

    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $labels = []
    ) {
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    public function passes(): bool
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $present = array_key_exists($field, $this->data);
            $value = $this->data[$field] ?? null;
            $isEmpty = $value === null || $value === '' || (is_array($value) && $value === []);

            $required = in_array('required', $rules, true);

            if (!$present || $isEmpty) {
                if ($required) {
                    $this->fail($field, 'is required.');
                } elseif ($present && in_array('nullable', $rules, true)) {
                    $this->validated[$field] = null;
                }
                continue;
            }

            $this->activeRules = $rules;

            foreach ($rules as $rule) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }

                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

                if (!$this->applyRule($field, $name, $param, $value)) {
                    break;
                }
            }

            if (!isset($this->errors[$field]) && !array_key_exists($field, $this->validated)) {
                $this->validated[$field] = $value;
            }
        }

        return $this->errors === [];
    }

    /** @return array<string,mixed> */
    public function validated(): array
    {
        if (!$this->passes()) {
            throw new ValidationException($this->errors);
        }

        return $this->validated;
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        if ($this->errors === []) {
            $this->passes();
        }

        return $this->errors;
    }

    private function applyRule(string $field, string $name, ?string $param, mixed $value): bool
    {
        $label = $this->label($field);

        switch ($name) {
            case 'string':
                if (!is_string($value)) {
                    return $this->fail($field, 'must be text.');
                }
                $this->validated[$field] = $value;
                return true;

            case 'integer':
                if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/', $value))) {
                    return $this->fail($field, 'must be a whole number.');
                }
                $this->validated[$field] = (int) $value;
                return true;

            case 'numeric':
                if (!is_numeric($value)) {
                    return $this->fail($field, 'must be a number.');
                }
                $this->validated[$field] = (float) $value;
                return true;

            case 'boolean':
                if (is_bool($value)) {
                    $this->validated[$field] = $value;
                    return true;
                }
                if (in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                    $this->validated[$field] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    return true;
                }
                return $this->fail($field, 'must be true or false.');

            case 'array':
                if (!is_array($value)) {
                    return $this->fail($field, 'must be a list.');
                }
                return true;

            case 'email':
                $normalized = strtolower(trim((string) $value));
                if (!filter_var($normalized, FILTER_VALIDATE_EMAIL) || strlen($normalized) > 190) {
                    return $this->fail($field, 'must be a valid email address.');
                }
                $this->validated[$field] = $normalized;
                return true;

            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL) || strlen((string) $value) > 500) {
                    return $this->fail($field, 'must be a valid URL.');
                }
                return true;

            case 'phone_bd':
                $digits = preg_replace('/(?!^\+)[^0-9]/', '', (string) $value) ?? '';
                if (!preg_match('/^(?:\+?880|0)1[3-9]\d{8}$/', $digits)) {
                    return $this->fail($field, 'must be a valid Bangladeshi number, e.g. 01XXXXXXXXX.');
                }
                $this->validated[$field] = $digits;
                return true;

            case 'min':
                $size = $this->size($value);
                if ($size < (float) $param) {
                    return $this->fail(
                        $field,
                        $this->measuresValue($value)
                            ? sprintf('must be at least %s.', $param)
                            : sprintf('is too short — at least %s required.', $param)
                    );
                }
                return true;

            case 'max':
                $size = $this->size($value);
                if ($size > (float) $param) {
                    return $this->fail(
                        $field,
                        $this->measuresValue($value)
                            ? sprintf('must be at most %s.', $param)
                            : sprintf('is too long — at most %s allowed.', $param)
                    );
                }
                return true;

            case 'between':
                [$low, $high] = array_map('floatval', explode(',', (string) $param));
                $size = $this->size($value);
                if ($size < $low || $size > $high) {
                    return $this->fail($field, sprintf('must be between %s and %s.', $low, $high));
                }
                return true;

            case 'in':
                $allowed = explode(',', (string) $param);
                if (!in_array((string) $value, $allowed, true)) {
                    return $this->fail($field, sprintf('must be one of: %s.', implode(', ', $allowed)));
                }
                return true;

            case 'regex':
                if (!preg_match((string) $param, (string) $value)) {
                    return $this->fail($field, 'has an invalid format.');
                }
                return true;

            default:
                return $this->fail($field, 'has an unknown validation rule (' . $name . ').');
        }
    }

    private function size(mixed $value): float
    {
        if (is_array($value)) {
            return (float) count($value);
        }

        if ($this->measuresValue($value)) {
            return (float) $value;
        }

        return (float) (function_exists('mb_strlen') ? mb_strlen((string) $value) : strlen((string) $value));
    }

    /**
     * "Size" is the field's number only when its own rules say it is a number.
     * A phone number arriving as "01811000000" is eleven characters, not eleven
     * billion, so max:20 has to measure length.
     */
    private function measuresValue(mixed $value): bool
    {
        if (in_array('integer', $this->activeRules, true) || in_array('numeric', $this->activeRules, true)) {
            return is_numeric($value);
        }

        return !is_string($value) && is_numeric($value);
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? str_replace('_', ' ', $field);
    }

    private function fail(string $field, string $suffix): bool
    {
        $this->errors[$field] = ucfirst($this->label($field)) . ' ' . $suffix;

        return false;
    }
}
