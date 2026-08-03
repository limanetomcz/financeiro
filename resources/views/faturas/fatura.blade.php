<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <style>
        * {
            box-sizing: border-box;
        }

        @page {
            margin: 20px 24px 18px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111;
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td,
        th {
            vertical-align: middle;
        }

        .bordered td,
        .bordered th {
            border: 1px solid #222;
            padding: 4px 5px;
        }

        .header td {
            height: 54px;
        }

        .logo-cell {
            width: 26%;
            text-align: center;
            border: 0 !important;
        }

        .logo {
            width: 150px;
            height: auto;
        }

        .company {
            width: 74%;
            line-height: 1.25;
            padding-left: 12px;
            border: 0 !important;
            font-size: 12px;
        }

        .company strong {
            font-size: 12px;
        }

        .title {
            margin: 4px 0;
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .label {
            display: block;
            font-size: 6px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .value {
            font-weight: bold;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .spacer td {
            height: 4px;
            padding: 0;
            border: 0;
        }
        .spacer-disc td {
            height: 20px;
            padding: 0;
            border: 0;
        }

        .info td {
            height: 28px;
        }

        .info .beneficiary {
            height: 25px;
            text-align: left;
        }

        .section-title {
            background: #d2d0d0;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            padding: 3px;
            border: 1px solid #222;
        }

        .client td {
            height: 20px;
            padding: 3px 5px;
        }

        .description td {
            padding: 6px 5px;
            line-height: 1.25;
        }

        .services th {
            font-size: 7px;
            height: 17px;
        }

        .services td {
            height: 17px;
        }

        .services .description {
            width: 68%;
        }

        .services .amount {
            width: 32%;
        }

        .services {
            border: 1px solid #222;
        }

        .services th {
            border: 1px solid #222;
        }

        .services td {
            height: 25px;
            border-top: 0;
            border-bottom: 0;
            border-left: 1px solid #222;
            border-right: 1px solid #222;
        }

        .services td:first-child {
            padding-left: 14px;
        }

        .services tr:last-child td:first-child {
            font-size: 7px;
            font-weight: normal;
            padding-left: 40px;
        }

        .services tr:last-child td {
            height: 13px;
            padding-top: 0;
            padding-bottom: 5;
            vertical-align: top;
        }

        .services tr:last-child td {
            border-bottom: 1px solid #222;
        }

        .summary td {
            width: 16.66%;
            height: 28px;
            padding: 3px 4px;
        }

        .summary {
            border-collapse: separate;
            border-spacing: 6px 0;
        }

        .summary .label {
            min-height: 14px;
        }

        .net td {
            padding: 5px 7px;
            font-size: 9px;
        }

        .net strong {
            font-size: 10px;
        }

        .footer {
            margin-top: 30px;
        }

        .footer td {
            width: 33.33%;
            height: 42px;
            vertical-align: bottom;
        }

        .signature {
            text-align: center;
            padding-bottom: 2px;
        }

        .signature-line {
            display: block;
            border-bottom: 1px solid #111;
            width: 145px;
            margin: 0 auto 3px;
            height: 16px;
        }

        .print-date {
            text-align: right;
            font-size: 10px;
        }

        .signature-image {
            display: block;
            width: 265px;
            height: 72px;
            object-fit: contain;
            margin: 0 auto 3px;
        }

        .label-text-negrito{
            font-weight: bold;
            font-size: 11px;
        }

        .info-table { width: 100%; border-collapse: collapse; border: 1px solid #000; font-family: Arial, Helvetica, sans-serif; }
        .info-table td { border: 1px solid #000; padding: 6px; vertical-align: middle; }
        .info-table .heading-row td { height: 30px; text-align: center; }
        .info-table .data-row td { height: 44px; text-align: center; }
        .info-table .data-row .label { display: none; }
        .info-table .data-row .ans-cell { display: none; }
        .info-table .label { margin-bottom: 4px; font-size: 10px; }
        .info-table .value { font-size: 16px; }
        .info-table .beneficiary { height: 48px; text-align: left; padding: 6px 8px; }
        .info-table .beneficiary .label { margin-bottom: 3px; }
        .info-table .beneficiary .value { font-size: 15px; }
        .info-table .ans-cell { width: 190px; text-align: center; }
        .info-table .ans-box { display: inline-block; background: #000; color: #fff; font-size: 10px; font-weight: bold; padding: 4px 18px; white-space: nowrap; }
        .client { width: 100%; table-layout: fixed; }
        .client tr:not(:first-child) td { width: 100%; padding: 5px 6px; font-size: 9px; font-weight: bold; }
        .client .section-title { height: 20px; padding: 3px; }
    </style>
</head>

<body>
    @php
        $emissao =
            optional($fatura->data_emissao)->format('d/m/Y') ?:
            (optional($fatura->created_at)->format('d/m/Y') ?:
            now()->format('d/m/Y'));
        $vencimento = optional($fatura->vencimento)->format('d/m/Y') ?: '—';
        $meses = [
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Março',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        ];
        $dataEmissao = optional($fatura->data_emissao ?: $fatura->created_at ?: now());
        $emissaoExtenso = $dataEmissao->day . ' de ' . $meses[$dataEmissao->month] . ' de ' . $dataEmissao->year;
        $valor = fn($number) => 'R$ ' . number_format((float) $number, 2, ',', '.');
        $descIndevidos = 0;
        $baseCalculo = (float) $fatura->valor_bruto;
        $taxaAdministracao = 0;
    @endphp

    <table class="bordered header">
        <tr>
            <td class="logo-cell"><img class="logo"
                    src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('imgs/logoUniodonto.png'))) }}">
            </td>
            <td class="company">
                <strong>{{ $empresa['nome'] }}</strong><br>
                CNPJ: {{ $empresa['cnpj'] }}<br>
                {{ $empresa['endereco'] }}
            </td>
        </tr>
    </table>

    <div class="title">FATURA DE SERVIÇO</div>

    <table class="info-table">
        <tr class="heading-row">
            <td>N&Uacute;MERO FATURA</td>
            <td>VALOR FATURA</td>
            <td>EMISS&Atilde;O</td>
            <td>VENCIMENTO</td>
            <td rowspan="2" class="ans-cell"><div class="ans-box">Operadora ANS - n&ordm; 34391-9</div></td>
        </tr>
        <tr class="data-row">
            <td>
                <div class="label">NÚMERO FATURA</div>
                <div class="value">{{ $numero_fatura }}</div>
            </td>

            <td>
                <div class="label">VALOR FATURA</div>
                <div class="value">{{ $valor($fatura->valor_liquido) }}</div>
            </td>

            <td>
                <div class="label">EMISSÃO</div>
                <div class="value">{{ $emissao }}</div>
            </td>

            <td>
                <div class="label">VENCIMENTO</div>
                <div class="value">{{ $vencimento }}</div>
            </td>

            <td class="ans-cell">
                <div class="ans-box">
                    Operadora ANS - nº 34391-9
                </div>
            </td>
        </tr>

        <tr>
            <td colspan="5" class="beneficiary">
                <div class="label">BENEFICIÁRIO:</div>
                <div class="value">{{ $empresa['nome'] }}</div>
            </td>
        </tr>
    </table>

    <table class="spacer">
        <tr>
            <td></td>
        </tr>
    </table>
    <table class="bordered client">
        <tr>
            <td class="section-title">DADOS DO CLIENTE</td>
        </tr>
        <tr>
            <td>
                <span class="label-text-negrito">CNPJ:</span><span class="label-text-negrito" style="margin-left:10px;"> {{ $sacado['documento'] ?: '—' }}</span><br><br>
                <span class="label-text-negrito">NOME SACADO:</span><span class="label-text-negrito" style="margin-left:3px;">{{ $sacado['nome'] ?: '—' }}</span><br><br>
                <span class="label-text-negrito">ENDEREÇO:</span><span class="label-text-negrito" style="margin-left:3px;">{{ $sacado['endereco'] ?: '—' }}@if ($sacado['bairro']) — {{ $sacado['bairro'] }}@endif</span><br><br>
                <span class="label-text-negrito">CIDADE:</span><span class="label-text-negrito" style="margin-left:10px;">{{ $sacado['cidade'] ?: '—' }}/{{ $sacado['uf'] ?: '—' }} — CEP {{ $sacado['cep'] ?: '—' }}</span>
            </td>
        </tr>
    </table>

    <table class="spacer">
        <tr>
            <td></td>
        </tr>
    </table>
    <table class="bordered description">
        <tr>
            <td>Deve(m) por serviços prestados, abaixo relacionados conforme duplicata de igual data e número, pagável
                no vencimento e praça acima.</td>
        </tr>
    </table>

    <table class="spacer-disc">
        <tr>
            <td></td>
        </tr>
    </table>

    <table class="bordered services">
        <tr>
            <th class="description" style="font-size:14px;">DISCRIMINAÇÃO</th>
            <th class="amount" style="font-size:14px;">VALOR</th>
        </tr>
        <tr>
            <td>1. Atos Cooperativos</td>
            <td class="right">{{ $valor($fatura->valor_bruto) }}</td>
        </tr>
        <tr>
            <td>2. Atos Cooperativos</td>
            <td class="right">{{ $valor(0) }}</td>
        </tr>
        <tr>
            <td>3. Atos não Cooperativos</td>
            <td class="right">{{ $valor(0) }}</td>
        </tr>
        <tr>
            <td>Retenção de Imposto de Renda na Fonte Conforme Art. 45 - Lei 8.54</td>
            <td></td>
        </tr>
    </table>

    <table class="spacer-disc">
        <tr>
            <td></td>
        </tr>
    </table>

    <table class="bordered summary">
        <tr>
            <td><span class="label">Valor bruto</span><span class="value">{{ $valor($fatura->valor_bruto) }}</span>
            </td>
            <td><span class="label">Acréscimos</span><span
                    class="value">{{ $valor($fatura->valor_acrescimos) }}</span></td>
            <td><span class="label">Desc. indevidos</span><span class="value">{{ $valor($descIndevidos) }}</span>
            </td>
            <td><span class="label">Base de cálculo</span><span class="value">{{ $valor($baseCalculo) }}</span></td>
            <td><span class="label">I.R.R.F.</span><span class="value">{{ $valor($impostos['ir']) }}</span></td>
            <td><span class="label">Tx. de administração</span><span
                    class="value">{{ $valor($taxaAdministracao) }}</span></td>
        </tr>
    </table>

    <table class="spacer-disc">
        <tr>
            <td></td>
        </tr>
    </table>
    
    <table class="bordered summary">
        <tr>
            <td><span class="label">PIS</span><span class="value">{{ $valor($impostos['pis']) }}</span></td>
            <td><span class="label">COFINS</span><span class="value">{{ $valor($impostos['cofins']) }}</span></td>
            <td><span class="label">CSLL</span><span class="value">{{ $valor($impostos['csll']) }}</span></td>
            <td><span class="label">ISS</span><span class="value">{{ $valor($impostos['iss']) }}</span></td>
            <td><span class="label">INSS</span><span class="value">{{ $valor($impostos['inss']) }}</span></td>
            <td><span class="label">Outros descontos</span><span
                    class="value">{{ $valor($impostos['outros']) }}</span></td>
        </tr>
    </table>

    <table class="bordered net" style="margin-top: 15px; width: 52%; margin-left: 48%;">
        <tr>
            <td><strong>VALOR LÍQUIDO:</strong></td>
            <td class="right"><strong>{{ $valor($fatura->valor_liquido) }}</strong></td>
        </tr>
    </table>

    <div class="footer-assinatura" style="margin-top: 60px;">
        <div style="text-align: center; font-size:12px;"><strong>{{ $emissaoExtenso }}</strong></div>
        <div class="signature" style="margin-top: 30px;">
            <img class="signature-image" src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('imgs/assinature.png'))) }}" alt="Assinatura">
        </div>
        <div class="print-date">Impresso em: {{ $impresso_em }} {{ now()->format('H:i') }}</div>
    </div>

    {{-- <table class="footer">
        <tr>
            <td><strong>{{ $emissaoExtenso }}.</strong></td>
            <td class="signature"><span class="signature-line"></span>Assinatura</td>
            <td class="print-date">Impresso em: {{ $impresso_em }} {{ now()->format('H:i') }}</td>
        </tr>
    </table> --}}
</body>

</html>
