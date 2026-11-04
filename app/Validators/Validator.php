<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Config;
use App\Core\Database;
use InvalidArgumentException;

/**
 * Rule-string validator. `field` is used in messages via {field} replacement.
 *
 * Supported rules: required, required_if, nullable, string, text, integer,
 * numeric, boolean, array, email, phone, bd_mobile, url, slug, min, max,
 * between, size, in, not_in, regex, confirmed, same, date, datetime, alpha_dash,
 * alpha_num, digits, digits_between, image, file, mimes, password, unique,
 * exists, json, ipv4, accepted, different.
 */
final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    /** @var array<string, string> */
    private array $customMessages;

    /** @var array<string, string> */
    private array $attributeNames = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @param array<string, string> $customMessages
     */
    public function __construct(
        private array $data,
        private array $rules,
        array $customMessages = [],
        private array $files = []
    ) {
        $this->customMessages = $customMessages;
        $this->run();
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

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $definition) {
            $rules = is_array($definition) ? $definition : explode('|', (string) $definition);
            $value = $this->data[$field] ?? null;

            $ruleNames = array_map(
                static fn ($rule): string => explode(':', (string) $rule, 2)[0],
                $rules
            );

            $isRequired = in_array('required', $ruleNames, true) || in_array('required_if', $ruleNames, true);
            $isNullable = in_array('nullable', $ruleNames, true);
            $isEmpty = $value === null || (is_string($value) && trim($value) === '') || $value === [];

            if ($isEmpty && $isNullable) {
                $this->validated[$field] = null;
                continue;
            }

            if ($isEmpty && !$isRequired) {
                // Nothing to check; an absent optional field is valid.
                continue;
            }

            $failed = false;
            foreach ($rules as $rule) {
                if ($rule === '') {
                    continue;
                }
                [$name, $parameters] = self::parseRule($rule);

                if ($name === 'nullable') {
                    continue;
                }

                if (!$this->applyRule($field, $name, $parameters, $value)) {
                    $failed = true;
                    break;
                }
            }

            if (!$failed) {
                $this->validated[$field] = $value;
            }
        }
    }

    /** @return array{string, list<string>} */
    private static function parseRule(string $rule): array
    {
        $parts = explode(':', $rule, 2);
        $name = $parts[0];
        // PHP 8.4+ deprecates relying on str_getcsv()'s default escape character,
        // and rule parameters never need backslash escaping, so it is set to none.
        $parameters = isset($parts[1]) ? str_getcsv($parts[1], ',', '"', '') : [];
        return [$name, array_map(static fn ($p): string => trim((string) $p), $parameters)];
    }

    private function applyRule(string $field, string $rule, array $parameters, mixed $value): bool
    {
        return match ($rule) {
            'required' => $this->required($field, $value),
            'required_if' => $this->requiredIf($field, $parameters, $value),
            'accepted' => in_array((string) $value, ['1', 'on', 'yes', 'true'], true),
            'string' => $this->checkString($field, $value),
            'text' => is_string($value) && preg_match('/^[\p{L}\p{N}\s.,\'&\/()+-]*$/u', $value) === 1,
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'numeric' => is_numeric($value),
            'boolean' => in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true),
            'array' => is_array($value),
            'json' => is_string($value) && json_decode($value, true) !== null,
            'email' => $this->checkEmail($field, $value),
            'phone' => $this->checkPhone($field, $value),
            'bd_mobile' => $this->checkPhone($field, $value, true),
            'url' => filter_var((string) $value, FILTER_VALIDATE_URL) !== false,
            'ipv4' => filter_var((string) $value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
            'slug' => preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value) === 1,
            'alpha_dash' => preg_match('/^[A-Za-z0-9_-]+$/', (string) $value) === 1,
            'alpha_num' => preg_match('/^[A-Za-z0-9]+$/', (string) $value) === 1,
            'digits' => preg_match('/^\d{' . (int) ($parameters[0] ?? 0) . '}$/', (string) $value) === 1,
            'digits_between' => preg_match(
                '/^\d{' . (int) ($parameters[0] ?? 1) . ',' . (int) ($parameters[1] ?? 20) . '}$/',
                (string) $value
            ) === 1,
            'min' => $this->checkMin($field, $value, $parameters),
            'max' => $this->checkMax($field, $value, $parameters),
            'between' => $this->checkBetween($value, $parameters),
            'size' => $this->checkSize($value, $parameters),
            'in' => in_array((string) $value, $parameters, true),
            'not_in' => !in_array((string) $value, $parameters, true),
            'regex' => $this->checkRegex($value, $parameters),
            'confirmed' => $this->checkConfirmed($field, $value),
            'same' => array_key_exists($parameters[0] ?? '', $this->data)
                && (string) $this->data[$parameters[0]] === (string) $value,
            'different' => (string) ($this->data[$parameters[0] ?? ''] ?? '') !== (string) $value,
            'date' => $this->checkDate((string) $value, 'Y-m-d'),
            'datetime' => $this->checkDate((string) $value, 'Y-m-d H:i:s')
                || $this->checkDate((string) $value, 'Y-m-d H:i'),
            'image' => $this->checkImage($field, $value, $parameters),
            'file' => $this->checkFile($field, $value),
            'mimes' => $this->checkMimes($field, $parameters),
            'password' => $this->checkPassword($field, $value),
            'unique' => $this->checkUnique($field, $value, $parameters),
            'exists' => $this->checkExists($field, $value, $parameters),
            default => throw new InvalidArgumentException('Unknown validation rule: ' . $rule),
        };
    }

    private function required(string $field, mixed $value): bool
    {
        if ($this->files !== [] && isset($this->files[$field])) {
            $file = $this->files[$field];
            if (is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                $this->addError($field, $this->message($field, 'required', 'The {field} field is required.'));
                return false;
            }
            return true;
        }

        $ok = !($value === null || (is_string($value) && trim($value) === '') || $value === []);
        if (!$ok) {
            $this->addError($field, $this->message($field, 'required', 'The {field} field is required.'));
        }
        return $ok;
    }

    private function requiredIf(string $field, array $parameters, mixed $value): bool
    {
        [$other, $expected] = [$parameters[0] ?? '', $parameters[1] ?? ''];
        $triggered = (string) ($this->data[$other] ?? '') === $expected;

        if ($triggered && ($value === null || (is_string($value) && trim($value) === ''))) {
            $this->addError($field, $this->message($field, 'required', 'The {field} field is required.'));
            return false;
        }
        return true;
    }

    private function checkString(string $field, mixed $value): bool
    {
        if (is_string($value)) {
            return true;
        }
        $this->addError($field, $this->message($field, 'string', 'The {field} must be text.'));
        return false;
    }

    private function checkEmail(string $field, mixed $value): bool
    {
        $clean = strtolower(trim((string) $value));
        if (filter_var($clean, FILTER_VALIDATE_EMAIL) !== false && strlen($clean) <= 190) {
            return true;
        }
        $this->addError($field, $this->message($field, 'email', 'Enter a valid email address.'));
        return false;
    }

    private function checkPhone(string $field, mixed $value, bool $strictBangladesh = false): bool
    {
        $digits = preg_replace('/[^0-9+]/', '', (string) $value) ?? '';

        if ($strictBangladesh) {
            $normalized = preg_replace('/^(\+?88)?0?/', '', $digits) ?? $digits;
            if (preg_match('/^1[3-9]\d{8}$/', $normalized) === 1) {
                return true;
            }
            $this->addError(
                $field,
                $this->message($field, 'phone', 'Enter a valid Bangladeshi mobile number (e.g. 01712345678).')
            );
            return false;
        }

        if (preg_match('/^\+?[0-9]{7,15}$/', $digits) === 1) {
            return true;
        }
        $this->addError($field, $this->message($field, 'phone', 'Enter a valid phone number.'));
        return false;
    }

    private function checkMin(string $field, mixed $value, array $parameters): bool
    {
        $limit = (float) ($parameters[0] ?? 0);

        if (is_numeric($value)) {
            if ((float) $value >= $limit) {
                return true;
            }
        } elseif (is_array($value)) {
            if (count($value) >= $limit) {
                return true;
            }
        } elseif (mb_strlen((string) $value) >= $limit) {
            return true;
        }

        $this->addError($field, $this->message(
            $field,
            'min',
            is_numeric($value)
                ? 'The {field} must be at least ' . rtrim(rtrim((string) $parameters[0], '0'), '.') . '.'
                : 'The {field} must be at least {min} characters.'
        ));
        return false;
    }

    private function checkMax(string $field, mixed $value, array $parameters): bool
    {
        $limit = (float) ($parameters[0] ?? 0);

        if (is_numeric($value)) {
            if ((float) $value <= $limit) {
                return true;
            }
        } elseif (is_array($value)) {
            if (count($value) <= $limit) {
                return true;
            }
        } elseif (mb_strlen((string) $value) <= $limit) {
            return true;
        }

        $this->addError($field, $this->message(
            $field,
            'max',
            is_numeric($value)
                ? 'The {field} may not be greater than ' . rtrim(rtrim((string) $parameters[0], '0'), '.') . '.'
                : 'The {field} may not be longer than {max} characters.'
        ));
        return false;
    }

    private function checkBetween(mixed $value, array $parameters): bool
    {
        $low = (float) ($parameters[0] ?? 0);
        $high = (float) ($parameters[1] ?? PHP_FLOAT_MAX);
        return is_numeric($value) ? ((float) $value >= $low && (float) $value <= $high)
            : (mb_strlen((string) $value) >= $low && mb_strlen((string) $value) <= $high);
    }

    private function checkSize(mixed $value, array $parameters): bool
    {
        $size = (float) ($parameters[0] ?? 0);
        return is_numeric($value) ? (float) $value === $size : mb_strlen((string) $value) === (int) $size;
    }

    private function checkRegex(mixed $value, array $parameters): bool
    {
        $pattern = $parameters[0] ?? '';
        if ($pattern === '') {
            return false;
        }
        return preg_match($pattern, (string) $value) === 1;
    }

    private function checkConfirmed(string $field, mixed $value): bool
    {
        $confirmation = (string) ($this->data[$field . '_confirmation'] ?? '');
        if ($confirmation === (string) $value) {
            return true;
        }
        $this->addError($field, $this->message($field, 'confirmed', 'The {field} confirmation does not match.'));
        return false;
    }

    private function checkDate(string $value, string $format): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat($format, $value);
        return $parsed !== false && $parsed->format($format) === $value;
    }

    private function checkImage(string $field, mixed $value, array $parameters): bool
    {
        $file = $this->files[$field] ?? null;
        if (!is_array($file)) {
            return true;
        }

        $allowed = $parameters ?: ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'];
        $mime = self::detectMime($file);

        if ($mime === null) {
            $this->addError($field, 'The uploaded file could not be read.');
            return false;
        }

        $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed, true) || !self::isImageMime($mime)) {
            $this->addError($field, 'Only image files (' . implode(', ', $allowed) . ') are allowed.');
            return false;
        }

        return true;
    }

    private function checkFile(string $field, mixed $value): bool
    {
        $file = $this->files[$field] ?? null;
        if (!is_array($file)) {
            return true;
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->addError($field, 'The file upload failed. Please try again.');
            return false;
        }
        $maximum = (int) Config::get('security.uploads.max_bytes', 5 * 1024 * 1024);
        if ((int) ($file['size'] ?? 0) > $maximum) {
            $this->addError($field, 'The file is larger than ' . round($maximum / 1048576, 1) . ' MB.');
            return false;
        }
        return true;
    }

    private function checkMimes(string $field, array $parameters): bool
    {
        $file = $this->files[$field] ?? null;
        if (!is_array($file)) {
            return true;
        }
        $mime = self::detectMime($file);
        if ($mime === null || $parameters === []) {
            return false;
        }
        $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, $parameters, true)) {
            $this->addError($field, 'That file type is not allowed.');
            return false;
        }
        return true;
    }

    private static function detectMime(array $file): ?string
    {
        $path = (string) ($file['tmp_name'] ?? '');
        if ($path === '' || !is_uploaded_file($path)) {
            // CLI/test context: fall back to a readable temp path only.
            if ($path === '' || !is_file($path)) {
                return null;
            }
        }
        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        return is_string($detected) ? $detected : null;
    }

    private static function isImageMime(string $mime): bool
    {
        return in_array($mime, [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml',
        ], true);
    }

    /**
     * Password policy from config/security.php: length plus a strong/weak class
     * mix. Common-password rejection uses a small embedded denylist.
     */
    private function checkPassword(string $field, mixed $value): bool
    {
        $policy = (array) Config::get('security.password', []);
        $minimum = (int) ($policy['min_length'] ?? 8);
        $text = (string) $value;

        if (mb_strlen($text) < $minimum) {
            $this->addError($field, 'Use at least ' . $minimum . ' characters for your password.');
            return false;
        }

        $classes = 0;
        foreach (['/[a-z]/', '/[A-Z]/', '/\d/', '/[^A-Za-z0-9]/'] as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $classes++;
            }
        }

        if ($classes < (int) ($policy['character_classes'] ?? 2)) {
            $this->addError($field, 'Mix upper case, lower case, numbers or symbols together.');
            return false;
        }

        $blocked = (array) ($policy['deny_list'] ?? []);
        if (in_array(strtolower($text), array_map('strtolower', $blocked), true)) {
            $this->addError($field, 'That password is too common to be accepted.');
            return false;
        }

        return true;
    }

    private function checkUnique(string $field, mixed $value, array $parameters): bool
    {
        [$table, $column, $ignoreId, $ignoreColumn] = [
            $parameters[0] ?? null,
            $parameters[1] ?? $field,
            $parameters[2] ?? null,
            $parameters[3] ?? 'id',
        ];

        if ($table === null) {
            throw new InvalidArgumentException('unique rule needs a table name.');
        }

        $sql = 'SELECT COUNT(*) FROM `' . self::identifier($table) . '`'
            . ' WHERE `' . self::identifier((string) $column) . '` = :value';
        $bindings = ['value' => (string) $value];

        if ($ignoreId !== null && $ignoreId !== '') {
            $sql .= ' AND `' . self::identifier((string) $ignoreColumn) . '` <> :ignore';
            $bindings['ignore'] = is_numeric($ignoreId) ? (int) $ignoreId : $ignoreId;
        }

        if ((int) Database::connection()->scalar($sql, $bindings) > 0) {
            $this->addError($field, 'That ' . self::humanize($field) . ' is already registered.');
            return false;
        }

        return true;
    }

    private function checkExists(string $field, mixed $value, array $parameters): bool
    {
        $table = self::identifier((string) ($parameters[0] ?? $field));
        $column = self::identifier((string) ($parameters[1] ?? 'id'));

        $count = (int) Database::connection()->scalar(
            'SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $column . '` = :value',
            ['value' => (string) $value]
        );

        if ($count === 0) {
            $this->addError($field, 'The selected ' . self::humanize($field) . ' is not valid.');
            return false;
        }

        return true;
    }

    /** Table/column names cannot be parameter-bound: whitelist them instead. */
    private static function identifier(string $name): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?? '';
        if ($clean === '') {
            throw new InvalidArgumentException('Invalid database identifier in validation rule.');
        }
        return $clean;
    }

    private function message(string $field, string $rule, string $default): string
    {
        $template = $this->customMessages[$field . '.' . $rule]
            ?? $this->customMessages[$rule]
            ?? $default;

        return strtr($template, [
            '{field}' => $this->attributeNames[$field] ?? self::humanize($field),
            '{min}' => (string) ($this->parameterFor($field, 'min') ?? ''),
            '{max}' => (string) ($this->parameterFor($field, 'max') ?? ''),
            '{value}' => (string) ($this->data[$field] ?? ''),
        ]);
    }

    private function parameterFor(string $field, string $rule): ?string
    {
        $definition = $this->rules[$field] ?? null;
        if ($definition === null) {
            return null;
        }
        foreach ((is_array($definition) ? $definition : explode('|', (string) $definition)) as $candidate) {
            [$name, $parameters] = self::parseRule((string) $candidate);
            if ($name === $rule) {
                return $parameters[0] ?? null;
            }
        }
        return null;
    }

    public static function humanize(string $field): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $field));
    }

    public function setAttributeName(string $field, string $label): void
    {
        $this->attributeNames[$field] = $label;
    }
}
