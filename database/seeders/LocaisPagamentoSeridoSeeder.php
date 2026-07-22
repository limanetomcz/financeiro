<?php

namespace Database\Seeders;

use App\Enums\BandeiraTaxaLocal;
use App\Enums\ModalidadeTaxaLocal;
use App\Enums\TipoLocalPagamento;
use App\Models\Cliente;
use App\Models\LocalPagamento;
use App\Models\TaxaLocalPagamento;
use App\Support\Tenant\ClienteContext;
use Illuminate\Database\Seeder;

/**
 * Catálogo Seridó (112) a partir de tb_localpagamento.
 * Canal ≠ tarifa: cartões viram taxas vinculadas ao local.
 */
class LocaisPagamentoSeridoSeeder extends Seeder
{
    public function run(): void
    {
        $cliente = Cliente::query()->where('codigo_cooperativa', '112')->first();
        if (! $cliente) {
            return;
        }

        ClienteContext::set($cliente);

        TaxaLocalPagamento::query()->delete();
        LocalPagamento::query()->delete();

        $canais = [
            ['codigo' => '2', 'codigo_legado' => '2', 'descricao' => 'UNIODONTO', 'tipo' => TipoLocalPagamento::Caixa, 'ordem' => 10],
            ['codigo' => '4', 'codigo_legado' => '4', 'descricao' => 'CAIXA ECONOMICA', 'tipo' => TipoLocalPagamento::Banco, 'ordem' => 20],
            ['codigo' => '7', 'codigo_legado' => '7', 'descricao' => 'SICREDI', 'tipo' => TipoLocalPagamento::Banco, 'ordem' => 30],
            ['codigo' => '8', 'codigo_legado' => '8', 'descricao' => 'BANCO DO BRASIL', 'tipo' => TipoLocalPagamento::Banco, 'ordem' => 40],
            ['codigo' => '69', 'codigo_legado' => '69', 'descricao' => 'ITAU - PIX', 'tipo' => TipoLocalPagamento::Pix, 'ordem' => 50],
            ['codigo' => '70', 'codigo_legado' => '70', 'descricao' => 'SICREDI - PIX', 'tipo' => TipoLocalPagamento::Pix, 'ordem' => 60],
            ['codigo' => 'ITAU_CARD', 'codigo_legado' => null, 'descricao' => 'ITAU - CARTAO', 'tipo' => TipoLocalPagamento::Cartao, 'ordem' => 70],
            ['codigo' => 'CARD', 'codigo_legado' => null, 'descricao' => 'CARTAO (LEGADO)', 'tipo' => TipoLocalPagamento::Cartao, 'ordem' => 80],
        ];

        $locais = [];
        foreach ($canais as $canal) {
            $locais[$canal['codigo']] = LocalPagamento::query()->create([
                'codigo' => $canal['codigo'],
                'codigo_legado' => $canal['codigo_legado'],
                'descricao' => $canal['descricao'],
                'tipo' => $canal['tipo'],
                'ativo' => true,
                'ordem' => $canal['ordem'],
            ]);
        }

        $taxasItau = [
            ['61', 'ITAU - DEBITO MASTER/VISA', ModalidadeTaxaLocal::Debito, BandeiraTaxaLocal::MasterVisa, 0.95],
            ['62', 'ITAU - DEBITO HIPER/ELO', ModalidadeTaxaLocal::Debito, BandeiraTaxaLocal::HiperEloAmex, 1.75],
            ['63', 'ITAU - CREDITO - MASTER/VISA', ModalidadeTaxaLocal::CreditoAvista, BandeiraTaxaLocal::MasterVisa, 1.64],
            ['64', 'ITAU - CREDITO - HIPER/ELO/AME', ModalidadeTaxaLocal::CreditoAvista, BandeiraTaxaLocal::HiperEloAmex, 2.44],
            ['65', 'ITAU-CREDITO 1-6 MASTER/VISA', ModalidadeTaxaLocal::Credito1a6, BandeiraTaxaLocal::MasterVisa, 1.92],
            ['66', 'ITAU-CREDITO 7-12 MASTER/VISA', ModalidadeTaxaLocal::Credito7a12, BandeiraTaxaLocal::MasterVisa, 1.95],
            ['67', 'ITAU-CREDITO1-6-HIPER/ELO/AME', ModalidadeTaxaLocal::Credito1a6, BandeiraTaxaLocal::HiperEloAmex, 2.72],
            ['68', 'ITAU-CREDITO7-12-HIPER/ELO/AME', ModalidadeTaxaLocal::Credito7a12, BandeiraTaxaLocal::HiperEloAmex, 2.75],
        ];

        $ordem = 10;
        foreach ($taxasItau as [$legado, $desc, $mod, $band, $taxa]) {
            TaxaLocalPagamento::query()->create([
                'local_pagamento_id' => $locais['ITAU_CARD']->id,
                'codigo_legado' => $legado,
                'descricao' => $desc,
                'modalidade' => $mod,
                'bandeira' => $band,
                'taxa_percentual' => $taxa,
                'ativo' => true,
                'ordem' => $ordem,
            ]);
            $ordem += 10;
        }

        $taxasLegado = [
            ['50', 'DEBITO', ModalidadeTaxaLocal::Debito, BandeiraTaxaLocal::Qualquer, 2.48],
            ['51', 'CREDITO A VISTA', ModalidadeTaxaLocal::CreditoAvista, BandeiraTaxaLocal::Qualquer, 3.48],
            ['52', 'CREDITO PARCELADO 2-6', ModalidadeTaxaLocal::Credito2a6, BandeiraTaxaLocal::Qualquer, 4.18],
            ['53', 'CREDITO PARCELADO 7-12', ModalidadeTaxaLocal::Credito7a12, BandeiraTaxaLocal::Qualquer, 4.43],
            ['54', 'ELO DEBITO', ModalidadeTaxaLocal::Debito, BandeiraTaxaLocal::Elo, 2.70],
            ['55', 'ELO CREDITO A VISTA', ModalidadeTaxaLocal::CreditoAvista, BandeiraTaxaLocal::Elo, 3.95],
            ['56', 'ELO CREDITO PARCELADO 2-6', ModalidadeTaxaLocal::Credito2a6, BandeiraTaxaLocal::Elo, 4.87],
            ['57', 'ELO CREDITO PARCELADO 7-12', ModalidadeTaxaLocal::Credito7a12, BandeiraTaxaLocal::Elo, 5.72],
        ];

        $ordem = 10;
        foreach ($taxasLegado as [$legado, $desc, $mod, $band, $taxa]) {
            TaxaLocalPagamento::query()->create([
                'local_pagamento_id' => $locais['CARD']->id,
                'codigo_legado' => $legado,
                'descricao' => $desc,
                'modalidade' => $mod,
                'bandeira' => $band,
                'taxa_percentual' => $taxa,
                'ativo' => true,
                'ordem' => $ordem,
            ]);
            $ordem += 10;
        }

        ClienteContext::clear();
    }
}
