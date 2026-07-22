<?php

namespace App\Enums;

/**
 * Ação derivada do código de movimento CNAB 240 (Febraban/Sicredi).
 * Códigos ajustáveis quando o .CRT real da Seridó chegar.
 */
enum AcaoRetornoItem: string
{
    case ConfirmarEntrada = 'confirmar_entrada';
    case Liquidar = 'liquidar';
    case ExcluirTitulo = 'excluir_titulo';
    case Rejeitar = 'rejeitar';
    case Registrar = 'registrar';
}
