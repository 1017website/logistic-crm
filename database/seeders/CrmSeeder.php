<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CrmSeeder extends Seeder
{
    public function run(): void
    {
        // Sales Users
        $salesUsers = [
            ['name' => 'Budi Santoso', 'email' => 'budi.santoso@logisticservice.co.id', 'phone' => '0812-3456-7890', 'position' => 'Sales Executive'],
            ['name' => 'Rina Wulandari', 'email' => 'rina.wulandari@logisticservice.co.id', 'phone' => '0813-4567-8901', 'position' => 'Sales Executive'],
            ['name' => 'Andi Wijaya', 'email' => 'andi.wijaya@logisticservice.co.id', 'phone' => '0814-5678-9012', 'position' => 'Sales Executive'],
            ['name' => 'Dimas Pratama', 'email' => 'dimas.pratama@logisticservice.co.id', 'phone' => '0815-6789-0123', 'position' => 'Sales Executive'],
            ['name' => 'Siti Aisyah', 'email' => 'siti.aisyah@logisticservice.co.id', 'phone' => '0816-7890-1234', 'position' => 'Sales Executive'],
        ];
        foreach ($salesUsers as $user) {
            DB::table('sales_users')->insert(array_merge($user, ['created_at' => now(), 'updated_at' => now()]));
        }

        // Customers
        $customers = [
            ['company_name' => 'PT. Sumber Makmur', 'pic_name' => 'Budi Hartono', 'pic_position' => 'Purchasing Manager', 'phone' => '0812-3456-7890', 'email' => 'budi.hartono@sumbermakmur.co.id', 'address' => 'Jl. Industri Raya No. 45, Cakung, Jakarta Timur 13910', 'industry' => 'Manufacturing', 'location' => 'Jakarta', 'status' => 'Existing', 'value_tag' => 'High Value', 'sales_user_id' => 1, 'customer_since' => '2023-01-12'],
            ['company_name' => 'PT. Cipta Abadi', 'pic_name' => 'Andi Wijaya', 'pic_position' => 'Purchasing Manager', 'phone' => '0813-9876-5432', 'email' => 'andi.wijaya@ciptaabadi.co.id', 'address' => 'Jl. Industri Raya No. 88, Rungkut, Surabaya 60293', 'industry' => 'Importir', 'location' => 'Surabaya', 'status' => 'Existing', 'value_tag' => 'Normal', 'sales_user_id' => 1, 'customer_since' => '2023-03-15'],
            ['company_name' => 'PT. Jaya Sentosa', 'pic_name' => 'Tommy Wibowo', 'pic_position' => 'Logistics Manager', 'phone' => '0811-2223-4445', 'email' => 'tommy@jayasentosa.co.id', 'address' => 'Jl. Raya Bekasi No. 12, Jakarta Timur', 'industry' => 'Exportir', 'location' => 'Jakarta', 'status' => 'Existing', 'value_tag' => 'High Value', 'sales_user_id' => 2, 'customer_since' => '2022-08-01'],
            ['company_name' => 'PT. Global Indo', 'pic_name' => 'Dedi Suhendra', 'pic_position' => 'Procurement', 'phone' => '0812-5566-7788', 'email' => 'dedi@globalindo.co.id', 'address' => 'Jl. MH Thamrin No. 5, Tangerang', 'industry' => 'Trading', 'location' => 'Tangerang', 'status' => 'Potential', 'value_tag' => 'Normal', 'sales_user_id' => 1, 'customer_since' => null],
            ['company_name' => 'CV. Maju Bersama', 'pic_name' => 'Rina Anita', 'pic_position' => 'Owner', 'phone' => '0813-3211-6677', 'email' => 'rina.anita@majubersama.co.id', 'address' => 'Jl. Siliwangi No. 34, Bekasi', 'industry' => 'Manufacturing', 'location' => 'Bekasi', 'status' => 'Existing', 'value_tag' => 'Normal', 'sales_user_id' => 2, 'customer_since' => '2023-06-20'],
            ['company_name' => 'PT. Prima Sukses', 'pic_name' => 'Jason', 'pic_position' => 'Sales Manager', 'phone' => '0812-7788-9900', 'email' => 'jason@primasukses.co.id', 'address' => 'Jl. Pemuda No. 21, Semarang', 'industry' => 'Retail', 'location' => 'Semarang', 'status' => 'Potential', 'value_tag' => 'Normal', 'sales_user_id' => 3, 'customer_since' => null],
            ['company_name' => 'PT. Lautan Biru', 'pic_name' => 'Steven', 'pic_position' => 'Director', 'phone' => '0811-8899-1122', 'email' => 'steven@lautanbiru.co.id', 'address' => 'Jl. Pelabuhan No. 8, Batam', 'industry' => 'Exportir', 'location' => 'Batam', 'status' => 'Existing', 'value_tag' => 'Normal', 'sales_user_id' => 1, 'customer_since' => '2022-11-10'],
            ['company_name' => 'PT. Indotech', 'pic_name' => 'Yudi', 'pic_position' => 'Procurement Manager', 'phone' => '0813-6677-8899', 'email' => 'yudi@indotech.co.id', 'address' => 'Jl. Teknologi No. 15, Bandung', 'industry' => 'Manufacturing', 'location' => 'Bandung', 'status' => 'Potential', 'value_tag' => 'Normal', 'sales_user_id' => 3, 'customer_since' => null],
            ['company_name' => 'PT. Sejahtera Abadi', 'pic_name' => 'Wulan', 'pic_position' => 'Logistics Manager', 'phone' => '0812-1234-5678', 'email' => 'wulan@sejahteraabadi.co.id', 'address' => 'Jl. Pattimura No. 33, Medan', 'industry' => 'Logistics', 'location' => 'Medan', 'status' => 'Existing', 'value_tag' => 'Normal', 'sales_user_id' => 2, 'customer_since' => '2023-09-05'],
            ['company_name' => 'PT. Karya Utama', 'pic_name' => 'Fajar', 'pic_position' => 'Owner', 'phone' => '0813-9090-1212', 'email' => 'fajar@karyautama.co.id', 'address' => 'Jl. Veteran No. 77, Makassar', 'industry' => 'Importir', 'location' => 'Makassar', 'status' => 'Potential', 'value_tag' => 'Normal', 'sales_user_id' => 1, 'customer_since' => null],
        ];
        foreach ($customers as $customer) {
            DB::table('customers')->insert(array_merge($customer, ['created_at' => now(), 'updated_at' => now()]));
        }

        // Vendors
        $vendors = [
            ['vendor_name' => 'PT. Samudera Indonesia', 'pic_name' => 'Rudi Hartono', 'pic_position' => 'Operations Manager', 'phone' => '0812-3456-7890', 'email' => 'rudi@samudera.co.id', 'address' => 'Jl. Pluit Selatan Raya No. 10, Penjaringan, Jakarta Utara 14450', 'vendor_type' => 'Shipping Line', 'service_type' => 'Sea Freight', 'coverage_area' => 'Jakarta, Surabaya, Medan', 'status' => 'Active', 'is_preferred' => true, 'rating' => 4.8, 'payment_term' => '30 Days', 'vendor_since' => '2021-01-12'],
            ['vendor_name' => 'PT. Trans Logistik', 'pic_name' => 'Andi Wijaya', 'pic_position' => 'Marketing Manager', 'phone' => '0813-9876-5432', 'email' => 'andi@translogistik.co.id', 'address' => 'Jl. Raya Bekasi No. 45, Jabodetabek', 'vendor_type' => 'Trucking', 'service_type' => 'Trucking', 'coverage_area' => 'Jabodetabek, Jawa Barat', 'status' => 'Active', 'is_preferred' => false, 'rating' => 4.5, 'payment_term' => '14 Days', 'vendor_since' => '2020-05-20'],
            ['vendor_name' => 'Maersk Line Indonesia', 'pic_name' => 'Dewi Lestari', 'pic_position' => 'Sales Representative', 'phone' => '0811-2233-4455', 'email' => 'dewi.maersk@maersk.com', 'address' => 'Gedung BRI II, Jakarta Pusat', 'vendor_type' => 'Shipping Line', 'service_type' => 'Sea Freight', 'coverage_area' => 'All Indonesia', 'status' => 'Active', 'is_preferred' => false, 'rating' => 4.7, 'payment_term' => '30 Days', 'vendor_since' => '2019-08-15'],
            ['vendor_name' => 'Garuda Indonesia Cargo', 'pic_name' => 'Budi Prasetyo', 'pic_position' => 'Account Manager', 'phone' => '0812-5566-7788', 'email' => 'budi.cargo@garuda.co.id', 'address' => 'Bandara Soekarno-Hatta, Tangerang', 'vendor_type' => 'Air Freight', 'service_type' => 'Air Freight', 'coverage_area' => 'All Indonesia', 'status' => 'Active', 'is_preferred' => false, 'rating' => 4.6, 'payment_term' => '21 Days', 'vendor_since' => '2020-11-01'],
            ['vendor_name' => 'PT. Berlian Jaya Logistics', 'pic_name' => 'Jimmy Setiawan', 'pic_position' => 'Direktur', 'phone' => '0813-6677-8899', 'email' => 'jimmy@berlianjaya.co.id', 'address' => 'Jl. Raya Semarang No. 22, Jawa Tengah', 'vendor_type' => 'Trucking', 'service_type' => 'Trucking', 'coverage_area' => 'Jawa Tengah, Jawa Timur', 'status' => 'Active', 'is_preferred' => false, 'rating' => 4.2, 'payment_term' => '14 Days', 'vendor_since' => '2021-03-08'],
        ];
        foreach ($vendors as $vendor) {
            DB::table('vendors')->insert(array_merge($vendor, ['created_at' => now(), 'updated_at' => now()]));
        }

        // Leads
        $leads = [
            ['lead_code' => 'LEAD-2025-0001', 'company_name' => 'PT. Maju Bersama', 'pic_name' => 'Rina Anita', 'phone' => '0813-3211-6677', 'industry' => 'Manufacturing', 'pipeline_stage' => 'Identifying', 'temperature' => 'Warm', 'service_type' => 'Import Sea Freight', 'route' => 'Shanghai - Surabaya', 'potensi_revenue' => 50000000, 'probability' => 20, 'lead_score' => 45, 'lead_source' => 'Website', 'sales_user_id' => 1, 'customer_id' => 5],
            ['lead_code' => 'LEAD-2025-0002', 'company_name' => 'PT. Global Indo', 'pic_name' => 'Dedi Suhendra', 'phone' => '0812-5566-7788', 'industry' => 'Trading', 'pipeline_stage' => 'Identifying', 'temperature' => 'Cold', 'service_type' => 'Trucking Domestic', 'route' => 'Jakarta - Surabaya', 'potensi_revenue' => 45000000, 'probability' => 15, 'lead_score' => 35, 'lead_source' => 'Cold Call', 'sales_user_id' => 1, 'customer_id' => 4],
            ['lead_code' => 'LEAD-2025-0003', 'company_name' => 'PT. Sejahtera Abadi', 'pic_name' => 'Wulan', 'phone' => '0812-1234-5678', 'industry' => 'Logistics', 'pipeline_stage' => 'Identifying', 'temperature' => 'Warm', 'service_type' => 'Air Freight', 'route' => 'Jakarta - Medan', 'potensi_revenue' => 60000000, 'probability' => 25, 'lead_score' => 55, 'lead_source' => 'Referral', 'sales_user_id' => 2, 'customer_id' => 9],
            ['lead_code' => 'LEAD-2025-0048', 'company_name' => 'PT. Cipta Abadi', 'pic_name' => 'Andi Wijaya', 'pic_position' => 'Purchasing Manager', 'phone' => '0813-9876-5432', 'email' => 'andi.wijaya@ciptaabadi.co.id', 'address' => 'Jl. Industri Raya No. 88, Rungkut, Surabaya 60293', 'industry' => 'Manufacturing', 'pipeline_stage' => 'Approaching', 'temperature' => 'Warm', 'service_type' => 'Import Sea Freight', 'route' => 'Shanghai, China - Surabaya, ID', 'commodity' => 'Mesin & Sparepart', 'volume_estimate' => '2 x 20\' DC / Bulan', 'timeline' => 'Juni 2025', 'notes_kebutuhan' => 'Butuh harga kompetitif dan estimasi transit time yang pasti.', 'catatan_internal' => "Customer minta harga bersaing, prioritas transit time yang pasti.\nDecision maker: Pak Andi (Purchasing), approval dari Pak Hendra (Direktur).\nStrategi: Follow up benefit service (transit time & handling) + harga.", 'potensi_revenue' => 250000000, 'probability' => 40, 'lead_score' => 65, 'lead_source' => 'Website', 'competitor' => '2 Forwarder lain', 'expected_closing' => '2025-06-30', 'sales_user_id' => 1, 'customer_id' => 2, 'next_follow_up' => '2025-05-22', 'next_follow_up_time' => '10:00:00', 'next_follow_up_notes' => 'Konfirmasi feedback quotation dan timeline decision.'],
            ['lead_code' => 'LEAD-2025-0049', 'company_name' => 'PT. Jaya Sentosa', 'pic_name' => 'Hendra', 'phone' => '0811-2223-4445', 'industry' => 'Exportir', 'pipeline_stage' => 'Follow Up', 'temperature' => 'Hot', 'service_type' => 'Export Sea Freight', 'route' => 'Surabaya - Shanghai', 'potensi_revenue' => 180000000, 'probability' => 60, 'lead_score' => 75, 'lead_source' => 'Referral', 'sales_user_id' => 1, 'customer_id' => 3],
            ['lead_code' => 'LEAD-2025-0050', 'company_name' => 'PT. Sinar Harapan', 'pic_name' => 'Wulan', 'phone' => '0812-9988-7766', 'industry' => 'Manufacturing', 'pipeline_stage' => 'Follow Up', 'temperature' => 'Warm', 'service_type' => 'Trucking Domestic', 'route' => 'Surabaya - Jakarta', 'potensi_revenue' => 90000000, 'probability' => 50, 'lead_score' => 60, 'lead_source' => 'Cold Call', 'sales_user_id' => 2],
            ['lead_code' => 'LEAD-2025-0051', 'company_name' => 'PT. Karya Utama', 'pic_name' => 'Fajar', 'phone' => '0813-9090-1212', 'industry' => 'Importir', 'pipeline_stage' => 'Closing', 'temperature' => 'Hot', 'service_type' => 'Import Sea Freight', 'route' => 'Hongkong - Jakarta', 'potensi_revenue' => 220000000, 'probability' => 80, 'lead_score' => 85, 'lead_source' => 'Referral', 'sales_user_id' => 1, 'customer_id' => 10],
            ['lead_code' => 'LEAD-2025-0052', 'company_name' => 'PT. Prima Sukses', 'pic_name' => 'Jason', 'phone' => '0812-7788-9900', 'industry' => 'Retail', 'pipeline_stage' => 'Closing', 'temperature' => 'Warm', 'service_type' => 'Export Air Freight', 'route' => 'Jakarta - Singapore', 'potensi_revenue' => 150000000, 'probability' => 70, 'lead_score' => 78, 'lead_source' => 'Website', 'sales_user_id' => 2],
            ['lead_code' => 'LEAD-2025-0053', 'company_name' => 'PT. Damai Sejahtera', 'pic_name' => 'Eko Prasetyo', 'phone' => '0819-1234-5678', 'industry' => 'Trading', 'pipeline_stage' => 'Won', 'temperature' => 'Hot', 'service_type' => 'Trucking Domestic', 'route' => 'Jakarta - Surabaya', 'potensi_revenue' => 50000000, 'probability' => 100, 'lead_score' => 95, 'lead_source' => 'Referral', 'sales_user_id' => 1],
        ];
        foreach ($leads as $lead) {
            DB::table('leads')->insert(array_merge(['created_at' => now(), 'updated_at' => now()], $lead));
        }

        // Activities
        $activities = [
            ['lead_id' => 4, 'customer_id' => 2, 'sales_user_id' => 1, 'type' => 'Call', 'subject' => 'Call - PT. Sumber Makmur', 'description' => 'Follow up penawaran jasa trucking project Surabaya - Jakarta.', 'activity_at' => '2025-05-20 09:30:00', 'status' => 'Done'],
            ['lead_id' => 4, 'customer_id' => 2, 'sales_user_id' => 1, 'type' => 'Visit', 'subject' => 'Visit - PT. Cipta Abadi', 'description' => 'Meeting kebutuhan import barang dari China.', 'activity_at' => '2025-05-19 14:00:00', 'status' => 'Done'],
            ['lead_id' => 4, 'customer_id' => 2, 'sales_user_id' => 1, 'type' => 'Email', 'subject' => 'Email - PT. Jaya Sentosa', 'description' => 'Kirim quotation sea freight rute Shanghai - Jakarta.', 'activity_at' => '2025-05-19 13:00:00', 'status' => 'Done'],
            ['lead_id' => 4, 'customer_id' => 2, 'sales_user_id' => 1, 'type' => 'Call', 'subject' => 'Call - PT. Sinar Harapan', 'description' => 'Konfirmasi harga dan jadwal pengiriman.', 'activity_at' => '2025-05-20 14:15:00', 'status' => 'Done'],
            ['lead_id' => 4, 'customer_id' => 2, 'sales_user_id' => 1, 'type' => 'Visit', 'subject' => 'Visit - CV. Maju Bersama', 'description' => 'Survey lokasi dan kebutuhan distribusi domestik.', 'activity_at' => '2025-05-20 15:30:00', 'status' => 'Pending'],
            ['lead_id' => 4, 'customer_id' => 2, 'sales_user_id' => 1, 'type' => 'Email', 'subject' => 'Email - PT. Global Indo', 'description' => 'Kirim dokumen rate card terbaru via email.', 'activity_at' => '2025-05-20 16:45:00', 'status' => 'Done'],
            ['lead_id' => 4, 'customer_id' => 2, 'sales_user_id' => 1, 'type' => 'Call', 'subject' => 'Call - PT. Cipta Abadi', 'description' => 'Konfirmasi feedback quotation dan timeline decision.', 'activity_at' => '2025-05-22 10:00:00', 'status' => 'Planned', 'next_follow_up' => '2025-05-22'],
        ];
        foreach ($activities as $activity) {
            DB::table('activities')->insert(array_merge($activity, ['created_at' => now(), 'updated_at' => now()]));
        }

        // Delivery Orders
        $dos = [
            ['do_number' => 'DO-2505-0123', 'customer_id' => 1, 'vendor_id' => 1, 'lead_id' => null, 'service_type' => 'Sea Freight', 'route' => 'Shanghai - JKT', 'amount' => 340000000, 'currency' => 'IDR', 'status' => 'Done', 'order_date' => '2025-05-16'],
            ['do_number' => 'DO-2504-0098', 'customer_id' => 1, 'vendor_id' => 2, 'lead_id' => null, 'service_type' => 'Trucking', 'route' => 'Surabaya - JKT', 'amount' => 120000000, 'currency' => 'IDR', 'status' => 'Done', 'order_date' => '2025-04-28'],
            ['do_number' => 'DO-2504-0076', 'customer_id' => 1, 'vendor_id' => 4, 'lead_id' => null, 'service_type' => 'Air Freight', 'route' => 'Hongkong - JKT', 'amount' => 210000000, 'currency' => 'IDR', 'status' => 'Done', 'order_date' => '2025-04-15'],
            ['do_number' => 'DO-2505-021', 'customer_id' => 2, 'vendor_id' => 1, 'lead_id' => null, 'service_type' => 'Sea Freight', 'route' => 'JKT - Shanghai', 'amount' => 1250, 'currency' => 'USD', 'status' => 'Done', 'order_date' => '2025-05-20'],
            ['do_number' => 'DO-2505-017', 'customer_id' => 2, 'vendor_id' => 1, 'lead_id' => null, 'service_type' => 'Sea Freight', 'route' => 'JKT - Singapore', 'amount' => 550, 'currency' => 'USD', 'status' => 'Done', 'order_date' => '2025-05-19'],
        ];
        foreach ($dos as $do) {
            DB::table('delivery_orders')->insert(array_merge($do, ['created_at' => now(), 'updated_at' => now()]));
        }

        // Quotations
        DB::table('quotations')->insert([
            'quotation_number' => 'QT-2025-0156',
            'lead_id' => 4,
            'customer_id' => 2,
            'service_type' => 'Import Sea Freight',
            'route' => 'Shanghai - Surabaya',
            'total_price' => 2450,
            'currency' => 'USD',
            'sent_at' => '2025-05-19',
            'valid_until' => '2025-06-02',
            'status' => 'Sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Vendor Rates
        $rates = [
            ['vendor_id' => 1, 'route' => 'Jakarta - Shanghai', 'container_type' => "20' DC", 'price' => 1250, 'currency' => 'USD', 'last_updated' => '2025-05-20'],
            ['vendor_id' => 1, 'route' => 'Jakarta - Singapore', 'container_type' => "20' DC", 'price' => 550, 'currency' => 'USD', 'last_updated' => '2025-05-20'],
            ['vendor_id' => 1, 'route' => 'Surabaya - Singapore', 'container_type' => "20' DC", 'price' => 600, 'currency' => 'USD', 'last_updated' => '2025-05-18'],
            ['vendor_id' => 1, 'route' => 'Jakarta - Rotterdam', 'container_type' => "40' HC", 'price' => 2450, 'currency' => 'USD', 'last_updated' => '2025-05-15'],
        ];
        foreach ($rates as $rate) {
            DB::table('vendor_rates')->insert(array_merge($rate, ['created_at' => now(), 'updated_at' => now()]));
        }
    }
}
