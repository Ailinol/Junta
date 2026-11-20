<?php

namespace App\Support;

use Illuminate\Support\Str;

class ProductIllustrations
{
    public static function forTitle(string $title): ?string
    {
        $title = Str::ascii(Str::lower($title));
        $products = [
            'farinha' => 'flour', 'arroz' => 'rice', 'oleo' => 'oil', 'cimento' => 'cement',
            'sementes' => 'seeds', 'material escolar' => 'school', 'paineis solares' => 'solar',
            'lampadas' => 'bulb', 'papel a4' => 'paper', 'primeiros socorros' => 'first-aid',
            'fertilizante' => 'fertilizer', 'feijao' => 'beans', 'acucar' => 'sugar',
            'batata' => 'potatoes', 'cebola' => 'onions', 'agua engarrafada' => 'water',
            'sabao' => 'soap', 'detergente' => 'detergent', 'cadernos' => 'notebooks',
            'enxadas' => 'hoe', 'tubos' => 'pipes', 'fraldas' => 'diapers',
            'luvas' => 'gloves', 'compra com transporte partilhado' => 'groceries',
        ];
        foreach ($products as $keyword => $image) {
            if (str_contains($title, $keyword)) {
                return url('/images/campaigns/product-'.$image.'-v1.svg');
            }
        }

        return null;
    }
}
