<?php

namespace App\Enums;

enum StatusParcela: string
{
    /** Ainda não exigível no CR (mês futuro). */
    case Prevista = 'prevista';
    case Aberta = 'aberta';
    case EmCobranca = 'em_cobranca';
    case Paga = 'paga';
    case Cancelada = 'cancelada';
    case Perdida = 'perdida';

    public function label(): string
    {
        return match ($this) {
            self::Prevista => 'Prevista',
            self::Aberta => 'Aberta',
            self::EmCobranca => 'Em cobrança',
            self::Paga => 'Paga',
            self::Cancelada => 'Cancelada',
            self::Perdida => 'Perdida',
        };
    }

    /** Texto para UI / migração do protótipo Sigoweb. */
    public function descricao(): string
    {
        return match ($this) {
            self::Prevista => 'Parcela de mês futuro — ainda não exigível; não entra em boleto/remessa até abrir.',
            self::Aberta => 'Parcela exigível no contas a receber, ainda sem boleto registrado.',
            self::EmCobranca => 'Boleto registrado (cobrança criada). Pode ir para remessa e gerar PDF.',
            self::Paga => 'Parcela liquidada (baixa manual, retorno bancário ou PIX).',
            self::Cancelada => 'Parcela cancelada — não cobra mais.',
            self::Perdida => 'Parcela baixada como perda / inadimplência definitiva.',
        };
    }
}
