<?php

namespace App\Enums;

enum StatusRetornoBancario: string
{
    case Pendente = 'pendente';
    case Processando = 'processando';
    case Concluido = 'concluido';
    case Parcial = 'parcial';
    case Falha = 'falha';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Processando => 'Processando',
            self::Concluido => 'Concluído',
            self::Parcial => 'Parcial',
            self::Falha => 'Falha',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::Pendente => 'Arquivo .CRT recebido, ainda não processado.',
            self::Processando => 'Itens do retorno sendo aplicados.',
            self::Concluido => 'Todos os itens do retorno tratados com sucesso.',
            self::Parcial => 'Alguns itens ok, outros com erro/ignorados.',
            self::Falha => 'Falha ao processar o arquivo de retorno.',
        };
    }
}
