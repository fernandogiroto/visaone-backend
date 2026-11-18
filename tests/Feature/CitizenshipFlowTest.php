<?php

namespace Tests\Feature;

use App\Models\CaseAssignment;
use App\Models\CaseStep;
use App\Models\ImmigrationCase;
use App\Models\User;
use Database\Seeders\AgentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CitizenshipFlowTest extends TestCase
{
    use RefreshDatabase;

    private function stepOneData(): array
    {
        return [
            'full_name' => 'Maria da Silva',
            'birth_date' => '1990-05-10',
            'birth_city' => 'Porto Alegre',
            'birth_state' => 'RS',
            'gender' => 'Feminino',
            'marital_status' => 'Solteiro(a)',
            'father_name' => 'José da Silva',
            'mother_name' => 'Ana da Silva',
            'cpf' => '000.000.000-00',
            'passport_number' => 'AB123456',
            'passport_expiry' => '2030-01-01',
            'phone' => '+55 51 99999-0000',
            'address_street' => 'Rua A, 100',
            'address_city' => 'Porto Alegre',
            'address_state' => 'RS',
            'address_zip' => '90000-000',
        ];
    }

    public function test_full_flow_from_intake_to_step_approval(): void
    {
        $this->seed(AgentSeeder::class);
        Storage::fake('local');

        // 1. Triagem pública
        $intake = $this->postJson('/api/intake', [
            'first_name' => 'Maria',
            'last_name' => 'da Silva',
            'visa_type' => 'dupla_cidadania',
            'origin_country' => 'BR',
            'destination_country' => 'PT',
        ])->assertCreated();

        $uuid = $intake->json('uuid');

        // Visto ainda não disponível deve ser recusado
        $this->postJson('/api/intake', [
            'first_name' => 'X', 'last_name' => 'Y', 'visa_type' => 'trabalho',
            'origin_country' => 'BR', 'destination_country' => 'PT',
        ])->assertUnprocessable();

        // 2. Registro com e-mail -> senha temporária gerada
        $this->postJson("/api/intake/{$uuid}/register", ['email' => 'maria@example.com'])
            ->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'role', 'must_change_password', 'case_uuid']])
            ->assertJsonPath('user.must_change_password', false);

        $client = User::where('email', 'maria@example.com')->firstOrFail();
        $this->assertFalse($client->must_change_password);
        $this->assertSame(User::ROLE_CLIENT, $client->role);

        $case = ImmigrationCase::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(ImmigrationCase::STATUS_WAITING_AGENT, $case->status);
        $this->assertSame(6, $case->steps()->count());

        // 3. Oferta enviada a um agente aleatório
        $assignment = $case->assignments()->firstOrFail();
        $this->assertSame(CaseAssignment::OFFERED, $assignment->status);
        $agent = $assignment->agent;

        // 4. Agente recusa -> vai para outro agente
        Sanctum::actingAs($agent);
        $this->postJson("/api/agent/offers/{$assignment->id}/decline")->assertOk();
        $this->assertDatabaseHas('case_assignments', ['id' => $assignment->id, 'status' => CaseAssignment::DECLINED]);

        $second = $case->assignments()->where('status', CaseAssignment::OFFERED)->firstOrFail();
        $this->assertNotSame($agent->id, $second->agent_id);

        // 5. Segundo agente aceita
        Sanctum::actingAs($second->agent);
        $this->postJson("/api/agent/offers/{$second->id}/accept")->assertOk();

        $case->refresh();
        $this->assertSame(ImmigrationCase::STATUS_IN_PROGRESS, $case->status);
        $this->assertSame($second->agent_id, $case->agent_id);

        // Cliente vê o perfil do agente
        Sanctum::actingAs($client);
        $this->getJson('/api/client/case')
            ->assertOk()
            ->assertJsonPath('case.agent.id', $second->agent_id)
            ->assertJsonPath('steps.0.is_current', true)
            ->assertJsonPath('steps.1.is_locked', true);

        // 6. Cliente envia etapa 2 antes da 1 -> bloqueado
        $this->postJson("/api/cases/{$uuid}/steps/2/submit", ['data' => []])->assertUnprocessable();

        // 7. Envia etapa 1 sem documentos obrigatórios -> erro
        $this->postJson("/api/cases/{$uuid}/steps/1/submit", ['data' => $this->stepOneData()])
            ->assertUnprocessable();

        // 8. Envia etapa 1 completa com documentos
        $files = [
            'passport_copy' => UploadedFile::fake()->create('passaporte.pdf', 120, 'application/pdf'),
            'rg_copy' => UploadedFile::fake()->create('rg.jpg', 80, 'image/jpeg'),
            'cpf_copy' => UploadedFile::fake()->create('cpf.png', 80, 'image/png'),
            'proof_address' => UploadedFile::fake()->create('endereco.pdf', 90, 'application/pdf'),
        ];

        $this->post("/api/cases/{$uuid}/steps/1/submit", ['data' => $this->stepOneData(), 'files' => $files])
            ->assertOk()
            ->assertJsonPath('steps.0.status', CaseStep::SUBMITTED);

        $this->assertCount(4, $case->documents()->get());

        // 9. Agente recusa com motivo
        Sanctum::actingAs($second->agent);
        $this->postJson("/api/agent/cases/{$uuid}/steps/1/review", ['decision' => 'reject'])
            ->assertUnprocessable();

        $this->postJson("/api/agent/cases/{$uuid}/steps/1/review", [
            'decision' => 'reject',
            'reason' => 'Comprovante de endereço está ilegível, envie uma foto nítida.',
        ])->assertOk();

        $this->assertDatabaseHas('case_steps', [
            'immigration_case_id' => $case->id,
            'number' => 1,
            'status' => CaseStep::REJECTED,
        ]);

        // 10. Cliente vê o motivo e reenvia somente o documento corrigido
        Sanctum::actingAs($client);
        $this->getJson("/api/cases/{$uuid}")
            ->assertJsonPath('steps.0.rejection_reason', 'Comprovante de endereço está ilegível, envie uma foto nítida.');

        $this->post("/api/cases/{$uuid}/steps/1/submit", [
            'data' => $this->stepOneData(),
            'files' => ['proof_address' => UploadedFile::fake()->create('endereco2.pdf', 90, 'application/pdf')],
        ])->assertOk();

        // 11. Agente aprova -> etapa 2 liberada
        Sanctum::actingAs($second->agent);
        $this->postJson("/api/agent/cases/{$uuid}/steps/1/review", ['decision' => 'approve'])->assertOk();

        $case->refresh();
        $this->assertSame(2, $case->current_step);

        // Mensagem do painel
        Sanctum::actingAs($client);
        $this->postJson("/api/cases/{$uuid}/messages", ['body' => 'Olá, enviei o comprovante corrigido.'])
            ->assertCreated();

        // Outro cliente/agente não acessa o caso
        Sanctum::actingAs($agent);
        $this->getJson("/api/cases/{$uuid}")->assertForbidden();
    }

    public function test_agent_profile_is_seeded_with_ten_agents(): void
    {
        $this->seed(AgentSeeder::class);

        $this->assertSame(11, User::where('role', User::ROLE_AGENT)->count());
        $this->assertSame(11, \App\Models\AgentProfile::count());
    }
}
