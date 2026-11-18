<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Dados iniciais: 10 agentes de exemplo.
     * Clientes são criados pelo próprio fluxo de cadastro (triagem -> e-mail).
     */
    public function run(): void
    {
        $this->call(AgentSeeder::class);
    }
}
