<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 24px; }
        h1 { font-size: 16px; margin: 0 0 8px; text-align: center; letter-spacing: 1px; }
        .empresa { text-align: center; margin-bottom: 14px; line-height: 1.35; }
        .empresa strong { font-size: 13px; }
        table { width: 100%; border-collapse: collapse; }
        .box td, .box th { border: 1px solid #333; padding: 5px 7px; }
        .muted { color: #444; font-size: 10px; }
        .right { text-align: right; }
        .center { text-align: center; }
        .section { margin-top: 14px; }
        .label { font-size: 10px; text-transform: uppercase; color: #333; }
        .totais td { padding: 4px 6px; border: 1px solid #333; }
        .footer { margin-top: 18px; font-size: 10px; }
    </style>
</head>
<body>
    <h1>FATURA DE SERVIÇO</h1>
    <div class="empresa">
        <strong>{{ $empresa['nome'] }}</strong><br>
        {{ $empresa['cnpj'] }}<br>
        <span class="muted">{{ $empresa['endereco'] }}</span>
    </div>

    <table class="box">
        <tr>
            <td><span class="label">Número fatura</span><br><strong>{{ $numero_fatura }}</strong></td>
            <td class="right"><span class="label">Valor fatura</span><br><strong>R$ {{ number_format((float)$fatura->valor_liquido, 2, ',', '.') }}</strong></td>
            <td class="center"><span class="label">Emissão</span><br>{{ optional($fatura->data_emissao)->format('d/m/Y') ?: (optional($fatura->created_at)->format('d/m/Y') ?: now()->format('d/m/Y')) }}</td>
            <td class="center"><span class="label">Vencimento</span><br>{{ optional($fatura->vencimento)->format('d/m/Y') }}</td>
        </tr>
    </table>

    <div class="section">
        <div class="label">Dados do cliente / sacado</div>
        <table class="box" style="margin-top:4px;">
            <tr>
                <td colspan="2">
                    <strong>{{ $sacado['chave'] }} — {{ $sacado['nome'] }}</strong><br>
                    CNPJ: {{ $sacado['documento'] ?: '—' }}<br>
                    {{ $sacado['endereco'] }}
                    @if($sacado['bairro']) — {{ $sacado['bairro'] }}@endif<br>
                    {{ $sacado['cidade'] }}/{{ $sacado['uf'] }} — CEP {{ $sacado['cep'] }}
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="label">Discriminação</div>
        <table class="box" style="margin-top:4px;">
            <tr>
                <th style="width:70%;">Descrição</th>
                <th class="right">Valor</th>
            </tr>
            <tr>
                <td>1. Atos Cooperativos (mensalidades do plano)</td>
                <td class="right">R$ {{ number_format((float)$fatura->valor_bruto, 2, ',', '.') }}</td>
            </tr>
            <tr>
                <td>2. Atos Cooperativos</td>
                <td class="right">R$ 0,00</td>
            </tr>
            <tr>
                <td>3. Atos não Cooperativos</td>
                <td class="right">R$ 0,00</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="label">Impostos / retenções</div>
        <table class="totais" style="margin-top:4px;">
            <tr>
                <td>Valor bruto<br><strong>R$ {{ number_format((float)$fatura->valor_bruto, 2, ',', '.') }}</strong></td>
                <td>Acréscimos<br><strong>R$ {{ number_format((float)$fatura->valor_acrescimos, 2, ',', '.') }}</strong></td>
                <td>I.R.R.F.<br><strong>R$ {{ number_format($impostos['ir'], 2, ',', '.') }}</strong></td>
                <td>ISS<br><strong>R$ {{ number_format($impostos['iss'], 2, ',', '.') }}</strong></td>
            </tr>
            <tr>
                <td>PIS<br><strong>R$ {{ number_format($impostos['pis'], 2, ',', '.') }}</strong></td>
                <td>COFINS<br><strong>R$ {{ number_format($impostos['cofins'], 2, ',', '.') }}</strong></td>
                <td>CSLL<br><strong>R$ {{ number_format($impostos['csll'], 2, ',', '.') }}</strong></td>
                <td>INSS<br><strong>R$ {{ number_format($impostos['inss'], 2, ',', '.') }}</strong></td>
            </tr>
            <tr>
                <td colspan="2">Outros descontos<br><strong>R$ {{ number_format($impostos['outros'], 2, ',', '.') }}</strong></td>
                <td colspan="2">Valor líquido<br><strong>R$ {{ number_format((float)$fatura->valor_liquido, 2, ',', '.') }}</strong></td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Competência: {{ $fatura->competencia }} · Status: {{ $fatura->status?->value }}<br>
        Data impressão: {{ $impresso_em }}
    </div>
</body>
</html>
