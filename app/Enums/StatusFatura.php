<?php

namespace App\Enums;

enum StatusFatura: string
{
    case Processando = 'processando';
    case Rascunho = 'rascunho';
    case Aberta = 'aberta';
    case EmCobranca = 'em_cobranca';
    case Paga = 'paga';
    case Erro = 'erro';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Processando => 'Processando',
            self::Rascunho => 'Rascunho',
            self::Aberta => 'Aberta',
            self::EmCobranca => 'Em cobrança',
            self::Paga => 'Paga',
            self::Erro => 'Erro',
            self::Cancelada => 'Cancelada',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::Processando => 'Geração assíncrona em andamento (vidas/preços/impostos).',
            self::Rascunho => 'Rascunho — ainda não liberada.',
            self::Aberta => 'Fatura pronta — pode emitir cobrança/boleto.',
            self::EmCobranca => 'Cobrança/boleto emitido.',
            self::Paga => 'Fatura liquidada.',
            self::Erro => 'Falha na geração — ver mensagem_erro.',
            self::Cancelada => 'Fatura cancelada.',
        };
    }
}
