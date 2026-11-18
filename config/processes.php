<?php

/*
|--------------------------------------------------------------------------
| Catálogo de processos de imigração
|--------------------------------------------------------------------------
|
| Cada processo (visa_type) é composto por etapas em ordem. Cada etapa tem
| campos (inputs) que o cliente preenche e documentos que ele envia.
|
| Tipos de campo suportados (frontend renderiza automaticamente):
|   text | email | phone | date | number | textarea | select | boolean
|
| Tipos de documento: sempre enviados como arquivo (pdf, jpg, png).
|
| ATENÇÃO: requisitos legais mudam. Antes de produção, validar com o
| IRN (irn.justica.gov.pt) ou com um advogado/consultor registado.
|
*/

return [

    'visa_types' => [
        'trabalho' => ['label' => 'Visto de Trabalho', 'enabled' => false],
        'residencia' => ['label' => 'Visto de Residência', 'enabled' => false],
        'dupla_cidadania' => ['label' => 'Dupla Cidadania Portuguesa', 'enabled' => true],
    ],

    'countries' => [
        ['code' => 'BR', 'label' => 'Brasil', 'enabled' => true],
        ['code' => 'PT', 'label' => 'Portugal', 'enabled' => true],
        ['code' => 'ES', 'label' => 'Espanha', 'enabled' => false],
        ['code' => 'IT', 'label' => 'Itália', 'enabled' => false],
        ['code' => 'OTHER', 'label' => 'Outro', 'enabled' => false],
    ],

    /*
    |--------------------------------------------------------------------------
    | Processo: Dupla cidadania portuguesa (cliente brasileiro)
    |--------------------------------------------------------------------------
    */
    'dupla_cidadania' => [

        'origin_countries' => ['BR'],
        'destination_countries' => ['PT'],

        'steps' => [

            [
                'key' => 'dados_pessoais',
                'document_groups' => [
                    ['label' => 'Identidade', 'documents' => ['passport_copy', 'rg_copy', 'cpf_copy']],
                    ['label' => 'Comprovante de endereço', 'documents' => ['proof_address']],
                ],
                'groups' => [
                    ['label' => 'Dados pessoais', 'fields' => ['full_name', 'birth_date', 'gender', 'marital_status', 'birth_city', 'birth_state', 'father_name', 'mother_name', 'cpf', 'rg_number', 'passport_number', 'passport_expiry']],
                    ['label' => 'Contato e endereço', 'fields' => ['phone', 'address_city', 'address_street', 'address_state', 'address_zip']],
                ],
                'title' => 'Dados pessoais',
                'description' => 'Informe seus dados como constam nos documentos oficiais (passaporte e certidão de nascimento).',
                'fields' => [
                    ['key' => 'full_name', 'label' => 'Nome completo', 'type' => 'text', 'required' => true],
                    ['key' => 'birth_date', 'label' => 'Data de nascimento', 'type' => 'date', 'required' => true],
                    ['key' => 'birth_city', 'label' => 'Cidade de nascimento', 'type' => 'text', 'required' => true],
                    ['key' => 'birth_state', 'label' => 'Estado de nascimento (UF)', 'type' => 'text', 'required' => true],
                    ['key' => 'gender', 'label' => 'Sexo', 'type' => 'select', 'required' => true, 'options' => ['Masculino', 'Feminino', 'Outro']],
                    ['key' => 'marital_status', 'label' => 'Estado civil', 'type' => 'select', 'required' => true, 'options' => ['Solteiro(a)', 'Casado(a)', 'União estável', 'Divorciado(a)', 'Viúvo(a)']],
                    ['key' => 'father_name', 'label' => 'Nome completo do pai', 'type' => 'text', 'required' => true],
                    ['key' => 'mother_name', 'label' => 'Nome completo da mãe', 'type' => 'text', 'required' => true],
                    ['key' => 'cpf', 'label' => 'CPF', 'type' => 'text', 'required' => true],
                    ['key' => 'rg_number', 'label' => 'RG (número)', 'type' => 'text', 'required' => false],
                    ['key' => 'passport_number', 'label' => 'Número do passaporte brasileiro', 'type' => 'text', 'required' => true],
                    ['key' => 'passport_expiry', 'label' => 'Validade do passaporte', 'type' => 'date', 'required' => true],
                    ['key' => 'phone', 'label' => 'Telefone / WhatsApp', 'type' => 'phone', 'required' => true],
                    ['key' => 'address_street', 'label' => 'Endereço (rua, número, complemento)', 'type' => 'text', 'required' => true],
                    ['key' => 'address_city', 'label' => 'Cidade de residência', 'type' => 'text', 'required' => true],
                    ['key' => 'address_state', 'label' => 'Estado (UF)', 'type' => 'text', 'required' => true],
                    ['key' => 'address_zip', 'label' => 'CEP', 'type' => 'text', 'required' => true],
                ],
                'documents' => [
                    ['key' => 'passport_copy', 'label' => 'Cópia do passaporte (todas as páginas com dados)', 'required' => true],
                    ['key' => 'rg_copy', 'label' => 'Cópia do RG ou CNH', 'required' => true],
                    ['key' => 'cpf_copy', 'label' => 'Cópia do CPF', 'required' => true],
                    ['key' => 'proof_address', 'label' => 'Comprovante de endereço no Brasil', 'required' => true],
                ],
            ],

            [
                'key' => 'vinculo_portugal',
                'document_groups' => [
                    ['label' => 'Ascendente português', 'documents' => ['ancestor_birth_certificate', 'ancestor_nationality_proof']],
                    ['label' => 'Residência e casamento', 'documents' => ['residence_permit_copy', 'marriage_certificate_pt']],
                ],
                'groups' => [
                    ['label' => 'Via de acesso', 'fields' => ['route', 'has_portuguese_ancestor', 'ancestor_relation', 'ancestor_full_name', 'ancestor_birth_date', 'ancestor_birth_place', 'ancestor_never_renounced']],
                    ['label' => 'Residência e vínculo', 'fields' => ['legal_residence_years', 'residence_permit_number', 'spouse_name']],
                ],
                'title' => 'Vínculo com Portugal',
                'description' => 'Informe por qual via você pretende obter a nacionalidade. Se tiver ascendente português, a via de descendência costuma ser a mais rápida; caso contrário, a via é a naturalização por residência legal.',
                'fields' => [
                    ['key' => 'route', 'label' => 'Via de acesso', 'type' => 'select', 'required' => true, 'options' => ['Descendência (pai, mãe ou avós portugueses)', 'Naturalização por residência legal', 'Casamento ou união de facto com português']],
                    ['key' => 'has_portuguese_ancestor', 'label' => 'Tem pai, mãe, avô ou avó nascido(a) em Portugal?', 'type' => 'boolean', 'required' => true],
                    ['key' => 'ancestor_relation', 'label' => 'Parentesco do ascendente português', 'type' => 'select', 'required' => false, 'options' => ['Pai', 'Mãe', 'Avô paterno', 'Avó paterna', 'Avô materno', 'Avó materna', 'Bisavô / bisavó']],
                    ['key' => 'ancestor_full_name', 'label' => 'Nome completo do ascendente', 'type' => 'text', 'required' => false],
                    ['key' => 'ancestor_birth_date', 'label' => 'Data de nascimento do ascendente', 'type' => 'date', 'required' => false],
                    ['key' => 'ancestor_birth_place', 'label' => 'Local de nascimento do ascendente (concelho/freguesia)', 'type' => 'text', 'required' => false],
                    ['key' => 'ancestor_never_renounced', 'label' => 'O ascendente nunca renunciou à nacionalidade portuguesa?', 'type' => 'boolean', 'required' => false],
                    ['key' => 'legal_residence_years', 'label' => 'Anos de residência legal em Portugal (somados)', 'type' => 'number', 'required' => false],
                    ['key' => 'residence_permit_number', 'label' => 'Número do título de residência (se houver)', 'type' => 'text', 'required' => false],
                    ['key' => 'spouse_name', 'label' => 'Nome do cônjuge/companheiro(a) português(a)', 'type' => 'text', 'required' => false],
                ],
                'documents' => [
                    ['key' => 'ancestor_birth_certificate', 'label' => 'Certidão de nascimento do ascendente português', 'required' => false],
                    ['key' => 'ancestor_nationality_proof', 'label' => 'Certidão de nacionalidade / assento de nascimento do ascendente', 'required' => false],
                    ['key' => 'residence_permit_copy', 'label' => 'Cópia do título de residência em Portugal', 'required' => false],
                    ['key' => 'marriage_certificate_pt', 'label' => 'Certidão de casamento (se aplicável)', 'required' => false],
                ],
            ],

            [
                'key' => 'documentos_civis',
                'document_groups' => [
                    ['label' => 'Certidões de nascimento e casamento', 'documents' => ['birth_certificate', 'marriage_certificate', 'name_change_proof']],
                    ['label' => 'Antecedentes criminais', 'documents' => ['criminal_record']],
                ],
                'groups' => [
                    ['label' => 'Certidões e antecedentes', 'fields' => ['birth_certificate_date', 'birth_certificate_apostilled', 'criminal_record_date', 'criminal_record_apostilled', 'has_criminal_record']],
                    ['label' => 'Observações', 'fields' => ['civil_notes']],
                ],
                'title' => 'Certidões e antecedentes criminais',
                'description' => 'Certidões brasileiras devem ser emitidas recentemente e, normalmente, apostiladas (Haia). Confirme com o agente o prazo de validade vigente.',
                'fields' => [
                    ['key' => 'birth_certificate_apostilled', 'label' => 'A certidão de nascimento é atual e está apostilada?', 'type' => 'boolean', 'required' => true],
                    ['key' => 'birth_certificate_date', 'label' => 'Data de emissão da certidão de nascimento', 'type' => 'date', 'required' => true],
                    ['key' => 'criminal_record_date', 'label' => 'Data de emissão do antecedente criminal brasileiro', 'type' => 'date', 'required' => true],
                    ['key' => 'criminal_record_apostilled', 'label' => 'O antecedente criminal brasileiro está apostilado?', 'type' => 'boolean', 'required' => true],
                    ['key' => 'has_criminal_record', 'label' => 'Você possui algum processo criminal ativo ou condenação?', 'type' => 'boolean', 'required' => true],
                    ['key' => 'civil_notes', 'label' => 'Observações sobre certidões (ex.: nome alterado, divórcio)', 'type' => 'textarea', 'required' => false],
                ],
                'documents' => [
                    ['key' => 'birth_certificate', 'label' => 'Certidão de nascimento (apostilada)', 'required' => true],
                    ['key' => 'marriage_certificate', 'label' => 'Certidão de casamento ou de divórcio (se aplicável)', 'required' => false],
                    ['key' => 'criminal_record', 'label' => 'Certidão de antecedentes criminais brasileira (apostilada)', 'required' => true],
                    ['key' => 'name_change_proof', 'label' => 'Documento de alteração de nome (se aplicável)', 'required' => false],
                ],
            ],

            [
                'key' => 'lingua_cultura',
                'document_groups' => [
                    ['label' => 'Língua portuguesa', 'documents' => ['language_certificate']],
                    ['label' => 'Teste de cultura', 'documents' => ['culture_test_proof']],
                ],
                'groups' => [
                    ['label' => 'Língua portuguesa', 'fields' => ['language_proof', 'language_certificate_number', 'language_certificate_date']],
                    ['label' => 'Teste de cultura', 'fields' => ['culture_test_status', 'culture_test_date']],
                ],
                'title' => 'Língua e teste de cultura',
                'description' => 'Informe se possui comprovação de língua portuguesa ou dispensa, e o estado do teste de cultura e valores democráticos exigido pela lei nova.',
                'fields' => [
                    ['key' => 'language_proof', 'label' => 'Comprovação de língua portuguesa', 'type' => 'select', 'required' => true, 'options' => ['Dispensa (nacional de país de língua oficial portuguesa — confirmar)', 'Certificado CIPLE/DEPLE', 'Outro certificado oficial', 'Ainda não tenho']],
                    ['key' => 'language_certificate_number', 'label' => 'Número do certificado de língua (se houver)', 'type' => 'text', 'required' => false],
                    ['key' => 'language_certificate_date', 'label' => 'Data do certificado de língua', 'type' => 'date', 'required' => false],
                    ['key' => 'culture_test_status', 'label' => 'Situação do teste de cultura', 'type' => 'select', 'required' => true, 'options' => ['Ainda não agendei', 'Agendado', 'Realizado e aprovado']],
                    ['key' => 'culture_test_date', 'label' => 'Data do teste de cultura (se agendado/realizado)', 'type' => 'date', 'required' => false],
                ],
                'documents' => [
                    ['key' => 'language_certificate', 'label' => 'Certificado de língua portuguesa (se houver)', 'required' => false],
                    ['key' => 'culture_test_proof', 'label' => 'Comprovativo do teste de cultura (se realizado)', 'required' => false],
                ],
            ],

            [
                'key' => 'subsistencia_nif',
                'document_groups' => [
                    ['label' => 'Renda', 'documents' => ['bank_statement', 'income_proof']],
                    ['label' => 'Taxa e NIF', 'documents' => ['fee_receipt', 'nif_proof']],
                ],
                'groups' => [
                    ['label' => 'NIF e renda', 'fields' => ['nif_pt', 'income_source', 'monthly_income_brl']],
                    ['label' => 'Taxa do processo', 'fields' => ['fee_paid', 'fee_payment_date']],
                ],
                'title' => 'Subsistência, NIF e taxas',
                'description' => 'Comprove meios de subsistência e informe o NIF português (pode ser obtido por representante fiscal).',
                'fields' => [
                    ['key' => 'nif_pt', 'label' => 'NIF português (se já tiver)', 'type' => 'text', 'required' => false],
                    ['key' => 'income_source', 'label' => 'Principal fonte de renda', 'type' => 'select', 'required' => true, 'options' => ['Emprego (CLT)', 'Empresa própria / PJ', 'Aposentadoria / pensão', 'Renda de investimentos', 'Outra']],
                    ['key' => 'monthly_income_brl', 'label' => 'Renda mensal média (R$)', 'type' => 'number', 'required' => true],
                    ['key' => 'fee_paid', 'label' => 'Já pagou a taxa do processo?', 'type' => 'boolean', 'required' => true],
                    ['key' => 'fee_payment_date', 'label' => 'Data do pagamento da taxa', 'type' => 'date', 'required' => false],
                ],
                'documents' => [
                    ['key' => 'bank_statement', 'label' => 'Extratos bancários (últimos 3 meses)', 'required' => true],
                    ['key' => 'income_proof', 'label' => 'Comprovante de renda (IR, holerite ou declaração)', 'required' => true],
                    ['key' => 'fee_receipt', 'label' => 'Comprovativo de pagamento da taxa', 'required' => false],
                    ['key' => 'nif_proof', 'label' => 'Comprovativo do NIF português', 'required' => false],
                ],
            ],

            [
                'key' => 'revisao_final',
                'groups' => [
                    ['label' => 'Declaração', 'fields' => ['truthfulness_declaration']],
                    ['label' => 'Assinatura', 'fields' => ['signature_name']],
                    ['label' => 'Observações', 'fields' => ['observations']],
                ],
                'title' => 'Entrega do Documento',
                'description' => 'Revise suas respostas e confirme a declaração de veracidade. Após aprovação desta etapa, o processo é concluído.',
                'fields' => [
                    ['key' => 'observations', 'label' => 'Observações para o agente', 'type' => 'textarea', 'required' => false],
                    ['key' => 'truthfulness_declaration', 'label' => 'Declaro que todas as informações são verdadeiras', 'type' => 'boolean', 'required' => true],
                    ['key' => 'signature_name', 'label' => 'Assinatura (digite seu nome completo)', 'type' => 'text', 'required' => true],
                ],
                'documents' => [],
            ],
        ],
    ],
];
