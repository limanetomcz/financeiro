<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        {!! file_get_contents(resource_path('css/sicredi.css')) !!}
    </style>
</head>
<body>
    <div class="top-wrap">
        <div class="logo-box">
            @if(!empty($logo_base64))
                <img src="{{ $logo_base64 }}" alt="Logo do beneficiário">
            @else
                <div class="receipt-title">LOGO</div>
            @endif
        </div>

        <div class="receipt-column">
            <div class="receipt-title">Recibo do Pagador</div>
            <div class="receipt-box">
                <div class="receipt-meta">
                    Beneficiário: {{ $conta->beneficiarioNome }}<br>
                    <br>
                    CNPJ: {{ $cnpj_formatado }}
                </div>
            </div>
        </div>
    </div>

    <div class="info-box">
        <div class="info-row">
            <div class="info-col narrow">
<div class="texto-padrao" style="white-space: nowrap;">
    Nome do pagador:   
    @if(!empty($pagador['chave']))
        {{ $pagador['chave'] }} —
    @endif
    {{ $pagador['nome'] }}
</div>     

<div class="texto-padrao">Endereço:  {{ $pagador['endereco'] }}</div>
                <div class="texto-padrao">Município:  {{ $pagador['cidade'] }}</div>
            </div>
            <div class="info-col narrow">
                <div class="texto-estado">Estado:  {{ $pagador['uf'] }}</div>
            </div>
            <div class="info-col narrow">
                <div class="texto-padrao">CPF:  {{ $documento_formatado ?: '—' }}</div>
                <div class="texto-padrao">Bairro:  {{ $pagador['bairro'] }}</div>
                <div class="texto-padrao">CEP:  {{ $pagador['cep'] }}</div>
            </div>
        </div>
    </div>

    <table class="quadros">
    <tr>
        <!-- Quadro esquerdo -->
        <td class="quadro-esquerdo">
            <div class="titulo-info">
                Informações (Todas as informações deste bloqueto são de exclusiva responsabilidade do Beneficiário)
            </div>

            <div class="mensagem">
                NO CASO DE ATRASO, COBRAR 2,00% DE MULTA +
                JUROS DE MORA DE 1,00% DO MÊS SOBRE O VALOR PRINCIPAL.
            </div>

            <div class="demonstrativo">
                <div class="titulo-demonstativo">
                    Demonstrativo Financeiro
                </div>
                <div class="container-demonstrativo">
                    <table class="tabela-pessoas-demonstrativo">
                        <thead>
                            <tr>
                                <th class="nome">Nome</th>
                                <th class="valor">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($composicao as $item)
                                <tr>
                                    <td class="nome">{{ $item['nome'] }}</td>
                                    <td class="valor">
                                        R$ {{ number_format($item['valor'], 2, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
            </div>
        </td>

        <!-- Quadro direito -->
        <td class="quadro-direito">

            <div class="operadora">
                Operadora ANS - Nº 34391-9
            </div>

            <div class="referencia">
                <strong>Este boleto refere-se ao vencimento:</strong>
                <span class="data-vencimento">{{ $cobranca->vencimento->format('d/m/Y') }}</span>
            </div>

            <div class="tributos">
                TRIBUTOS INCIDENTES:
                R$ 3,83 (PIS: 0,65%, COFINS: 3,00%, ISS: 0,00%)
            </div>

        <div class="pagamentos-realizados"> 
    <div class="titulo-centro">
        Últimos 10 Pagamentos Realizados
    </div>

    <div class="container-pagamentos">
        <table class="tabela-pagamentos">
            <tr>
                <th>Referência</th>
                <th>DT Pagto</th>
                <th>Valor Pago</th>
            </tr>

            @forelse($historico10ultimosPagamewntos as $pagamento)
                <tr>
                    <td>{{ $pagamento->referencia_externa }}</td>
                    <td>{{ \Carbon\Carbon::parse($pagamento->pago_em)->format('d/m/Y') }}</td>
                    <td>R$ {{ number_format($pagamento->valor, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align: center;">
                        Nenhum pagamento encontrado.
                    </td>
                </tr>
            @endforelse

        </table>
    </div>
</div>

        </td>
    </tr>
</table>

    <table class="banco-header" role="presentation">
        <tr style="line-height: 0.75;">
            <td class="cell banco-logo" colspan="1" style="width: 20%; padding: 0 4px 0 2px;">
                @if(!empty($logo_sicredi_base64))
                    <img src="{{ $logo_sicredi_base64 }}" alt="Sicredi" />
                @else
                    <span class="banco">SICREDI</span>
                @endif
            </td>
            <td class="cell banco-codigo" colspan="1" style="width: 10%; padding: 0 4px;">
                <span>{{ $barras->codigoBancoFormatado }}</span>
            </td>
            <td class="cell banco-linha" colspan="4" style="width: 70%; padding: 0 4px;">
                <span class="banco-digitavel">{{ $barras->linhaDigitavelFormatada }}</span>
            </td>
        </tr>
        
        <tr style="line-height: 1.65;">
            <td class="cell" colspan="4">
                <span class="lbl">Beneficiário</span>
                <span class="val-padrao">{{ $conta->beneficiarioNome }} — {{ $cnpj_formatado }}</span>
            </td>
            <td class="cell">
                <span class="lbl">Agência / Código do Beneficiário</span>
                <span class="val-padrao">{{ $barras->agenciaCodigoBeneficiario }}</span>
            </td>
            <td class="cell">
                <span class="lbl">CNPJ</span>
                <span class="val-padrao">{{ $documento_formatado ?: '—' }}</span>
            </td>
        </tr>
        <tr style="line-height: 1.65;">
            <td class="cell" colspan="2">
                <span class="lbl">Nº do Documento</span>
                <span class="val-center">{{ $barras->nossoNumeroExibicao }}</span>
            </td>
            <td class="cell" colspan="2">
                <span class="lbl">Vencimento</span>
                <span class="val-center">{{ $cobranca->vencimento->format('d/m/Y') }}</span>
            </td>
            <td class="cell">
                <span class="lbl">Nosso Número</span>
                <span class="val-center">{{ $barras->nossoNumeroExibicao }}</span>
            </td>
            <td class="cell">
                <span class="lbl">(=) Valor do Documento</span>
                <span class="val-right">{{ number_format((float) $cobranca->valor, 2, ',', '.') }}</span>
            </td>
        </tr>
        <tr style="line-height: 2.75;">
            <td class="cell" colspan="6">
                <span class="val-center" style="vertical-align: top;">Autenticação Mecânica</span>
            </td>
        </tr>
        
    </table>
    <table class="banco-header" role="presentation" style="margin-top: 30px;">
        <tr style="line-height: 0.75;">
            <td class="cell banco-logo" colspan="1" style="width: 20%; padding: 0 4px 0 2px;">
                @if(!empty($logo_sicredi_base64))
                    <img src="{{ $logo_sicredi_base64 }}" alt="Sicredi" />
                @else
                    <span class="banco">SICREDI</span>
                @endif
            </td>
            <td class="cell banco-codigo" colspan="1" style="width: 10%; padding: 0 4px;">
                <span>{{ $barras->codigoBancoFormatado }}</span>
            </td>
            <td class="cell banco-linha" colspan="4" style="width: 70%; padding: 0 4px;">
                <span class="banco-digitavel">{{ $barras->linhaDigitavelFormatada }}</span>
            </td>
        </tr>

        <tr>
            <td class="cell" colspan="5">
                <span class="lbl">Local de Pagamento</span>
                <span class="val-pouco-direita">ATÉ O VENCIMENTO PAGÁVEL EM QUALQUER BANCO</span>
            </td>
            <td class="cell" style="width: 130px;">
                <span class="lbl">Vencimento</span>
                <span class="val-right">{{ $cobranca->vencimento->format('d/m/Y') }}</span>
            </td>
        </tr>
        <tr>
            <td class="cell" colspan="5">
                <span class="lbl">Beneficiário</span>
                <span class="val-pouco-direita">{{ $conta->beneficiarioNome }} — {{ $cnpj_formatado }}</span>
            </td>
            <td class="cell">
                <span class="lbl">Agência / Código do Beneficiário</span>
                <span class="val-right">{{ $barras->agenciaCodigoBeneficiario }}</span>
            </td>
        </tr>
        <tr>
            <td class="cell">
                <span class="lbl">Data do Documento</span>
                <span class="val-center">{{ ($cobranca->data_emissao_boleto ?? $cobranca->created_at)?->format('d/m/Y') }}</span>
            </td>
            <td class="cell">
                <span class="lbl">Espécie Doc</span>
                <span class="val-center">&nbsp;</span>
            </td>
            <td class="cell">
                <span class="lbl">Nº do Documento</span>
                <span class="val-center">{{ $barras->nossoNumeroExibicao }}</span>
            </td>
            <td class="cell">
                <span class="lbl">Aceite</span>
                <span class="val-center">&nbsp;</span>
            </td>
            <td class="cell">
                <span class="lbl">Data Processamento</span>
                <span class="val-right">{{ now()->format('d/m/Y') }}</span>
            </td>
            <td class="cell">
                <span class="lbl">Nosso Número</span>
                <span class="val-right">{{ $barras->nossoNumeroExibicao }}</span>
            </td>
        </tr>
        <tr>
            <td class="cell">
                <span class="lbl">Uso do Banco</span>
                <span class="val-center">&nbsp;</span>
            </td>
            <td class="cell">
                <span class="lbl">Carteira</span>
                <span class="val-center">{{ $conta->carteira }}</span>
            </td>
            <td class="cell">
                <span class="lbl">Espécie</span>
                <span class="val-center">R$</span>
            </td>
            <td class="cell">
                <span class="lbl">Quantidade</span>
                <span class="val-center">&nbsp;</span>
            </td>
            <td class="cell">
                <span class="lbl">Valor</span>
                <span class="val-center">&nbsp;</span>
            </td>
            <td class="cell">
                <span class="lbl">Valor do Documento</span>
                <span class="val-right">{{ number_format((float) $cobranca->valor, 2, ',', '.') }}</span>
            </td>
        </tr>
        <tr class="info-adicionais">
            <td class="cell" colspan="5" rowspan="4" style="height: 70px;">
                <span class="lbl-9" style="margin-bottom:15px; margin-left:5px">Informações (Todas as Infomações deste bloqueto são de exclusiva responsabilidade do Beneficiário):</span>
                <div style="font-weight: bold; font-size:10px; margin-left:5px">{{ $instrucao }}</div>
                <div style="margin-left:5px; font-weight: bold; font-size:9px;">Este boleto refere-se ao vencimento &nbsp;&nbsp;&nbsp;&nbsp;{{ $cobranca->vencimento->format('d/m/Y') }}</div>
            </td>
            <td class="cell"><span class="lbl">(-) Desconto/Abatimento</span>&nbsp;</td>
        </tr>
        <tr><td class="cell"><span class="lbl">(-) Outras deduções</span>&nbsp;</td></tr>
        <tr><td class="cell"><span class="lbl">(+) Mora/Multa</span>&nbsp;</td></tr>
        <tr><td class="cell"><span class="lbl">(=) Valor Cobrado</span>&nbsp;</td></tr>
    </table>
    <div class="info-box">
        <div class="info-row">
            <div class="info-col narrow">
                <div class="texto-padrao" style="white-space: nowrap;">
                    Nome do pagador:   
                    @if(!empty($pagador['chave']))
                        {{ $pagador['chave'] }} —
                    @endif
                    {{ $pagador['nome'] }}
                </div>     

<div class="texto-padrao">Endereço:  {{ $pagador['endereco'] }}</div>
                <div class="texto-padrao">Município:  {{ $pagador['cidade'] }}</div>
            </div> 
            <div class="info-col narrow">
                <div class="texto-estado">Estado:  {{ $pagador['uf'] }}</div>
            </div>
            <div class="info-col narrow">
                <div class="texto-padrao">CPF:  {{ $documento_formatado ?: '—' }}</div>
                <div class="texto-padrao">Bairro:  {{ $pagador['bairro'] }}</div>
                <div class="texto-padrao">CEP:  {{ $pagador['cep'] }}</div>
            </div>
        </div>
    </div>

    <div class="barcode">
        {!! $barcode_html !!}
    </div>
</body>
</html>
