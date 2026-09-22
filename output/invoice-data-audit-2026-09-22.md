# Audit Data Invoice setelah Revisi TR/NTR

Tanggal audit: 22 September 2026  
Database: salinan lokal `logistic_crm`

## Migrasi yang dijalankan

- `2026_09_22_000001_add_service_type_to_request_order_items`
- `2026_09_22_000002_backfill_request_order_item_service_types`
- `2026_09_22_000003_classify_audited_non_trucking_service_items`
- `2026_09_22_000004_backfill_legacy_trucking_job_codes`

## Perubahan data

- 73 Item Layanan diperiksa.
- 70 item diklasifikasikan sebagai NTR berdasarkan nama layanan yang ditemukan pada database: Bongkar TTL, Buruh, Empt TTL, Karantina, Kuli, Solar Timbang, dan Timbang.
- 3 item tetap TR: CDD BAK, Trailer Flatbed, dan Trucking Only.
- 4 Rincian Biaya bernama Trucking yang sebelumnya tidak memiliki `job_code` dibakukan menjadi TR.
- Tidak ada tipe item atau kode pekerjaan yang kosong atau tidak valid setelah koreksi.

## Dampak terhadap invoice

- Tidak ada draft invoice pada salinan database, sehingga tidak ada invoice yang digabung atau dihapus selama audit.
- Terdapat 7 invoice terbit dan semuanya bertipe TR.
- Ketujuh invoice terbit tidak diubah oleh migrasi.
- Total HPP dan jual setiap invoice cocok dengan jumlah itemnya.
- Tidak ditemukan duplikasi pasangan DO dan tipe pada `invoice_items`.
- Tidak ditemukan ketidaksesuaian status tagihan DO.

## DO siap invoice setelah koreksi

- 11 DO memiliki komponen yang masih dapat ditagih.
- Tersedia 11 komponen TR dan 4 komponen NTR.
- Empat komponen NTR yang sekarang muncul:
  - RDO-202609-0013, PT.MITRA INTERTRANS FORWADING: Kuli dan Bongkar TTL, HPP Rp100.000, jual Rp80.000.
  - RDO-202609-0015, PT.MASAJI KARGOSENTRA TAMA: Karantina, HPP Rp100.000, jual Rp50.000.
  - RDO-202609-0199, PT.MASAJI KARGOSENTRA TAMA: Bongkar TTL, HPP Rp50.000, jual Rp50.000.
  - RDO-202609-0046, PT.MASAJI KARGOSENTRA TAMA: Bongkar TTL, HPP Rp50.000, jual Rp50.000.

Jika seluruh DO Masaji yang siap tagih dipilih, pratinjau hasilnya:

- Invoice TR: 4 DO, HPP Rp3.950.000, jual Rp4.950.000.
- Invoice NTR: 3 DO, HPP Rp200.000, jual Rp150.000.

## Temuan yang perlu review Finance

- 27 dari 70 item NTR memiliki harga jual lebih rendah daripada harga beli.
- Dua komponen yang sudah berada pada DO Closed ikut dalam temuan tersebut:
  - RDO-202609-0013: gabungan NTR HPP Rp100.000 dan jual Rp80.000.
  - RDO-202609-0015: Karantina HPP Rp100.000 dan jual Rp50.000.
- Nominal tidak diubah otomatis karena merupakan data komersial yang perlu konfirmasi Finance.
- 65 item NTR lainnya masih berada pada tahap Surat Jalan. Nilai gabungannya HPP Rp5.780.000 dan jual Rp5.810.000; item tersebut baru masuk daftar siap invoice setelah DO ditutup.

## Kesimpulan

Revisi berhasil membaca biaya tambahan dari Item Layanan sebagai NTR tanpa menggandakan nilai dari Rincian Biaya. Data invoice yang sudah terbit tetap utuh. Koreksi nominal hanya diperlukan bila Finance memastikan margin negatif pada item tertentu bukan data yang disengaja.
