<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationCase;
use App\Models\User;
use App\Notifications\TemporaryPasswordCreated;
use App\Services\CaseWorkflow;
use App\Services\ProcessCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Triagem pública (antes de criar conta):
 *  1. nome, sobrenome, tipo de visto, país de origem e de destino  -> store()
 *  2. e-mail de registro -> cria usuário com senha temporária      -> register()
 */
class IntakeController extends Controller
{
    public function store(Request $request, ProcessCatalog $catalog): JsonResponse
    {
        $visaKeys = array_keys(config('processes.visa_types'));
        $countryCodes = collect(config('processes.countries'))->pluck('code')->all();

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:150'],
            'visa_type' => ['required', Rule::in($visaKeys)],
            'origin_country' => ['required', Rule::in($countryCodes)],
            'destination_country' => ['required', Rule::in($countryCodes)],
        ]);

        if (! $catalog->isVisaTypeEnabled($data['visa_type'])) {
            throw ValidationException::withMessages([
                'visa_type' => ['Este tipo de visto ainda não está disponível.'],
            ]);
        }

        $origins = config("processes.{$data['visa_type']}.origin_countries", []);
        $destinations = config("processes.{$data['visa_type']}.destination_countries", []);

        if (! in_array($data['origin_country'], $origins, true) || ! in_array($data['destination_country'], $destinations, true)) {
            throw ValidationException::withMessages([
                'destination_country' => ['Este processo ainda não está disponível para a combinação de países escolhida.'],
            ]);
        }

        $case = ImmigrationCase::create($data + ['status' => ImmigrationCase::STATUS_DRAFT]);

        return response()->json([
            'uuid' => $case->uuid,
            'message' => 'Dados iniciais salvos. Agora informe seu e-mail para criar sua conta.',
        ], 201);
    }

    public function register(Request $request, ImmigrationCase $case, CaseWorkflow $workflow, ProcessCatalog $catalog): JsonResponse
    {
        if ($case->user_id !== null || $case->status !== ImmigrationCase::STATUS_DRAFT) {
            return response()->json(['message' => 'Este processo já possui cadastro. Faça login para continuar.'], 422);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $temporaryPassword = Str::password(12);

        $user = DB::transaction(function () use ($case, $data, $temporaryPassword, $workflow, $catalog) {
            $user = User::create([
                'name' => trim("{$case->first_name} {$case->last_name}"),
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $temporaryPassword,
                'role' => User::ROLE_CLIENT,
                // Entra direto com a senha temporária gerada; ela também vai por e-mail
                // para acessos futuros pela tela de login.
                'must_change_password' => false,
            ]);

            $case->update([
                'user_id' => $user->id,
                'status' => ImmigrationCase::STATUS_WAITING_AGENT,
            ]);

            $workflow->ensureSteps($case->refresh());
            $workflow->offerToRandomAgent($case->refresh());

            return $user;
        });

        $user->notify(new TemporaryPasswordCreated($temporaryPassword));

        // Já entra direto no painel. A senha temporária também vai por e-mail,
        // e o sistema pede a troca dela no primeiro acesso.
        $token = $user->createToken('painel')->plainTextToken;

        return response()->json([
            'message' => 'Conta criada. Enviamos uma senha temporária para o seu e-mail.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'must_change_password' => false,
                'case_uuid' => $case->uuid,
            ],
        ], 201);
    }
}
