<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ProductSeeder extends Seeder
{
    /** [nama, harga, stok, status, deskripsi] */
    private const PRODUCTS = [
        ['Kopi Arabika Gayo 250g', 85000, 40, 'active', 'Biji kopi arabika asli Aceh Gayo, roasting medium. Aroma fruity dan rasa seimbang.'],
        ['Kopi Robusta Lampung 250g', 55000, 35, 'active', 'Kopi robusta Lampung bubuk, pahit mantap cocok untuk kopi tubruk.'],
        ['Teh Hijau Melati 100g', 30000, 50, 'active', 'Teh hijau dengan aroma melati, cocok diseduh panas maupun dingin.'],
        ['Madu Hutan Sumbawa 500ml', 120000, 4, 'active', 'Madu hutan murni dari Sumbawa tanpa campuran.'],
        ['Keripik Singkong Pedas 200g', 18000, 80, 'active', 'Keripik singkong renyah dengan bumbu balado pedas manis.'],
        ['Sambal Bawang Botol 150g', 25000, 3, 'active', 'Sambal bawang homemade, pedas gurih, tahan 3 bulan.'],
        ['Rendang Daging Kemasan 250g', 95000, 15, 'active', 'Rendang daging sapi khas Padang dalam kemasan vakum siap saji.'],
        ['Kaos Polos Cotton Combed 30s', 65000, 60, 'active', 'Kaos polos bahan cotton combed 30s, adem dan nyaman. Tersedia ukuran S-XL.'],
        ['Kemeja Batik Pria Lengan Pendek', 175000, 20, 'active', 'Kemeja batik motif parang modern, bahan katun primisima.'],
        ['Hijab Voal Segi Empat', 45000, 2, 'active', 'Hijab voal premium, mudah dibentuk dan tidak licin.'],
        ['Sandal Jepit Karet Premium', 35000, 0, 'active', 'Sandal jepit karet empuk dan anti slip.'],
        ['Tas Ransel Kanvas', 210000, 12, 'active', 'Tas ransel kanvas tebal dengan slot laptop 14 inci.'],
        ['Tumbler Stainless 500ml', 89000, 25, 'active', 'Tumbler stainless double wall, menjaga suhu hingga 12 jam.'],
        ['Earphone Bluetooth TWS', 249000, 18, 'active', 'Earphone TWS Bluetooth 5.3, baterai tahan 20 jam dengan case.'],
        ['Powerbank 10000mAh', 199000, 5, 'active', 'Powerbank 10000mAh fast charging 20W, dua port output.'],
        ['Kabel Data USB-C 1m', 39000, 100, 'active', 'Kabel data USB-C ke USB-C 1 meter, mendukung fast charging 60W.'],
        ['Lampu LED Meja Lipat', 129000, 9, 'active', 'Lampu meja LED lipat dengan 3 mode cahaya dan port USB.'],
        ['Sabun Sereh Wangi Handmade', 22000, 45, 'active', 'Sabun mandi handmade dengan minyak sereh wangi alami.'],
        ['Minyak Kayu Putih 60ml', 28000, 30, 'inactive', 'Minyak kayu putih asli, hangat dan melegakan.'],
        ['Gantungan Kunci Wayang', 15000, 70, 'inactive', 'Gantungan kunci kulit motif wayang, oleh-oleh khas Jawa.'],
    ];

    private const COLORS = ['#0d6efd', '#6610f2', '#d63384', '#dc3545', '#fd7e14', '#198754', '#20c997', '#0dcaf0', '#6f42c1', '#795548'];

    public function run(): void
    {
        $disk = Storage::disk('public');
        $disk->deleteDirectory('products/seed');

        foreach (self::PRODUCTS as $i => [$name, $price, $stock, $status, $description]) {
            $product = Product::create(compact('name', 'price', 'stock', 'status', 'description'));

            // Gambar placeholder SVG dibuat lokal (tanpa hotlink, tanpa ekstensi GD).
            foreach ([1, 2] as $n) {
                $path = "products/seed/{$product->id}-{$n}.svg";
                $disk->put($path, $this->placeholderSvg($name, "Foto {$n}", self::COLORS[($i + $n) % count(self::COLORS)]));
                $product->images()->create(['path' => $path, 'sort_order' => $n]);
            }
        }
    }

    private function placeholderSvg(string $title, string $subtitle, string $color): string
    {
        $lines = array_map('htmlspecialchars', explode("\n", wordwrap($title, 18, "\n", true)));
        $startY = 300 - (count($lines) - 1) * 28;
        $text = '';
        foreach ($lines as $k => $line) {
            $text .= '<text x="300" y="'.($startY + $k * 56).'" font-size="44" font-weight="bold" fill="#fff" text-anchor="middle" font-family="Arial, sans-serif">'.$line.'</text>';
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$color}"/><stop offset="1" stop-color="#212529"/></linearGradient></defs>
  <rect width="600" height="600" fill="url(#g)"/>
  <text x="300" y="120" font-size="72" text-anchor="middle">🛍️</text>
  {$text}
  <text x="300" y="540" font-size="26" fill="#ffffffcc" text-anchor="middle" font-family="Arial, sans-serif">Axelo Mart · {$subtitle}</text>
</svg>
SVG;
    }
}
