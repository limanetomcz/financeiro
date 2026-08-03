<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        @page { margin: 22px 25px 20px; }
        body { margin: 0; color: #111; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; border: 0; }
        .logo-cell { width: 29%; padding-top: 4px; }
        .logo { width: 150px; height: auto; }
        .company { width: 48%; padding-top: 4px; line-height: 1.25; font-size: 9px; }
        .company strong { font-size: 10px; }
        .date { width: 23%; padding-top: 20px; text-align: right; font-size: 8px; white-space: nowrap; }
        .title { margin: 20px 0 26px; text-align: center; font-size: 12px; font-weight: bold; }
        .summary { width: 31%; margin: 0 0 27px auto; font-size: 8px; }
        .summary th { font-weight: bold; text-align: left; padding-bottom: 3px; }
        .summary th:nth-child(2), .summary th:nth-child(3), .summary td:nth-child(2), .summary td:nth-child(3) { text-align: right; }
        .invoice-info { margin-bottom: 7px; line-height: 1.35; font-size: 9px; }
        .invoice-info strong { font-weight: bold; }
        .beneficiaries { table-layout: fixed; }
        .beneficiaries th { border-bottom: 1px solid #111; padding: 3px 2px 4px; font-weight: normal; text-align: left; }
        .beneficiaries td { padding: 3px 2px 0; vertical-align: top; white-space: nowrap; }
        .beneficiaries .code { width: 18%; }
        .beneficiaries .name { width: 43%; }
        .beneficiaries .date-col { width: 13%; }
        .beneficiaries .birth { width: 13%; }
        .beneficiaries .amount { width: 13%; text-align: right; }
        .beneficiaries.titulares .code { width: 25%; }
        .beneficiaries.titulares .name { width: 60%; }
        .beneficiaries.titulares .amount { width: 15%; }
        .beneficiaries tfoot td { border-top: 1px solid #111; padding-top: 5px; font-weight: bold; }
        .separator { border-top: 1px solid #111; margin-top: 10px; }
        .grand-total { text-align: right; font-weight: bold; padding-top: 5px; }
        .launches { width: 72%; margin: 42px auto 0; table-layout: fixed; }
        .launches th { font-weight: normal; text-align: left; padding-bottom: 4px; }
        .launches .description { width: 58%; }
        .launches .launch-amount { width: 15%; text-align: right; }
        .launches .observation { width: 27%; padding-left: 10px; }
        .launches td { padding: 3px 0; vertical-align: top; }
        .launches tfoot td { border-top: 1px solid #111; padding-top: 5px; font-weight: bold; }
        .right { text-align: right; }
        .center { text-align: center; }
    </style>
</head>

<body>
    <table class="header">
        <tr>
            <td class="logo-cell">
                <img class="logo" src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('imgs/logoUniodonto.png'))) }}" alt="Logo">
            </td>
            <td class="company">
                <strong>{{ $empresa['nome'] }}</strong><br>
                CNPJ: {{ $empresa['cnpj'] }}<br>
                {{ $empresa['endereco'] ?? '' }}
            </td>
            <td class="date">Data Gera&ccedil;&atilde;o: {{ $impresso_em }}</td>
        </tr>
    </table>

    <div class="title">{{ $titulo }}</div>

    <table class="summary">
        <tr>
            <th>Resumo de Valores da Fatura:</th>
            <th>Qtde</th>
            <th>Valor</th>
        </tr>
        <tr>
            <td></td>
            <td>{{ count($linhas) }}</td>
            <td>{{ number_format($total, 2, ',', '.') }}</td>
        </tr>
    </table>

    <div class="invoice-info">
        N&uacute;mero da Fatura: <strong>{{ $numero_fatura }} ({{ $sacado?->nome ?: '&mdash;' }})</strong><br>
        CNPJ: <strong>{{ $sacado?->documento ?: '&mdash;' }}</strong>
    </div>

    <table class="beneficiaries{{ $com_dependentes ? '' : ' titulares' }}">
        <thead>
            <tr>
                <th class="code">C&oacute;digo</th>
                <th class="name">Nome do Benefici&aacute;rio</th>
                @if($com_dependentes)
                    <th class="date-col">Inclus&atilde;o</th>
                    <th class="birth">Data Nasc.</th>
                @endif
                <th class="amount">Valor</th>
            </tr>
        </thead>
        <tbody>
            @forelse($linhas as $l)
                <tr>
                    <td>{{ $l['codigo'] ?: '&mdash;' }}</td>
                    <td>{{ $l['nome'] }}</td>
                    @if($com_dependentes)
                        <td>{{ $l['inclusao'] }}</td>
                        <td>{{ $l['nascimento'] }}</td>
                    @endif
                    <td class="amount">{{ number_format($l['valor'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $com_dependentes ? 5 : 3 }}" class="center">Nenhum benefici&aacute;rio na composi&ccedil;&atilde;o desta fatura.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="{{ $com_dependentes ? 4 : 2 }}"></td>
                <td class="amount">{{ number_format($total, 2, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="separator"></div>
    <div class="grand-total">{{ number_format($total, 2, ',', '.') }}</div>

    @if($lancamentos->isNotEmpty())
        <table class="launches">
            <thead>
                <tr>
                    <th class="description">Lan&ccedil;amentos</th>
                    <th class="launch-amount">Valor</th>
                    <th class="observation">Observa&ccedil;&atilde;o</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lancamentos as $lancamento)
                    <tr>
                        <td class="description">{{ $lancamento['ordem'] }} - {{ $lancamento['descricao'] }}</td>
                        <td class="launch-amount">{{ number_format($lancamento['valor'], 2, ',', '.') }}</td>
                        <td class="observation">{{ $lancamento['observacao'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td class="launch-amount">{{ number_format($total_geral, 2, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif
</body>

</html>
