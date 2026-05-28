<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ── Vendors (contoh perusahaan logistik) ──
        DB::table('vendors')->insert([
            ['vendor_name'=>'JNE Express','pic_name'=>'Budi Santoso','pic_position'=>'Account Manager','phone'=>'0812-1111-2222','email'=>'budi@jne.co.id','address'=>'Jl. Tomang Raya No. 11, Jakarta Barat','vendor_type'=>'External','service_type'=>'Pengiriman Kilat & Instan','service_mode'=>'Tracking','status'=>'Active','relationship_status'=>'Existing','is_preferred'=>1,'rating'=>4.8,'payment_term'=>'Net 30','vendor_since'=>'2020-01-10','created_at'=>$now,'updated_at'=>$now],
            ['vendor_name'=>'Pelni Logistics','pic_name'=>'Dewi Rahayu','pic_position'=>'Sales Manager','phone'=>'0813-2222-3333','email'=>'dewi@pelni.co.id','address'=>'Jl. Gajah Mada No. 14, Jakarta Pusat','vendor_type'=>'External','service_type'=>'Sea Freight','service_mode'=>'Kontainer,Tracking','status'=>'Active','relationship_status'=>'Existing','is_preferred'=>1,'rating'=>4.5,'payment_term'=>'Net 45','vendor_since'=>'2019-06-01','created_at'=>$now,'updated_at'=>$now],
            ['vendor_name'=>'Garuda Cargo','pic_name'=>'Andi Kurniawan','pic_position'=>'Sales Executive','phone'=>'0811-3333-4444','email'=>'andi@garudacargo.com','address'=>'Bandara Soekarno-Hatta, Tangerang','vendor_type'=>'External','service_type'=>'Air Freight','service_mode'=>'Tracking','status'=>'Active','relationship_status'=>'Existing','is_preferred'=>0,'rating'=>4.6,'payment_term'=>'Net 30','vendor_since'=>'2021-03-15','created_at'=>$now,'updated_at'=>$now],
            ['vendor_name'=>'PT. Angkasa Mitra Logistik','pic_name'=>'Siti Nurhaliza','pic_position'=>'Operations Manager','phone'=>'0812-4444-5555','email'=>'siti@angkasamitra.co.id','address'=>'Surabaya, Jawa Timur','vendor_type'=>'External','service_type'=>'Land Freight','service_mode'=>'Wingbox,Kontainer','status'=>'Active','relationship_status'=>'Existing','is_preferred'=>0,'rating'=>4.3,'payment_term'=>'Net 30','vendor_since'=>'2022-01-20','created_at'=>$now,'updated_at'=>$now],
            ['vendor_name'=>'Tim Kurir Internal','pic_name'=>'Hendra Wijaya','pic_position'=>'Supervisor','phone'=>'0813-5555-6666','email'=>'hendra@internal.com','address'=>'Kantor Pusat','vendor_type'=>'Internal','service_type'=>'Pengiriman Kilat & Instan','service_mode'=>'Tracking','status'=>'Active','relationship_status'=>'Existing','is_preferred'=>0,'rating'=>4.0,'payment_term'=>null,'vendor_since'=>'2020-01-01','created_at'=>$now,'updated_at'=>$now],
        ]);

        // ── Customers ──
        DB::table('customers')->insert([
            ['company_name'=>'PT. Maju Bersama Tbk','pic_name'=>'Rina Suhartini','pic_position'=>'Procurement Manager','phone'=>'0812-6666-7777','email'=>'rina@majubersama.co.id','address'=>'Jl. Sudirman No. 55, Jakarta','industry'=>'Manufacturing','location'=>'Jakarta','status'=>'Existing','value_tag'=>'High Value','user_id'=>1,'customer_since'=>'2021-01-01','created_at'=>$now,'updated_at'=>$now],
            ['company_name'=>'CV. Cepat Sampai','pic_name'=>'Doni Pratama','pic_position'=>'Logistik Manager','phone'=>'0813-7777-8888','email'=>'doni@cepatsampai.com','address'=>'Jl. Raya Darmo No. 22, Surabaya','industry'=>'Trading','location'=>'Surabaya','status'=>'Existing','value_tag'=>'Normal','user_id'=>1,'customer_since'=>'2022-03-15','created_at'=>$now,'updated_at'=>$now],
            ['company_name'=>'PT. Nusantara Distribusi','pic_name'=>'Maya Kusuma','pic_position'=>'GM Operations','phone'=>'0811-8888-9999','email'=>'maya@nusantaradist.co.id','address'=>'Jl. Ahmad Yani No. 100, Bandung','industry'=>'Distribution','location'=>'Bandung','status'=>'Potential','value_tag'=>'High Value','user_id'=>1,'customer_since'=>null,'created_at'=>$now,'updated_at'=>$now],
        ]);

        // ── Leads ──
        DB::table('leads')->insert([
            ['lead_code'=>'LEAD-2026-0001','company_name'=>'PT. Sumber Makmur','pic_name'=>'Bayu Aji','phone'=>'0812-0001-0001','industry'=>'Retail','pipeline_stage'=>'Follow Up','temperature'=>'Hot','product_interest'=>'Land Freight Reguler','volume_estimate'=>'50 Ton/Bulan','potensi_revenue'=>75000000,'probability'=>60,'lead_score'=>78,'lead_source'=>'Referral','user_id'=>1,'customer_id'=>null,'notes_kebutuhan'=>'Butuh pengiriman reguler Surabaya-Jakarta.','competitor'=>'JNE, SiCepat','expected_closing'=>'2026-06-30','next_follow_up'=>'2026-06-05','created_at'=>$now,'updated_at'=>$now],
            ['lead_code'=>'LEAD-2026-0002','company_name'=>'PT. Ekspor Nusantara','pic_name'=>'Slamet Riyadi','phone'=>'0813-0002-0002','industry'=>'Export-Import','pipeline_stage'=>'Closing','temperature'=>'Hot','product_interest'=>'Sea Freight Kontainer','volume_estimate'=>'2 Kontainer/Bulan','potensi_revenue'=>150000000,'probability'=>80,'lead_score'=>85,'lead_source'=>'Direct','user_id'=>1,'customer_id'=>2,'notes_kebutuhan'=>'Pengiriman rutin ke Singapore dan Malaysia.','competitor'=>'Pelni, SPIL','expected_closing'=>'2026-06-15','next_follow_up'=>'2026-06-01','created_at'=>$now,'updated_at'=>$now],
        ]);

        // ── Delivery Orders ──
        $dos = [
            ['do_number'=>'DO-202605-0001','customer_id'=>1,'vendor_id'=>1,'currency'=>'IDR','status'=>'Done','order_date'=>'2026-05-10','delivery_type'=>'Pengiriman Kilat & Instan','origin'=>'Surabaya','destination'=>'Jakarta','tracking_number'=>'JNE20260510001','estimated_arrival'=>'2026-05-12'],
            ['do_number'=>'DO-202605-0002','customer_id'=>2,'vendor_id'=>2,'currency'=>'IDR','status'=>'Done','order_date'=>'2026-05-08','delivery_type'=>'Sea Freight','origin'=>'Surabaya','destination'=>'Batam','tracking_number'=>'PLN20260508002','estimated_arrival'=>'2026-05-14'],
            ['do_number'=>'DO-202605-0003','customer_id'=>3,'vendor_id'=>3,'currency'=>'IDR','status'=>'In Progress','order_date'=>'2026-05-05','delivery_type'=>'Air Freight','origin'=>'Jakarta','destination'=>'Makassar','tracking_number'=>null,'estimated_arrival'=>'2026-05-06'],
        ];

        $items = [
            [['service_name'=>'Pengiriman Paket SameDay','unit'=>'kg','qty'=>50,'buy_price'=>10000,'sell_price'=>15000]],
            [['service_name'=>'Freight Sea FCL 20ft','unit'=>'kontainer','qty'=>1,'buy_price'=>12000000,'sell_price'=>15000000]],
            [['service_name'=>'Air Cargo Express','unit'=>'kg','qty'=>200,'buy_price'=>25000,'sell_price'=>32000]],
        ];

        foreach ($dos as $idx => $do) {
            $doId = DB::table('delivery_orders')->insertGetId(array_merge($do, ['lead_id'=>null,'user_id'=>1,'notes'=>null,'created_at'=>$now,'updated_at'=>$now]));
            foreach ($items[$idx] as $item) {
                DB::table('delivery_order_items')->insert(array_merge($item, ['delivery_order_id'=>$doId,'description'=>null,'created_at'=>$now,'updated_at'=>$now]));
            }
        }
    }
}
