<?php

namespace App\Bancario\DTO;

readonly class PagadorRemessa
{
    public function __construct(
        public string $tipoInscricao,
        public string $inscricao,
        public string $nome,
        public string $endereco,
        public string $bairro,
        public string $cidade,
        public string $cep,
        public string $uf,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'tipo_inscricao' => $this->tipoInscricao,
            'inscricao' => $this->inscricao,
            'nome' => $this->nome,
            'endereco' => $this->endereco,
            'bairro' => $this->bairro,
            'cidade' => $this->cidade,
            'cep' => $this->cep,
            'uf' => $this->uf,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tipoInscricao: (string) ($data['tipo_inscricao'] ?? '1'),
            inscricao: (string) ($data['inscricao'] ?? ''),
            nome: (string) ($data['nome'] ?? ''),
            endereco: (string) ($data['endereco'] ?? ''),
            bairro: (string) ($data['bairro'] ?? ''),
            cidade: (string) ($data['cidade'] ?? ''),
            cep: preg_replace('/\D/', '', (string) ($data['cep'] ?? '')) ?: '00000000',
            uf: (string) ($data['uf'] ?? 'RN'),
        );
    }
}
