<?php

namespace App\Enums;

enum StatusCobranca: string
{
    case Aberta = 'aberta';
    case Paga = 'paga';
    case Cancelada = 'cancelada';
    case Expirada = 'expirada';

    public function label(): string
    {
        return match ($this) {
            self::Aberta => 'Aberta',
            self::Paga => 'Paga',
            self::Cancelada => 'Cancelada',
            self::Expirada => 'Expirada',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::Aberta => 'Cobrança/boleto em aberto — aguarda pagamento ou retorno do banco.',
            self::Paga => 'Cobrança liquidada.',
            self::Cancelada => 'Cobrança cancelada / título excluído (ex.: retorno 09/10).',
            self::Expirada => 'Cobrança vencida sem liquidação útil (baixa por prazo).',
        };
    }
}
