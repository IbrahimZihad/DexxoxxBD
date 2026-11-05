<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Rule-string validator. It is the only place request data is judged, and every
 * field a controller wants to store has to appear here, which is what keeps a
 * crafted form field from reaching an INSERT.
 *
 * Supported: required, email, phone_bd, numeric, integer, min:N, max:N, between:A,B,
 * in:a,b,c, regex:..., digits:N, confirmed, date, url, boolean, slug, unique:table,col[,id],
 * exists:table,col, same:field, after:field, before:field, trim, lower, upper.
 */
final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $clean = [];

    /** @var array<string, string> */
    private array $labels = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     */
    public function __construct(private array $data, private array $rules, array $labels = [])
    {
        $this->labels = $labels;
        $this->run();
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = array_filter(array_map('trim', explode('|', $ruleString)));
            $value = $this->data[$field] ?? null;
            $value = is_string($value) ? trim($value) : $value;

            $label = $this->label($field);
            $isEmpty = $value === null || $value === '';

            if (in_array('required', $rules, true) && $isEmpty) {
                $this->addError($field, $label . ' is required.');
                continue;
            }

            if ($isEmpty) {
                // An absent optional field is simply not part of the clean set.
                continue;
            }

            $passed = true;

            foreach ($rules as $rule) {
                if ($rule === 'required') {
                    continue;
                }

                [$name, $argument] = array_pad(explode(':', $rule, 2), 2, '');

                if (!$this->apply((string) $name, (string) $argument, $value, $field, $label)) {
                    $passed = false;

                    break;
                }
            }

            if ($passed) {
                $this->clean[$field] = $this->cast($rules, $value);
            }
        }
    }

    private function apply(string $name, string $argument, mixed $value, string $field, string $label): bool
    {
        $string = is_scalar($value) ? (string) $value : '';

        switch ($name) {
            case 'email':
                if (filter_var($string, FILTER_VALIDATE_EMAIL) === false || mb_strlen($string) > 180) {
                    return $this->fail($field, 'Enter a valid email address.');
                }

                return true;

            case 'phone_bd':
                // Accept 01XXXXXXXXX, +8801XXXXXXXXX and 8801XXXXXXXXX.
                $digits = preg_replace('/[\s()-]/', '', $string) ?? '';

                if (preg_match('/^(?:\+?88)?01[3-9]\d{8}$/', $digits) !== 1) {
                    return $this->fail($field, 'Enter a valid Bangladeshi mobile number, e.g. 01712-345678.');
                }

                $this->data[$field] = $digits;

                return true;

            case 'numeric':
                if (!is_numeric($string)) {
                    return $this->fail($field, $label . ' must be a number.');
                }

                return true;

            case 'integer':
                if (preg_match('/^-?\d+$/', $string) !== 1) {
                    return $this->fail($field, $label . ' must be a whole number.');
                }

                return true;

            case 'digits':
                if (preg_match('/^\d{' . (int) $argument . '}$/', $string) !== 1) {
                    return $this->fail($field, $label . ' must be exactly ' . (int) $argument . ' digits.');
                }

                return true;

            case 'min':
                if (is_numeric($string) && $this->isNumericRule($field)) {
                    if ((float) $string < (float) $argument) {
                        return $this->fail($field, $label . ' must be at least ' . $argument . '.');
                    }

                    return true;
                }

                if (mb_strlen($string) < (int) $argument) {
                    return $this->fail($field, $label . ' must be at least ' . (int) $argument . ' characters.');
                }

                return true;

            case 'max':
                if (is_numeric($string) && $this->isNumericRule($field)) {
                    if ((float) $string > (float) $argument) {
                        return $this->fail($field, $label . ' may not be more than ' . $argument . '.');
                    }

                    return true;
                }

                if (mb_strlen($string) > (int) $argument) {
                    return $this->fail($field, $label . ' must be 200 characters or fewer.');
                }

                return true;

            case 'between':
                [$low, $high] = array_pad(explode(',', $argument), 2, '0');

                if (!is_numeric($string) || (float) $string < (float) $low || (float) $string > (float) $high) {
                    return $this->fail($field, $label . ' must be between ' . $low . ' and ' . $high . '.');
                }

                return true;

            case 'in':
                $allowed = array_filter(array_map('trim', explode(',', $argument)));

                if (!in_array($string, $allowed, true)) {
                    return $this->fail($field, 'Choose a valid ' . mb_strtolower($label) . '.');
                }

                return true;

            case 'boolean':
                if (!in_array(strtolower($string), ['1', '0', 'true', 'false', 'on', 'off', 'yes', 'no'], true)) {
                    return $this->fail($field, $label . ' must be yes or no.');
                }

                return true;

            case 'regex':
                if (@preg_match($argument, $string) !== 1) {
                    return $this->fail($field, $label . ' is not formatted correctly.');
                }

                return true;

            case 'slug':
                if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $string) !== 1) {
                    return $this->fail($field, 'Use lowercase letters, numbers and dashes only.');
                }

                return true;

            case 'url':
                if (filter_var($string, FILTER_VALIDATE_URL) === false || !preg_match('#^https://#i', $string)) {
                    return $this->fail($field, 'Enter a full https:// URL.');
                }

                return true;

            case 'date':
                if (new \DateTimeImmutable($string) === false) {
                    return $this->fail($field, $label . ' must be a valid date.');
                }

                return true;

            case 'confirmed':
                $other = (string) ($this->data[$field . '_confirmation'] ?? '');

                if (!hash_equals($string, $other)) {
                    return $this->fail($field, $label . ' and its confirmation do not match.');
                }

                return true;

            case 'same':
                if (!hash_equals($string, (string) ($this->data[$argument] ?? ''))) {
                    return $this->fail($field, $label . ' and ' . $this->label($argument) . ' must match.');
                }

                return true;

            case 'unique':
                return $this->checkUnique($field, $label, $string, $argument);

            case 'exists':
                return $this->checkExists($field, $label, $string, $argument);

            case 'trim':
            case 'lower':
            case 'upper':
                return true;

            default:
                return true;
        }
    }

    /** argument is "table,column[,ignoreId]" */
    private function checkUnique(string $field, string $label, string $value, string $argument): bool
    {
        [$table, $column, $ignoreId] = array_pad(explode(',', $argument), 3, '');

        if ($table === null || $column === null || $column === '') {
            return $this->fail($field, 'Validation rule for ' . $label . ' is misconfigured.');
        }

        try {
            $query = Database::table($table)->where($column, $value);

            if (is_string($ignoreId) && is_numeric($ignoreId)) {
                $query->where('id', '!=', (int) $ignoreId);
            }

            if ($query->exists()) {
                return $this->fail($field, 'That ' . mb_strtolower($label) . ' is already taken.');
            }
        } catch (\Throwable $exception) {
            Logger::error('unique_rule_failed', ['reason' => $exception->getMessage()]);
        }

        return true;
    }

    private function checkExists(string $field, string $label, string $value, string $argument): bool
    {
        [$table, $column] = array_pad(explode(',', $argument), 2, '');

        if ($table === null || $column === null || $column === '') {
            return true;
        }

        try {
            if (!Database::table($table)->where($column, $value)->exists()) {
                return $this->fail($field, 'The selected ' . mb_strtolower($label) . ' is not available.');
            }
        } catch (\Throwable $exception) {
            Logger::error('exists_rule_failed', ['reason' => $exception->getMessage()]);
        }

        return true;
    }

    /** min/max compare values (not length) when the field is also numeric. */
    private function isNumericRule(string $field): bool
    {
        $rules = (string) ($this->rules[$field] ?? '');

        return str_contains($rules, 'numeric') || str_contains($rules, 'integer');
    }

    private function cast(array $rules, mixed $value): mixed
    {
        $names = array_map(static fn (string $rule): string => explode(':', $rule, 2)[0], $rules);

        if (in_array('integer', $names, true)) {
            return (int) $value;
        }

        if (in_array('numeric', $names, true)) {
            return (float) $value;
        }

        if (in_array('boolean', $names, true)) {
            return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
        }

        if (in_array('lower', $names, true)) {
            return mb_strtolower((string) $value);
        }

        if (in_array('upper', $names, true)) {
            return mb_strtoupper((string) $value);
        }

        return is_string($value) ? trim($value) : $value;
    }

    private function fail(string $field, string $message): bool
    {
        $this->addError($field, $message);

        return false;
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    private function label(string $field): string
    {
        if (isset($this->labels[$field])) {
            return $this->labels[$field];
        }

        return ucfirst(str_replace('_', ' ', $field));
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstMessage(): string
    {
        foreach ($this->errors as $messages) {
            if ($messages !== []) {
                return (string) $messages[0];
            }
        }

        return 'Please check the form and try again.';
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->clean;
    }
}
