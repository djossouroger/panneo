<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Plomberie', 'slug' => 'plomberie', 'icon' => 'plumbing', 'indicative_min_price' => 5000, 'indicative_max_price' => 25000, 'callout_fee_label' => 'Déplacement (quartier)', 'callout_fee' => 3000],
            ['name' => 'Électricité', 'slug' => 'electricite', 'icon' => 'electricity', 'indicative_min_price' => 5000, 'indicative_max_price' => 30000, 'callout_fee_label' => 'Déplacement (quartier)', 'callout_fee' => 3000],
            ['name' => 'Serrurerie', 'slug' => 'serrurerie', 'icon' => 'locksmith', 'indicative_min_price' => 7000, 'indicative_max_price' => 30000, 'callout_fee_label' => 'Déplacement (quartier)', 'callout_fee' => 4000],
            ['name' => 'Climatisation', 'slug' => 'climatisation', 'icon' => 'air_conditioning', 'indicative_min_price' => 10000, 'indicative_max_price' => 40000, 'callout_fee_label' => 'Déplacement (quartier)', 'callout_fee' => 5000],
            ['name' => 'Électroménager', 'slug' => 'electromenager', 'icon' => 'appliance', 'indicative_min_price' => 8000, 'indicative_max_price' => 35000, 'callout_fee_label' => 'Déplacement (quartier)', 'callout_fee' => 5000],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                $category + ['is_active' => true, 'currency' => 'XOF']
            );
        }
    }
}
