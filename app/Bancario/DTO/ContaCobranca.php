<?php

namespace App\Bancario\DTO;

use App\Exceptions\DominioException;

readonly class ContaCobranca
{
    /**
     * @param  array{endereco?: string, bairro?: string, cidade?: string, cep?: string, uf?: string}  $pagadorPadrao
     */
    public function __construct(
        public string $codigoBanco,
        public string $agencia,
        public string $dvAgencia,
        public string $conta,
        public string $dvConta,
        public string $carteira,
        public string $modalidadeCarteira,
        public string $posto,
        public string $codigoCedente,
        public string $beneficiarioNome,
        public string $beneficiarioCnpj,
        public string $nomeBancoArquivo,
        public string $especieTitulo,
        public string $codigoMulta,
        public string $tipoInscricaoPf,
        public string $tipoInscricaoPj,
        public string $tipoDocumentoCobranca,
        public string $idEmissaoBoleto,
        public string $idDistribuicaoBoleto,
        public string $codigoJurosMoraComJuros,
        public string $codigoJurosMoraSemJuros,
        public string $codigoProtesto,
        public string $diasProtesto,
        public string $codigoDevolucao,
        public string $codigoMoeda,
        public int $diasDevolucao,
        public float $percentualMultaPadrao,
        public float $percentualJurosMes,
        public float $jurosDiaPadrao,
        public int $contadorDigitos,
        public string $layoutArquivoVersao,
        public string $layoutArquivoDensidade,
        public array $pagadorPadrao = [],
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromClienteConfig(array $config): self
    {
        $bancario = (array) data_get($config, 'bancario', []);
        $conta = (array) data_get($bancario, 'conta', []);
        $cnab = (array) data_get($bancario, 'cnab', []);
        $pagador = (array) data_get($bancario, 'pagador_padrao', []);

        $banco = (string) ($bancario['banco'] ?? 'sicredi');
        $codigo = (string) ($bancario['codigo_banco'] ?? match ($banco) {
            'sicredi', 'unicred' => '748',
            'bradesco' => '237',
            'bb' => '001',
            default => (string) ($bancario['codigo_banco'] ?? ''),
        });

        return new self(
            codigoBanco: $codigo,
            agencia: (string) ($conta['agencia'] ?? ''),
            dvAgencia: (string) ($conta['dv_agencia'] ?? ' '),
            conta: (string) ($conta['conta'] ?? ''),
            dvConta: (string) ($conta['dv_conta'] ?? ''),
            carteira: (string) ($conta['carteira'] ?? '1'),
            modalidadeCarteira: (string) ($conta['modalidade_carteira'] ?? '1'),
            posto: (string) ($conta['posto'] ?? $conta['formulario'] ?? ''),
            codigoCedente: (string) ($conta['codigo_cedente'] ?? ''),
            beneficiarioNome: (string) ($conta['beneficiario_nome'] ?? ''),
            beneficiarioCnpj: preg_replace('/\D/', '', (string) ($conta['beneficiario_cnpj'] ?? '')) ?: '',
            nomeBancoArquivo: (string) ($cnab['nome_banco'] ?? match ($banco) {
                'sicredi', 'unicred' => 'SICREDI',
                'bradesco' => 'BRADESCO',
                default => strtoupper($banco),
            }),
            especieTitulo: (string) ($cnab['especie_titulo'] ?? '99'),
            codigoMulta: (string) ($cnab['codigo_multa'] ?? '2'),
            tipoInscricaoPf: (string) ($cnab['tipo_inscricao_pf'] ?? '1'),
            tipoInscricaoPj: (string) ($cnab['tipo_inscricao_pj'] ?? '2'),
            tipoDocumentoCobranca: (string) ($cnab['tipo_documento'] ?? '1'),
            idEmissaoBoleto: (string) ($cnab['id_emissao_boleto'] ?? '2'),
            idDistribuicaoBoleto: (string) ($cnab['id_distribuicao_boleto'] ?? '2'),
            codigoJurosMoraComJuros: (string) ($cnab['codigo_juros_mora_com_juros'] ?? '1'),
            codigoJurosMoraSemJuros: (string) ($cnab['codigo_juros_mora_sem_juros'] ?? '3'),
            codigoProtesto: (string) ($cnab['codigo_protesto'] ?? '3'),
            diasProtesto: (string) ($cnab['dias_protesto'] ?? '00'),
            codigoDevolucao: (string) ($cnab['codigo_devolucao'] ?? '1'),
            codigoMoeda: (string) ($cnab['codigo_moeda'] ?? '09'),
            diasDevolucao: (int) ($conta['dias_devolucao'] ?? data_get($cnab, 'dias_devolucao', 60)),
            percentualMultaPadrao: (float) ($conta['percentual_multa'] ?? 2.0),
            percentualJurosMes: (float) ($conta['percentual_juros_mes'] ?? 0.0),
            jurosDiaPadrao: (float) ($conta['juros_dia'] ?? 0.0),
            contadorDigitos: max(1, (int) ($cnab['contador_digitos'] ?? 6)),
            layoutArquivoVersao: (string) ($cnab['layout_versao'] ?? '081'),
            layoutArquivoDensidade: (string) ($cnab['layout_densidade'] ?? '01600'),
            pagadorPadrao: [
                'endereco' => (string) ($pagador['endereco'] ?? 'ENDERECO NAO INFORMADO'),
                'bairro' => (string) ($pagador['bairro'] ?? 'CENTRO'),
                'cidade' => (string) ($pagador['cidade'] ?? ''),
                'cep' => preg_replace('/\D/', '', (string) ($pagador['cep'] ?? '')) ?: '00000000',
                'uf' => (string) ($pagador['uf'] ?? ''),
            ],
        );
    }

    public function validar(): void
    {
        $obrigatorios = [
            'agencia' => $this->agencia,
            'conta' => $this->conta,
            'dv_conta' => $this->dvConta,
            'posto' => $this->posto,
            'codigo_cedente' => $this->codigoCedente,
            'beneficiario_nome' => $this->beneficiarioNome,
            'beneficiario_cnpj' => $this->beneficiarioCnpj,
            'codigo_banco' => $this->codigoBanco,
        ];

        foreach ($obrigatorios as $campo => $valor) {
            if (trim((string) $valor) === '') {
                throw new DominioException(
                    "Config bancário incompleto: bancario.conta/código {$campo} é obrigatório."
                );
            }
        }
    }
}
