<?php

namespace App\Core;

/**
 * Validateur de données à base de règles chaînées "champ" => "rule1|rule2:param".
 *
 * Règles supportées :
 *   required, nullable, string, integer, numeric, boolean, email, url,
 *   min:n, max:n, between:a,b, in:a,b,c, array, date, regex:/.../,
 *   confirmed, latitude, longitude
 */
final class Validator
{
    private array $errors = [];
    private array $validated = [];
    /** Le champ courant doit-il comparer min/max comme un nombre (et non une longueur) ? */
    private bool $numericContext = false;

    public function __construct(
        private array $data,
        private array $rules,
        private array $messages = []
    ) {}

    public static function make(array $data, array $rules, array $messages = []): self
    {
        return new self($data, $rules, $messages);
    }

    /** Valide et retourne les données propres, ou lève une HttpException 422. */
    public function validate(): array
    {
        if ($this->fails()) {
            throw HttpException::validation($this->errors);
        }
        return $this->validated;
    }

    public function fails(): bool
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($this->rules as $field => $ruleset) {
            $rules = is_array($ruleset) ? $ruleset : explode('|', $ruleset);
            $value = $this->data[$field] ?? null;
            $isNullable = in_array('nullable', $rules, true);
            $isRequired = in_array('required', $rules, true);
            // min/max : comparaison numérique seulement si le champ est déclaré integer/numeric
            $this->numericContext = in_array('integer', $rules, true) || in_array('numeric', $rules, true);

            if (($value === null || $value === '') && !$isRequired) {
                if (array_key_exists($field, $this->data)) {
                    $this->validated[$field] = $value;
                }
                continue;
            }

            foreach ($rules as $rule) {
                if (in_array($rule, ['nullable'], true)) {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $ok = $this->applyRule($name, $value, $param, $field);
                if (!$ok) {
                    break; // une erreur par champ suffit
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }

        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function applyRule(string $name, mixed $value, ?string $param, string $field): bool
    {
        $pass = match ($name) {
            'required'  => $value !== null && $value !== '' && $value !== [],
            'string'    => is_string($value),
            'integer'   => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'numeric'   => is_numeric($value),
            'boolean'   => is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true),
            'email'     => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url'       => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'array'     => is_array($value),
            'date'      => strtotime((string) $value) !== false,
            'min'       => $this->compareSize($value, (float) $param, '>='),
            'max'       => $this->compareSize($value, (float) $param, '<='),
            'between'   => $this->between($value, $param),
            'in'        => in_array((string) $value, explode(',', (string) $param), true),
            'regex'     => (bool) preg_match($param, (string) $value),
            'latitude'  => is_numeric($value) && $value >= -90 && $value <= 90,
            'longitude' => is_numeric($value) && $value >= -180 && $value <= 180,
            'confirmed' => ($value === ($this->data[$field . '_confirmation'] ?? null)),
            default     => true,
        };

        if (!$pass) {
            $this->addError($field, $name, $param);
        }
        return $pass;
    }

    private function compareSize(mixed $value, float $limit, string $op): bool
    {
        $size = ($this->numericContext && is_numeric($value))
            ? (float) $value
            : (is_array($value) ? count($value) : mb_strlen((string) $value));
        return $op === '>=' ? $size >= $limit : $size <= $limit;
    }

    private function between(mixed $value, ?string $param): bool
    {
        [$a, $b] = array_pad(explode(',', (string) $param), 2, 0);
        return $this->compareSize($value, (float) $a, '>=') && $this->compareSize($value, (float) $b, '<=');
    }

    private function addError(string $field, string $rule, ?string $param): void
    {
        $key = "$field.$rule";
        if (isset($this->messages[$key])) {
            $this->errors[$field][] = $this->messages[$key];
            return;
        }
        $this->errors[$field][] = match ($rule) {
            'required'  => "Le champ $field est obligatoire.",
            'email'     => "Le champ $field doit être une adresse email valide.",
            'min'       => "Le champ $field est trop court (minimum $param).",
            'max'       => "Le champ $field est trop long (maximum $param).",
            'in'        => "La valeur de $field n'est pas autorisée.",
            'integer', 'numeric' => "Le champ $field doit être un nombre.",
            'latitude', 'longitude' => "Le champ $field n'est pas une coordonnée valide.",
            default     => "Le champ $field est invalide.",
        };
    }
}
