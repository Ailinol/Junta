<?php

namespace Database\Seeders;

class EverydayProductsSeeder extends RealisticPlatformSeeder
{
    protected function shouldMaintainLegacyPresentation(): bool
    {
        return false;
    }

    protected function description(string $title, string $unit, string $city): string
    {
        return parent::description($title, $unit, $city).' Dados fictícios para testes; os preços são valores de ensaio.';
    }

    /** @return list<array{key:string,title:string,category:string,unit:string,price:numeric-string,city:string,stage:string,ageHours:int,pace:string}> */
    protected function catalogue(): array
    {
        $rows = [
            ['arroz-5kg', 'Arroz branco em sacos de 5 kg — Maputo', 'alimentacao', 'saco', '340.00', 'Maputo', 'interest', 96, 'rising'],
            ['farinha-milho', 'Farinha de milho em sacos de 25 kg — Matola', 'alimentacao', 'saco', '920.00', 'Matola', 'interest', 72, 'achieved'],
            ['acucar', 'Açúcar branco em pacotes de 1 kg — Beira', 'alimentacao', 'pacote', '95.00', 'Beira', 'interest', 96, 'steady'],
            ['feijao', 'Feijão manteiga em sacos de 5 kg — Nampula', 'alimentacao', 'saco', '480.00', 'Nampula', 'interest', 120, 'slow'],
            ['oleo-5l', 'Óleo alimentar em garrafas de 5 L — Maputo', 'alimentacao', 'garrafa', '680.00', 'Maputo', 'interest', 96, 'near_goal'],
            ['massa', 'Massa esparguete em pacotes de 500 g — Matola', 'alimentacao', 'pacote', '65.00', 'Matola', 'interest', 48, 'steady'],
            ['leite', 'Leite UHT em caixas de 12 embalagens de 1 L — Maputo', 'alimentacao', 'caixa', '1140.00', 'Maputo', 'evaluation', 72, 'steady'],
            ['sal', 'Sal iodado em pacotes de 1 kg — Quelimane', 'alimentacao', 'pacote', '25.00', 'Quelimane', 'interest', 6, 'sparse'],
            ['ovos', 'Ovos em tabuleiros de 30 unidades — Matola', 'alimentacao', 'tabuleiro', '300.00', 'Matola', 'interest', 48, 'rising'],
            ['batata', 'Batata de consumo em sacos de 10 kg — Beira', 'alimentacao', 'saco', '650.00', 'Beira', 'evaluation', 48, 'steady'],
            ['cebola', 'Cebola seca em sacos de 5 kg — Tete', 'alimentacao', 'saco', '300.00', 'Tete', 'interest', 120, 'slow'],
            ['tomate', 'Tomate fresco em caixas de 5 kg — Chimoio', 'alimentacao', 'caixa', '350.00', 'Chimoio', 'interest', 72, 'rising'],
            ['agua', 'Água engarrafada em packs de 6 garrafas de 1,5 L — Maputo', 'alimentacao', 'pack', '240.00', 'Maputo', 'payment', 0, 'steady'],
            ['sardinha', 'Sardinha em caixas de 24 latas de 125 g — Maputo', 'alimentacao', 'caixa', '1680.00', 'Maputo', 'supply', 2, 'steady'],
            ['cha', 'Chá preto em caixas de 100 saquetas — Beira', 'alimentacao', 'caixa', '150.00', 'Beira', 'selected', 0, 'steady'],
            ['bolachas', 'Bolachas em caixas de 20 pacotes — Quelimane', 'alimentacao', 'caixa', '600.00', 'Quelimane', 'interest', 48, 'steady'],
            ['sabao', 'Sabão de lavar em caixas de 24 barras de 200 g — Maputo', 'outro', 'caixa', '720.00', 'Maputo', 'interest', 96, 'slow'],
            ['detergente', 'Detergente da loiça em embalagens de 5 L — Matola', 'outro', 'embalagem', '680.00', 'Matola', 'evaluation', 48, 'steady'],
            ['lixivia', 'Lixívia em caixas de 6 garrafas de 2 L — Beira', 'outro', 'caixa', '450.00', 'Beira', 'interest', 72, 'rising'],
            ['papel-higienico', 'Papel higiénico em pacotes de 4 rolos — Nampula', 'outro', 'pacote', '180.00', 'Nampula', 'interest', 96, 'steady'],
            ['pasta-dentes', 'Pasta de dentes em caixas de 12 tubos de 75 ml — Maputo', 'saude', 'caixa', '900.00', 'Maputo', 'selected', 0, 'steady'],
            ['escovas', 'Escovas de dentes em packs de 12 — Matola', 'saude', 'pack', '480.00', 'Matola', 'completed', 480, 'steady'],
            ['pensos', 'Pensos higiénicos em pacotes de 8 unidades — Quelimane', 'saude', 'pacote', '85.00', 'Quelimane', 'interest', 48, 'steady'],
            ['fraldas', 'Fraldas tamanho M em pacotes de 20 unidades — Maputo', 'saude', 'pacote', '450.00', 'Maputo', 'interest', 96, 'near_goal'],
            ['sabonetes', 'Sabonetes de banho em caixas de 12 — Matola', 'saude', 'caixa', '360.00', 'Matola', 'completed', 1080, 'steady'],
            ['champô', 'Champô familiar em frascos de 500 ml — Beira', 'saude', 'frasco', '220.00', 'Beira', 'draft', 0, 'steady'],
            ['alcool', 'Álcool a 70% em frascos de 500 ml — Nampula', 'saude', 'frasco', '150.00', 'Nampula', 'interest', 96, 'steady'],
            ['sacos-lixo', 'Sacos de lixo de 50 L em rolos de 10 — Maputo', 'outro', 'rolo', '95.00', 'Maputo', 'collection', 2, 'steady'],
            ['esponjas', 'Esponjas da loiça em packs de 6 — Matola', 'outro', 'pack', '120.00', 'Matola', 'partial', 2, 'steady'],
            ['cadernos', 'Cadernos de 96 folhas em packs de 10 — Maputo', 'escritorio', 'pack', '450.00', 'Maputo', 'interest', 72, 'rising'],
            ['canetas', 'Canetas azuis em caixas de 50 — Matola', 'escritorio', 'caixa', '350.00', 'Matola', 'evaluation', 48, 'steady'],
            ['lapis', 'Lápis HB em caixas de 72 — Beira', 'escritorio', 'caixa', '540.00', 'Beira', 'payment', 0, 'steady'],
            ['borrachas', 'Borrachas escolares em caixas de 20 — Chimoio', 'escritorio', 'caixa', '200.00', 'Chimoio', 'interest', 48, 'steady'],
            ['mochilas', 'Mochilas escolares reforçadas — Maputo', 'escritorio', 'unidade', '850.00', 'Maputo', 'draft', 0, 'steady'],
            ['pilhas', 'Pilhas AA em packs de 4 — Maputo', 'eletronicos', 'pack', '120.00', 'Maputo', 'interest', 96, 'near_goal'],
            ['lampadas', 'Lâmpadas LED de 9 W em caixas de 10 — Matola', 'eletronicos', 'caixa', '650.00', 'Matola', 'selected', 0, 'steady'],
            ['arroz-historico', 'Arroz branco em sacos de 5 kg — compra anterior de Maputo', 'alimentacao', 'saco', '325.00', 'Maputo', 'completed', 1680, 'steady'],
            ['feijao-historico', 'Feijão manteiga em sacos de 5 kg — compra anterior da Beira', 'alimentacao', 'saco', '465.00', 'Beira', 'completed', 2280, 'steady'],
            ['oleo-historico', 'Óleo alimentar em garrafas de 5 L — compra anterior da Matola', 'alimentacao', 'garrafa', '660.00', 'Matola', 'completed', 720, 'steady'],
            ['sabao-devolvido', 'Sabão de lavar em caixas de 24 barras — mínimo não atingido', 'outro', 'caixa', '700.00', 'Maputo', 'refunded', 360, 'steady'],
        ];

        $scenarios = [];
        foreach ($rows as $row) {
            $scenario = array_combine(['key', 'title', 'category', 'unit', 'price', 'city', 'stage', 'ageHours', 'pace'], $row);
            $scenario['key'] = 'everyday-v1-'.$scenario['key'];
            $scenarios[] = $scenario;
        }

        return $scenarios;
    }
}
