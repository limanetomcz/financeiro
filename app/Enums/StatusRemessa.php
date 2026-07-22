<?php

namespace App\Enums;

enum StatusRemessa: string
{
    case Pendente = 'pendente';
    case Processando = 'processando';
    case Concluida = 'concluida';
    case Vazia = 'vazia';
    case Falha = 'falha';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Processando => 'Processando',
            self::Concluida => 'Concluída',
            self::Vazia => 'Vazia',
            self::Falha => 'Falha',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::Pendente => 'Remessa criada, ainda não gerou o arquivo CNAB.',
            self::Processando => 'Geração do .CRM em andamento (fila bancario).',
            self::Concluida => 'Arquivo .CRM gerado — pronto para download e envio ao banco.',
            self::Vazia => 'Nenhum título elegível no intervalo (vencimento, já em remessa, etc.).',
            self::Falha => 'Erro na geração — ver campo erro.',
        };
    }
}
