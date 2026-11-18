<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Lê config/processes.php e expõe as etapas, campos e regras de validação.
 */
class ProcessCatalog
{
    public function visaTypes(): array
    {
        return collect(config('processes.visa_types'))
            ->map(fn (array $v, string $key) => ['key' => $key, 'label' => $v['label'], 'enabled' => $v['enabled']])
            ->values()
            ->all();
    }

    public function countries(): array
    {
        return collect(config('processes.countries'))
            ->map(fn (array $c) => ['code' => $c['code'], 'label' => $c['label'], 'enabled' => $c['enabled']])
            ->values()
            ->all();
    }

    public function isVisaTypeEnabled(string $visaType): bool
    {
        return (bool) config("processes.visa_types.{$visaType}.enabled");
    }

    /** Etapas de um processo (visa_type). */
    public function steps(string $visaType): array
    {
        return config("processes.{$visaType}.steps", []);
    }

    public function stepCount(string $visaType): int
    {
        return count($this->steps($visaType));
    }

    public function step(string $visaType, int $number): ?array
    {
        $step = Arr::get($this->steps($visaType), $number - 1);

        return $step ? $step + ['number' => $number] : null;
    }

    /** Regras de validação para os campos (data) de uma etapa. */
    public function fieldRules(array $step): array
    {
        $rules = [];

        foreach ($step['fields'] ?? [] as $field) {
            $base = $field['required'] ? ['required'] : ['nullable'];

            $rules[$field['key']] = match ($field['type']) {
                'date' => [...$base, 'date'],
                'number' => [...$base, 'numeric', 'min:0'],
                'boolean' => [...$base, 'boolean'],
                'email' => [...$base, 'email', 'max:255'],
                'textarea' => [...$base, 'string', 'max:5000'],
                'select' => [...$base, 'string', Rule::in($field['options'] ?? [])],
                default => [...$base, 'string', 'max:255'],
            };
        }

        return $rules;
    }

    /** Regras para os arquivos enviados em uma etapa (por chave do documento). */
    public function documentRules(array $step): array
    {
        $rules = [];

        foreach ($step['documents'] ?? [] as $doc) {
            $rules["files.{$doc['key']}"] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];
        }

        return $rules;
    }

    public function documentDefinition(array $step, string $key): ?array
    {
        return collect($step['documents'] ?? [])->firstWhere('key', $key);
    }
}
