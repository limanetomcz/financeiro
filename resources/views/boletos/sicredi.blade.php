<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 12mm 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #000; }
        table { width: 100%; border-collapse: collapse; }
        .linha { font-size: 11px; font-weight: bold; letter-spacing: 0.5px; text-align: center; margin: 4px 0 8px; }
        .banco { font-size: 16px; font-weight: bold; }
        .banco span { border-right: 2px solid #000; padding-right: 6px; margin-right: 6px; }
        .cell { border: 1px solid #000; padding: 2px 4px; vertical-align: top; }
        .lbl { font-size: 7px; color: #333; display: block; }
        .val { font-size: 10px; font-weight: bold; }
        .right { text-align: right; }
        .mt { margin-top: 10px; }
        .mb { margin-bottom: 8px; }
        .corte { border-top: 1px dashed #000; margin: 10px 0; padding-top: 6px; font-size: 8px; color: #444; }
        .barcode { text-align: center; margin-top: 6px; }
        .barcode > div { margin: 0 auto; }
        h3 { font-size: 11px; margin: 0 0 4px; }
        .small { font-size: 8px; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td class="banco" style="width: 120px;"><span>{{ $barras->codigoBancoFormatado }}</span></td>
            <td class="linha">{{ $barras->linhaDigitavelFormatada }}</td>
        </tr>
    </table>

    <h3>RECIBO DO PAGADOR</h3>
    <table class="mb">
        <tr>
            <td class="cell" colspan="2">
                <span class="lbl">Beneficiário</span>
                <span class="val">{{ $conta->beneficiarioNome }} — {{ $cnpj_formatado }}</span>
            </td>
            <td class="cell" style="width: 160px;">
                <span class="lbl">Agência / Código Beneficiário</span>
                <span class="val">{{ $barras->agenciaCodigoBeneficiario }}</span>
            </td>
        </tr>
        <tr>
            <td class="cell">
                <span class="lbl">Pagador</span>
                <span class="val">
                    @if(!empty($pagador['chave'])){{ $pagador['chave'] }} — @endif{{ $pagador['nome'] }}
                </span>
                <div class="small">
                    CPF/CNPJ: {{ $documento_formatado ?: '—' }}<br>
                    {{ $pagador['endereco'] }} — {{ $pagador['bairro'] }}<br>
                    {{ $pagador['cidade'] }}/{{ $pagador['uf'] }} CEP {{ $pagador['cep'] }}
                </div>
            </td>
            <td class="cell" style="width: 110px;">
                <span class="lbl">Nosso Número</span>
                <span class="val">{{ $barras->nossoNumeroExibicao }}</span>
            </td>
            <td class="cell" style="width: 110px;">
                <span class="lbl">Vencimento</span>
                <span class="val">{{ $cobranca->vencimento->format('d/m/Y') }}</span>
            </td>
        </tr>
        <tr>
            <td class="cell" colspan="2">
                <span class="lbl">Informações</span>
                <span class="small">{{ $instrucao }}</span>
            </td>
            <td class="cell right">
                <span class="lbl">Valor do Documento</span>
                <span class="val">{{ number_format((float) $cobranca->valor, 2, ',', '.') }}</span>
            </td>
        </tr>
    </table>

    @if(count($composicao))
        <table class="mb">
            <tr>
                <td class="cell" colspan="2"><span class="lbl">Composição (valor por pessoa)</span></td>
            </tr>
            @foreach($composicao as $item)
                <tr>
                    <td class="cell">{{ $item['nome'] }}</td>
                    <td class="cell right" style="width: 100px;">R$ {{ number_format($item['valor'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="corte">Corte na linha pontilhada</div>

    <table>
        <tr>
            <td class="banco" style="width: 120px;"><span>{{ $barras->codigoBancoFormatado }}</span></td>
            <td class="linha">{{ $barras->linhaDigitavelFormatada }}</td>
        </tr>
    </table>

    <table>
        <tr>
            <td class="cell" colspan="5">
                <span class="lbl">Local de Pagamento</span>
                <span class="val">ATÉ O VENCIMENTO PAGÁVEL EM QUALQUER BANCO</span>
            </td>
            <td class="cell" style="width: 130px;">
                <span class="lbl">Vencimento</span>
                <span class="val">{{ $cobranca->vencimento->format('d/m/Y') }}</span>
            </td>
        </tr>
        <tr>
            <td class="cell" colspan="5">
                <span class="lbl">Beneficiário</span>
                <span class="val">{{ $conta->beneficiarioNome }} — {{ $cnpj_formatado }}</span>
            </td>
            <td class="cell">
                <span class="lbl">Agência / Código do Beneficiário</span>
                <span class="val">{{ $barras->agenciaCodigoBeneficiario }}</span>
            </td>
        </tr>
        <tr>
            <td class="cell">
                <span class="lbl">Data do Documento</span>
                <span class="val">{{ ($cobranca->data_emissao_boleto ?? $cobranca->created_at)?->format('d/m/Y') }}</span>
            </td>
            <td class="cell">
                <span class="lbl">Nº do Documento</span>
                <span class="val">{{ $barras->nossoNumeroExibicao }}</span>
            </td>
            <td class="cell">
                <span class="lbl">Espécie Doc</span>
                <span class="val">DM</span>
            </td>
            <td class="cell">
                <span class="lbl">Aceite</span>
                <span class="val">N</span>
            </td>
            <td class="cell">
                <span class="lbl">Data Processamento</span>
                <span class="val">{{ now()->format('d/m/Y') }}</span>
            </td>
            <td class="cell">
                <span class="lbl">Nosso Número</span>
                <span class="val">{{ $barras->nossoNumeroExibicao }}</span>
            </td>
        </tr>
        <tr>
            <td class="cell">
                <span class="lbl">Uso do Banco</span>
                <span class="val">&nbsp;</span>
            </td>
            <td class="cell">
                <span class="lbl">Carteira</span>
                <span class="val">{{ $conta->carteira }}</span>
            </td>
            <td class="cell">
                <span class="lbl">Espécie</span>
                <span class="val">R$</span>
            </td>
            <td class="cell">
                <span class="lbl">Quantidade</span>
                <span class="val">&nbsp;</span>
            </td>
            <td class="cell">
                <span class="lbl">Valor</span>
                <span class="val">&nbsp;</span>
            </td>
            <td class="cell right">
                <span class="lbl">(=) Valor do Documento</span>
                <span class="val">{{ number_format((float) $cobranca->valor, 2, ',', '.') }}</span>
            </td>
        </tr>
        <tr>
            <td class="cell" colspan="5" rowspan="5" style="height: 90px;">
                <span class="lbl">Instruções (Texto de responsabilidade do beneficiário)</span>
                <div class="mt small">{{ $instrucao }}</div>
                <div class="mt small">Este boleto refere-se ao vencimento {{ $cobranca->vencimento->format('d/m/Y') }}.</div>
            </td>
            <td class="cell right"><span class="lbl">(-) Desconto/Abatimento</span>&nbsp;</td>
        </tr>
        <tr><td class="cell right"><span class="lbl">(-) Outras deduções</span>&nbsp;</td></tr>
        <tr><td class="cell right"><span class="lbl">(+) Mora/Multa</span>&nbsp;</td></tr>
        <tr><td class="cell right"><span class="lbl">(+) Outros acréscimos</span>&nbsp;</td></tr>
        <tr><td class="cell right"><span class="lbl">(=) Valor Cobrado</span>&nbsp;</td></tr>
        <tr>
            <td class="cell" colspan="6">
                <span class="lbl">Pagador</span>
                <span class="val">
                    @if(!empty($pagador['chave'])){{ $pagador['chave'] }} — @endif{{ $pagador['nome'] }}
                    &nbsp;&nbsp; CPF/CNPJ: {{ $documento_formatado ?: '—' }}
                </span>
                <div class="small">
                    {{ $pagador['endereco'] }} — {{ $pagador['bairro'] }} —
                    {{ $pagador['cidade'] }}/{{ $pagador['uf'] }} — CEP {{ $pagador['cep'] }}
                </div>
                <div class="small">Sacador/Avalista</div>
            </td>
        </tr>
    </table>

    <div class="barcode">
        {!! $barcode_html !!}
        <div class="small">Ficha de Compensação / Autenticação Mecânica</div>
        <div class="small">{{ $barras->codigoBarras }}</div>
    </div>
</body>
</html>
