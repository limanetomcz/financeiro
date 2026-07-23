<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 20px; }
        h1 { font-size: 14px; margin: 0 0 6px; }
        .sub { margin-bottom: 12px; color: #333; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #444; padding: 4px 6px; }
        th { background: #eee; font-size: 9px; text-transform: uppercase; }
        .right { text-align: right; }
        .center { text-align: center; }
        tfoot td { font-weight: bold; }
        .meta { margin-bottom: 10px; line-height: 1.4; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <div class="sub">{{ $empresa['nome'] }}</div>

    <div class="meta">
        Fatura: <strong>{{ $numero_fatura }}</strong>
        · Competência: <strong>{{ $fatura->competencia }}</strong>
        · Vencimento: <strong>{{ optional($fatura->vencimento)->format('d/m/Y') }}</strong><br>
        Sacado: <strong>{{ $sacado?->chave_sigoweb }} — {{ $sacado?->nome }}</strong><br>
        Impresso em: {{ $impresso_em }}
        @if(!$com_dependentes)
            · <em>Somente titulares</em>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Família</th>
                <th>Dep.</th>
                <th>Tipo</th>
                <th>Nome</th>
                <th>TP</th>
                <th class="right">Valor</th>
            </tr>
        </thead>
        <tbody>
            @forelse($linhas as $l)
                <tr>
                    <td class="center">{{ $l['familia'] }}</td>
                    <td class="center">{{ $l['depend'] }}</td>
                    <td class="center">{{ $l['tipodep'] }}</td>
                    <td>{{ $l['nome'] }}</td>
                    <td class="center">{{ $l['tipopag'] }}</td>
                    <td class="right">{{ number_format($l['valor'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="center">Nenhum beneficiário na composição desta fatura.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" class="right">Total</td>
                <td class="right">{{ number_format($total, 2, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
