from pathlib import Path
from reportlab.pdfgen import canvas
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import ParagraphStyle
from reportlab.platypus import Paragraph
from reportlab.lib.utils import ImageReader
from reportlab.lib.colors import HexColor
import zipfile

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'output' / 'tutorial-rdo'
SHOTS = OUT / 'screenshots'
W, H = landscape(A4)
pages = [
    ('Panduan revisi RDO dan DO',
     'Kode sektor pada detail order',
     '<b>Tujuan:</b> menggunakan kode sektor, mengembalikan DO untuk koreksi harga dan biaya, serta memeriksa kemungkinan RDO duplikat. '
     'Buka <b>Request DO</b>, lalu klik ikon <b>Detail &amp; Flow</b> pada order. Kode sektor tampil di informasi utama dan juga di detail Delivery Order.',
     '01-kode-sektor-detail.png',
     'Screenshot aplikasi lokal menggunakan data contoh. Panduan ini berlaku setelah revisi dipasang pada aplikasi yang digunakan.'),
    ('Mengisi atau memperbarui kode sektor',
     'Petugas yang memiliki akses membuat atau mengedit RDO',
     '<b>1.</b> Buka <b>Request DO</b>, pilih <b>Tambah Request DO</b> atau ikon pensil pada RDO yang masih dapat diedit. '
     '<b>2.</b> Buka <b>Detail Operasional Muatan</b>, lalu bagian <b>Container &amp; Area</b>. '
     '<b>3.</b> Isi <b>Kode Sektor</b> sesuai kode yang digunakan perusahaan, kemudian simpan. Contoh di gambar: SBY-JKT-01.',
     '06-form-kode-sektor.png',
     'Kode sektor membantu Finance menentukan harga dan biaya; nilainya tidak mengisi harga secara otomatis. Jika kode kosong, detail memakai nilai Sektor jika tersedia.'),
    ('Mengembalikan DO ke RDO',
     'Dilakukan oleh Admin atau Sales Manager termasuk Super Admin',
     '<b>1.</b> Buka <b>Delivery Orders</b>, lalu detail DO yang akan diperbaiki. '
     '<b>2.</b> Pada panel <b>Kembalikan ke RDO</b>, tulis alasan pengembalian. '
     '<b>3.</b> Klik <b>Kembalikan ke RDO</b> dan pilih <b>OK</b> pada konfirmasi. Ulangi per DO jika ada beberapa order.',
     '02-kembalikan-do.png',
     'Syarat: masih tahap Surat Jalan, belum ditagihkan atau masuk item invoice, serta belum memiliki POD atau penutupan DO. Nomor dan riwayat tetap tersimpan.'),
    ('Memeriksa RDO yang dikembalikan',
     'Finance melanjutkan perbaikan dari halaman detail RDO',
     '<b>1.</b> Setelah pengembalian, aplikasi membuka detail RDO dengan tahap <b>Review Finance &amp; DP</b>. '
     '<b>2.</b> RDO muncul kembali pada daftar <b>Request Aktif</b>; DO sementara keluar dari daftar Delivery Orders. '
     '<b>3.</b> Periksa alasan pada <b>Riwayat Alur</b>, kode sektor, customer, dan rute sebelum memperbaiki harga.',
     '03-review-finance.png',
     'Approval harga sebelumnya dibatalkan. Pengembalian bukan penghapusan permanen; DO yang sama akan digunakan lagi saat disetujui ulang.'),
    ('Melengkapi harga dan biaya',
     'Dilakukan oleh Finance atau Admin termasuk Super Admin',
     '<b>1.</b> Pada <b>Rincian Biaya per Pekerjaan</b>, klik <b>Tambah</b> atau ikon pensil untuk rincian yang sudah ada. '
     '<b>2.</b> Pilih pekerjaan atau isi nama manual; periksa <b>Riil Biaya (HPP)</b>, <b>Riil Jual</b>, vendor, dan status bayar. '
     '<b>3.</b> Klik <b>Simpan</b> di bagian bawah form. Contoh angka pada gambar bukan tarif acuan.',
     '04-isi-biaya.png',
     'Lengkapi juga Item Layanan & Harga bila diperlukan. Setelah data benar, selesaikan Review Finance & DP dan klik Teruskan ke Sales Manager. Jika DP aktif, lengkapi status dan nominal DP.'),
    ('Menyetujui ulang dan menerbitkan DO',
     'Sales Manager atau Admin memeriksa hasil koreksi',
     '<b>1.</b> Buka RDO dengan tahap <b>Menunggu Approval</b>, lalu periksa nilai jual, HPP, rincian pekerjaan, dan DP. '
     '<b>2.</b> Setujui harga melalui <b>Approve DO</b> setelah nilainya benar. '
     '<b>3.</b> Pada <b>Approval Sales Manager</b>, klik <b>Setujui &amp; Terbitkan DO</b> dan konfirmasi. Jika belum benar, gunakan <b>Tolak</b> disertai catatan.',
     '05-approval-ulang.png',
     'DO kembali tampil dengan nomor dan riwayat yang sama, tanpa membuat DO kedua. Approval harga dan penerbitan DO adalah dua tindakan; harga harus disetujui sebelum DO dapat ditutup.'),
    ('Menangani peringatan RDO duplikat',
     'Berlaku saat membuat maupun menyimpan perubahan RDO',
     '<b>1.</b> Klik tombol simpan seperti biasa. Jika data cocok dengan order lain, aplikasi meminta konfirmasi. '
     '<b>2.</b> Pilih <b>Cancel / Batal</b> untuk memeriksa dan memperbaiki data; isian form tetap tersedia. '
     '<b>3.</b> Pilih <b>OK</b> hanya jika sudah dipastikan merupakan order terpisah yang sah.',
     '07-form-pemeriksaan-duplikat.png',
     'Screenshot menunjukkan form setelah peringatan dibatalkan. Teks dialog dan aturan pencocokan dijelaskan pada halaman berikutnya.'),
]

