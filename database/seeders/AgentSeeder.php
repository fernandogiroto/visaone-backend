<?php

namespace Database\Seeders;

use App\Models\AgentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Cria 10 agentes de exemplo. Senhas são apenas para desenvolvimento:
 * troque antes de qualquer ambiente real.
 */
class AgentSeeder extends Seeder
{
    /** Caminhos dos retratos de placeholder, na ordem dos agentes. */
    private const PORTRAITS = [
        'women/11', 'men/21', 'women/12', 'men/22', 'women/16',
        'men/23', 'women/14', 'men/24', 'women/15', 'men/25',
    ];

    public function run(): void
    {
        $agents = [
            ['name' => 'Mariana Costa', 'email' => 'mariana.costa@visaone.test', 'birth' => '1988-03-14', 'years' => 9, 'specialty' => 'Dupla cidadania (descendência)', 'bio' => 'Especialista em processos de descendência e registos em Conservatórias portuguesas.'],
            ['name' => 'Rafael Oliveira', 'email' => 'rafael.oliveira@visaone.test', 'birth' => '1984-07-02', 'years' => 12, 'specialty' => 'Naturalização por residência', 'bio' => 'Acompanha processos de naturalização com foco em documentação e prazos.'],
            ['name' => 'Juliana Ferreira', 'email' => 'juliana.ferreira@visaone.test', 'birth' => '1990-11-21', 'years' => 6, 'specialty' => 'Certidões e apostilas', 'bio' => 'Revisão de certidões brasileiras, apostilas e traduções.'],
            ['name' => 'Bruno Almeida', 'email' => 'bruno.almeida@visaone.test', 'birth' => '1986-01-09', 'years' => 10, 'specialty' => 'Dupla cidadania', 'bio' => 'Atendimento completo, da triagem à submissão final.'],
            ['name' => 'Camila Rodrigues', 'email' => 'camila.rodrigues@visaone.test', 'birth' => '1992-05-30', 'years' => 5, 'specialty' => 'Teste de cultura e língua', 'bio' => 'Orientação para CIPLE/DEPLE e preparação para o teste de cultura.'],
            ['name' => 'Thiago Santos', 'email' => 'thiago.santos@visaone.test', 'birth' => '1982-09-17', 'years' => 14, 'specialty' => 'Casos complexos', 'bio' => 'Casos com múltiplas vias e histórico de recusas anteriores.'],
            ['name' => 'Fernanda Lima', 'email' => 'fernanda.lima@visaone.test', 'birth' => '1989-12-05', 'years' => 8, 'specialty' => 'Subsistência e NIF', 'bio' => 'Análise de rendimentos, extratos bancários e NIF português.'],
            ['name' => 'Lucas Pereira', 'email' => 'lucas.pereira@visaone.test', 'birth' => '1991-04-25', 'years' => 4, 'specialty' => 'Dupla cidadania', 'bio' => 'Foco em clareza do cliente e comunicação objetiva em cada etapa.'],
            ['name' => 'Patrícia Gomes', 'email' => 'patricia.gomes@visaone.test', 'birth' => '1985-08-11', 'years' => 11, 'specialty' => 'Descendência e registos', 'bio' => 'Registos de nascimento e transcrições em Portugal.'],
            ['name' => 'Gustavo Martins', 'email' => 'gustavo.martins@visaone.test', 'birth' => '1987-02-28', 'years' => 7, 'specialty' => 'Revisão final', 'bio' => 'Conferência final de documentos antes da submissão.'],
            ['name' => 'Ana Tavares', 'email' => 'ana.tavares@visaone.test', 'birth' => '1990-01-01', 'years' => 5, 'specialty' => 'Atendimento da equipe', 'bio' => 'Integrante da equipe VisaOne.', 'photo' => 'avatars/ana-tavares.jpg'],
        ];

        foreach ($agents as $index => $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('Agente@2026'),
                    'role' => User::ROLE_AGENT,
                    'must_change_password' => false,
                ],
            );

            AgentProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    // Foto de PLACEHOLDER de desenvolvimento (retrato aleatório de randomuser.me).
                    // Os agentes são fictícios: troque pela foto real de cada agente antes de produção.
                    'photo_path' => $data['photo'] ?? 'https://randomuser.me/api/portraits/'.self::PORTRAITS[$index].'.jpg',
                    'birth_date' => $data['birth'],
                    'job_title' => 'Agente de imigração',
                    'specialty' => $data['specialty'],
                    'years_experience' => $data['years'],
                    'languages' => 'Português, Inglês',
                    'bio' => $data['bio'],
                ],
            );
        }
    }
}