style = ParagraphStyle('body', fontName='Helvetica', fontSize=11, leading=15, textColor=HexColor('#20252b'))
caption = ParagraphStyle('caption', parent=style, fontSize=9, leading=12, textColor=HexColor('#4b5563'))
def paragraph(c, text, x, top, width, s=style):
    p = Paragraph(text, s)
    _, height = p.wrap(width, H)
    p.drawOn(c, x, top-height)
    return top-height

pdf = OUT/'Panduan-Revisi-RDO-DO.pdf'
c = canvas.Canvas(str(pdf), pagesize=(W,H))
c.setTitle('Panduan revisi RDO dan DO')
c.setAuthor('Panduan pengguna CRM')

def footer(n):
    c.setFillColor(HexColor('#64748b')); c.setFont('Helvetica',8)
    c.drawString(32,18,'FIRMAN TANGGUH LOGISTIK  |  Panduan pengguna  |  15 September 2026')
    c.drawRightString(W-32,18,f'{n} / 8')

for i,(title,sub,body,img,note) in enumerate(pages,1):
    c.setFillColor(HexColor('#111827')); c.setFont('Helvetica-Bold',20)
    c.drawString(32,H-35,title)
    c.setFont('Helvetica',10); c.setFillColor(HexColor('#475569'));c.drawString(32,H-53,sub)
    top = paragraph(c,body,32,H-68,W-64)-10
    # Keep the real screenshot intact; scale it proportionally to the available space.
    reader=ImageReader(str(SHOTS/img)); iw,ih=reader.getSize()
    bottom=70
    scale=min((W-64)/iw,(top-bottom)/ih)
    dw,dh=iw*scale,ih*scale
    c.drawImage(reader,(W-dw)/2,top-dh,dw,dh)
    paragraph(c,note,32,58,W-64,caption)
    footer(i); c.showPage()

c.setFillColor(HexColor('#111827')); c.setFont('Helvetica-Bold',20)
c.drawString(32,H-35,'Aturan duplikat dan kendala umum')
y=H-66
blocks=[
('Teks konfirmasi yang muncul',
 'Kemungkinan RDO duplikat: RDO-CONTOH-001. Periksa data dan konfirmasi sebelum menyimpan.<br/>Tetap simpan sebagai order terpisah?<br/><br/>Tombol: <b>Cancel</b> dan <b>OK</b>. Nama tombol mengikuti bahasa browser. Teks ini disalin dari dialog yang muncul saat pengujian; bukan gambar dialog.'),
('Kapan data dianggap berpotensi duplikat',
 'Customer dan tanggal order sama, lalu salah satu nomor <b>container</b>, <b>seal</b>, atau <b>tracking</b> sama; atau kombinasi <b>nomor polisi, lokasi muat, dan lokasi bongkar</b> sama. Huruf besar/kecil dan spasi diabaikan. Order yang dibatalkan atau dihapus tidak ikut diperiksa. Saat mengedit, order itu sendiri tidak dihitung sebagai duplikat.'),
('Tombol Kembalikan ke RDO tidak muncul',
 'Periksa peran akun, tahap Surat Jalan, dan status penagihan. DO yang sudah pickup atau lebih lanjut tidak memakai jalur pengembalian ini. Untuk koreksi harga pada DO yang masih belum ditagihkan, minta pengelola memeriksa jalur <b>Unapprove</b> di detail RDO sesuai kewenangan.'),
('Harga masih belum bisa diedit atau RDO tidak ditemukan',
 'Pastikan RDO sudah berada di tahap Review Finance &amp; DP dan akun memiliki akses Finance/Admin. Pada daftar Request DO, periksa rentang tanggal dan filter Request Aktif. Komponen yang sudah masuk invoice tidak dapat dikoreksi melalui alur ini.'),
('Simpan gagal atau koneksi terputus',
 'Baca pesan kesalahan dan periksa daftar order sebelum mencoba ulang. Jangan membuat order baru hanya karena halaman belum berpindah. Sistem memakai pengunci kirim ganda untuk menghindari pemrosesan ulang aksi yang sama.'),
]
for heading,body in blocks:
    c.setFillColor(HexColor('#111827'));c.setFont('Helvetica-Bold',12);c.drawString(32,y,heading)
    y=paragraph(c,body,32,y-9,W-64)-22
assert y>30,y
footer(8);c.save()

md=['# Panduan revisi RDO dan DO','', 'Panduan berdasarkan aplikasi lokal hasil revisi 15 September 2026. Semua order, perusahaan, kode, dan angka pada screenshot adalah data contoh.','']
import re
for title,sub,body,img,note in pages:
    md += ['## '+title,'',sub,'',re.sub('<[^>]+>','',body),'',f'![{title}](screenshots/{img})','',note,'']
md+=['## Aturan duplikat dan kendala umum','']
for heading,body in blocks:
    md += ['### '+heading,'',re.sub('<[^>]+>','',body.replace('<br/>','\n')),'']
(OUT/'Panduan-Revisi-RDO-DO.md').write_text('\n'.join(md),encoding='utf-8')
with zipfile.ZipFile(OUT/'Tutorial-RDO-DO-dan-Screenshot.zip','w',zipfile.ZIP_DEFLATED) as z:
    for f in [pdf,OUT/'Panduan-Revisi-RDO-DO.md',*sorted(SHOTS.glob('*.png'))]:
        z.write(f,f.relative_to(OUT))
print(pdf)
