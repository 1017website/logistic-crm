from pathlib import Path
from xml.sax.saxutils import escape

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    Flowable,
    Image,
    KeepTogether,
    PageBreak,
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)


ROOT = Path(r"D:\Project\1017website 2026\logistic-crm")
SHOT_DIR = ROOT / "tmp" / "pdfs" / "screenshots"
OUTPUT = ROOT / "output" / "pdf" / "Panduan_Pengguna_Logistic_CRM_Firman_Tangguh.pdf"
OUTPUT.parent.mkdir(parents=True, exist_ok=True)

PAGE_W, PAGE_H = landscape(A4)
NAVY = colors.HexColor("#172033")
BLUE = colors.HexColor("#1677FF")
LIGHT_BLUE = colors.HexColor("#EAF3FF")
CYAN = colors.HexColor("#22B8CF")
GREEN = colors.HexColor("#1FA971")
LIGHT_GREEN = colors.HexColor("#E9F8F1")
ORANGE = colors.HexColor("#F59F00")
RED = colors.HexColor("#E03131")
INK = colors.HexColor("#263238")
MUTED = colors.HexColor("#667085")
LINE = colors.HexColor("#D7DDE7")
PANEL = colors.HexColor("#F5F7FA")
WHITE = colors.white


def register_fonts():
    font_dir = Path(r"C:\Windows\Fonts")
    candidates = {
        "GuideSans": font_dir / "arial.ttf",
        "GuideSans-Bold": font_dir / "arialbd.ttf",
        "GuideSans-Italic": font_dir / "ariali.ttf",
    }
    for name, path in candidates.items():
        if path.exists():
            pdfmetrics.registerFont(TTFont(name, str(path)))
    if "GuideSans" not in pdfmetrics.getRegisteredFontNames():
        return "Helvetica", "Helvetica-Bold", "Helvetica-Oblique"
    return "GuideSans", "GuideSans-Bold", "GuideSans-Italic"


FONT, FONT_BOLD, FONT_ITALIC = register_fonts()

styles = getSampleStyleSheet()
styles.add(ParagraphStyle(
    name="GuideTitle", fontName=FONT_BOLD, fontSize=28, leading=32,
    textColor=WHITE, spaceAfter=8,
))
styles.add(ParagraphStyle(
    name="GuideSubtitle", fontName=FONT, fontSize=13, leading=18,
    textColor=colors.HexColor("#D7E8FF"),
))
styles.add(ParagraphStyle(
    name="Section", fontName=FONT_BOLD, fontSize=18, leading=22,
    textColor=NAVY, spaceAfter=5,
))
styles.add(ParagraphStyle(
    name="SectionWhite", fontName=FONT_BOLD, fontSize=18, leading=22,
    textColor=WHITE, spaceAfter=5,
))
styles.add(ParagraphStyle(
    name="Subsection", fontName=FONT_BOLD, fontSize=11, leading=14,
    textColor=NAVY, spaceBefore=3, spaceAfter=3,
))
styles.add(ParagraphStyle(
    name="BodyGuide", fontName=FONT, fontSize=8.5, leading=11.2,
    textColor=INK, spaceAfter=4,
))
styles.add(ParagraphStyle(
    name="Small", fontName=FONT, fontSize=7.2, leading=9.2,
    textColor=MUTED,
))
styles.add(ParagraphStyle(
    name="SmallDark", fontName=FONT, fontSize=7.4, leading=9.5,
    textColor=INK,
))
styles.add(ParagraphStyle(
    name="Tiny", fontName=FONT, fontSize=6.4, leading=8,
    textColor=INK,
))
styles.add(ParagraphStyle(
    name="TableHead", fontName=FONT_BOLD, fontSize=6.7, leading=8,
    textColor=WHITE, alignment=TA_CENTER,
))
styles.add(ParagraphStyle(
    name="TableCell", fontName=FONT, fontSize=6.6, leading=8.2,
    textColor=INK,
))
styles.add(ParagraphStyle(
    name="TableCellCenter", fontName=FONT_BOLD, fontSize=6.5, leading=8,
    textColor=INK, alignment=TA_CENTER,
))
styles.add(ParagraphStyle(
    name="Caption", fontName=FONT_ITALIC, fontSize=6.7, leading=8.5,
    textColor=MUTED, alignment=TA_CENTER,
))
styles.add(ParagraphStyle(
    name="Callout", fontName=FONT, fontSize=8, leading=10.5,
    textColor=INK,
))
styles.add(ParagraphStyle(
    name="RoleTitle", fontName=FONT_BOLD, fontSize=13, leading=16,
    textColor=WHITE,
))


def P(text, style="BodyGuide"):
    return Paragraph(text, styles[style])


def bullets(items, style="BodyGuide", color=BLUE):
    rows = []
    for item in items:
        rows.append([
            Paragraph("&#9679;", ParagraphStyle(
                "dot", parent=styles[style], textColor=color, alignment=TA_CENTER
            )),
            P(item, style),
        ])
    tbl = Table(rows, colWidths=[4 * mm, None], hAlign="LEFT")
    tbl.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ("RIGHTPADDING", (0, 0), (-1, -1), 2),
        ("TOPPADDING", (0, 0), (-1, -1), 0),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 1.2),
    ]))
    return tbl


def section_title(number, title, subtitle=None):
    data = [[
        Table([[P(str(number), "RoleTitle")]], colWidths=[12 * mm], rowHeights=[12 * mm], style=TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), BLUE),
            ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
            ("ALIGN", (0, 0), (-1, -1), "CENTER"),
        ])),
        P(title, "Section"),
    ]]
    t = Table(data, colWidths=[15 * mm, 250 * mm])
    t.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ("RIGHTPADDING", (0, 0), (-1, -1), 4),
        ("TOPPADDING", (0, 0), (-1, -1), 0),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
    ]))
    result = [t, Spacer(1, 2 * mm)]
    if subtitle:
        result += [P(subtitle, "Small"), Spacer(1, 2 * mm)]
    return result


def card(title, body, width, accent=BLUE, title_style="Subsection"):
    content = [P(title, title_style)]
    if isinstance(body, list):
        content.append(bullets(body, "SmallDark", accent))
    else:
        content.append(P(body, "SmallDark"))
    tbl = Table([[content]], colWidths=[width])
    tbl.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), WHITE),
        ("BOX", (0, 0), (-1, -1), 0.7, LINE),
        ("LINEBEFORE", (0, 0), (0, -1), 3, accent),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("RIGHTPADDING", (0, 0), (-1, -1), 7),
        ("TOPPADDING", (0, 0), (-1, -1), 6),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
    ]))
    return tbl


def screenshot(filename, caption, width=246 * mm):
    path = SHOT_DIR / filename
    img = Image(str(path))
    img._restrictSize(width, 430)
    frame = Table([[img], [P(caption, "Caption")]], colWidths=[width])
    frame.setStyle(TableStyle([
        ("BOX", (0, 0), (-1, 0), 0.8, LINE),
        ("BACKGROUND", (0, 1), (-1, 1), PANEL),
        ("LEFTPADDING", (0, 0), (-1, -1), 4),
        ("RIGHTPADDING", (0, 0), (-1, -1), 4),
        ("TOPPADDING", (0, 0), (-1, 0), 4),
        ("BOTTOMPADDING", (0, 0), (-1, 0), 4),
        ("TOPPADDING", (0, 1), (-1, 1), 3),
        ("BOTTOMPADDING", (0, 1), (-1, 1), 3),
    ]))
    return frame


def screenshot_pair(left, right, captions):
    width = 122 * mm
    return Table([[screenshot(left, captions[0], width), screenshot(right, captions[1], width)]],
                 colWidths=[126 * mm, 126 * mm],
                 style=TableStyle([
                     ("VALIGN", (0, 0), (-1, -1), "TOP"),
                     ("LEFTPADDING", (0, 0), (-1, -1), 0),
                     ("RIGHTPADDING", (0, 0), (-1, -1), 4),
                 ]))


def flow_steps(items, widths=None, accent=BLUE):
    if widths is None:
        usable = 250 * mm - (len(items) - 1) * 6 * mm
        widths = [usable / len(items)] * len(items)
    cells = []
    col_widths = []
    for i, (title, detail) in enumerate(items):
        bg = LIGHT_BLUE if i % 2 == 0 else colors.HexColor("#F3F8FF")
        block = Table([[P(title, "Subsection")], [P(detail, "Tiny")]], colWidths=[widths[i]])
        block.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), bg),
            ("BOX", (0, 0), (-1, -1), 0.7, accent),
            ("LEFTPADDING", (0, 0), (-1, -1), 5),
            ("RIGHTPADDING", (0, 0), (-1, -1), 5),
            ("TOPPADDING", (0, 0), (-1, -1), 4),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
        ]))
        cells.append(block)
        col_widths.append(widths[i])
        if i < len(items) - 1:
            cells.append(P("&#8594;", "Subsection"))
            col_widths.append(6 * mm)
    tbl = Table([cells], colWidths=col_widths)
    tbl.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("ALIGN", (0, 0), (-1, -1), "CENTER"),
        ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ("RIGHTPADDING", (0, 0), (-1, -1), 0),
    ]))
    return tbl


def role_page(story, number, role, purpose, menus, actions, handoff, output, accent):
    story.extend(section_title(number, f"Panduan Role: {role}", purpose))
    w = 81 * mm
    row1 = Table([[
        card("Menu utama", menus, w, accent),
        card("Form & tindakan", actions, w, accent),
        card("Serah-terima pekerjaan", handoff, w, accent),
    ]], colWidths=[84 * mm] * 3)
    row1.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ("RIGHTPADDING", (0, 0), (-1, -1), 3),
    ]))
    story += [row1, Spacer(1, 4 * mm)]
    story.append(Table([[
        card("Output yang dihasilkan", output, 165 * mm, GREEN),
        card("Kontrol penting", [
            "Pastikan data wajib dan lampiran sudah benar sebelum mengubah status.",
            "Gunakan catatan/reason saat menolak, menjadwalkan ulang, atau membatalkan.",
            "Hindari klik tombol simpan berulang ketika proses masih berjalan.",
        ], 81 * mm, ORANGE),
    ]], colWidths=[168 * mm, 84 * mm], style=TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ("RIGHTPADDING", (0, 0), (-1, -1), 3),
    ])))
    story += [Spacer(1, 4 * mm), P(
        "Catatan: hak akses mengikuti konfigurasi role. Jika menu tidak tampil, pastikan akun aktif dan role pengguna sudah sesuai.",
        "Small"
    ), PageBreak()]


def header_footer(canvas, doc):
    canvas.saveState()
    if doc.page > 1:
        canvas.setStrokeColor(LINE)
        canvas.setLineWidth(0.5)
        canvas.line(16 * mm, PAGE_H - 12 * mm, PAGE_W - 16 * mm, PAGE_H - 12 * mm)
        canvas.setFont(FONT_BOLD, 7.2)
        canvas.setFillColor(NAVY)
        canvas.drawString(16 * mm, PAGE_H - 9.2 * mm, "LOGISTIC CRM - PANDUAN PENGGUNA")
        canvas.setFont(FONT, 6.8)
        canvas.setFillColor(MUTED)
        canvas.drawRightString(PAGE_W - 16 * mm, PAGE_H - 9.2 * mm, "PT Firman Tangguh Logistik")
        canvas.line(16 * mm, 10 * mm, PAGE_W - 16 * mm, 10 * mm)
        canvas.drawString(16 * mm, 6.8 * mm, "Versi 1.0 - 2 September 2026")
        canvas.drawRightString(PAGE_W - 16 * mm, 6.8 * mm, f"Halaman {doc.page}")
    canvas.restoreState()


doc = SimpleDocTemplate(
    str(OUTPUT),
    pagesize=landscape(A4),
    leftMargin=16 * mm,
    rightMargin=16 * mm,
    topMargin=16 * mm,
    bottomMargin=14 * mm,
    title="Panduan Pengguna Logistic CRM - PT Firman Tangguh Logistik",
    author="PT Firman Tangguh Logistik",
    subject="Panduan role, form, flow, dan output Logistic CRM",
)

story = []

# Cover
cover_bg = Table([[
    [
        Spacer(1, 18 * mm),
        P("PANDUAN PENGGUNA", "GuideSubtitle"),
        P("Logistic CRM", "GuideTitle"),
        P("Role, form, alur kerja, dan output sistem", "GuideSubtitle"),
        Spacer(1, 12 * mm),
        Table([[
            P("PT FIRMAN TANGGUH LOGISTIK", "RoleTitle"),
            P("Versi 1.0  |  2 September 2026", "GuideSubtitle"),
        ]], colWidths=[110 * mm, 100 * mm], style=TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor("#25314A")),
            ("BOX", (0, 0), (-1, -1), 0.6, colors.HexColor("#4F6A99")),
            ("LEFTPADDING", (0, 0), (-1, -1), 8),
            ("RIGHTPADDING", (0, 0), (-1, -1), 8),
            ("TOPPADDING", (0, 0), (-1, -1), 8),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
            ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ])),
        Spacer(1, 9 * mm),
        P("Dokumen ini menjelaskan penggunaan aplikasi dari sisi setiap role, field utama pada form, alur persetujuan, serta hasil yang dihasilkan sistem.", "GuideSubtitle"),
    ]
]], colWidths=[PAGE_W - 32 * mm], rowHeights=[PAGE_H - 50 * mm])
cover_bg.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, -1), NAVY),
    ("LEFTPADDING", (0, 0), (-1, -1), 22 * mm),
    ("RIGHTPADDING", (0, 0), (-1, -1), 22 * mm),
    ("TOPPADDING", (0, 0), (-1, -1), 5 * mm),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 5 * mm),
    ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
]))
story += [cover_bg, PageBreak()]

# Contents and quick start
story.extend(section_title("A", "Cara Menggunakan Panduan", "Bacalah bagian ringkas terlebih dahulu, lalu lanjutkan ke role dan modul yang digunakan sehari-hari."))
toc_data = [
    ["Bagian", "Isi", "Fokus"],
    ["1-2", "Pengenalan & navigasi", "Login, dashboard, menu, istilah"],
    ["3", "Matriks hak akses", "Siapa dapat melakukan apa"],
    ["4-10", "Panduan per role", "Tanggung jawab, form, handoff, output"],
    ["11", "Flow end-to-end", "Lead sampai invoice lunas"],
    ["12-20", "Panduan per modul", "Langkah input, status, screenshot, hasil"],
    ["21-22", "SOP & troubleshooting", "Checklist dan penanganan kendala"],
]
toc = Table([[P(str(c), "TableHead") for c in toc_data[0]]] +
            [[P(str(c), "TableCell") for c in row] for row in toc_data[1:]],
            colWidths=[25 * mm, 90 * mm, 132 * mm])
toc.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, 0), NAVY),
    ("GRID", (0, 0), (-1, -1), 0.4, LINE),
    ("ROWBACKGROUNDS", (0, 1), (-1, -1), [WHITE, PANEL]),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 6),
    ("RIGHTPADDING", (0, 0), (-1, -1), 6),
    ("TOPPADDING", (0, 0), (-1, -1), 5),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
]))
story += [toc, Spacer(1, 5 * mm)]
story.append(Table([[
    card("Aturan penggunaan dasar", [
        "Gunakan akun sendiri; jangan berbagi password.",
        "Isi field bertanda bintang (*) dan periksa kembali sebelum simpan.",
        "Perubahan status adalah handoff ke role berikutnya; pastikan data sudah lengkap.",
        "Gunakan filter periode saat mencari data lama dan saat mengekspor laporan.",
    ], 122 * mm, BLUE),
    card("Arti warna umum", [
        "Biru: proses/review; hijau: aktif/selesai/disetujui.",
        "Kuning: menunggu tindakan; merah: ditolak/batal/bermasalah.",
        "Abu-abu: draft, belum ada data, atau informasi pendukung.",
    ], 122 * mm, GREEN),
]], colWidths=[126 * mm, 126 * mm], style=TableStyle([
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 0),
    ("RIGHTPADDING", (0, 0), (-1, -1), 3),
])))
story += [PageBreak()]

# Login and dashboard
story.extend(section_title(1, "Login, Dashboard, dan Navigasi", "Dashboard menampilkan ringkasan sesuai hak akses pengguna."))
story.append(screenshot_pair(
    "01-login.png", "02-dashboard-admin.png",
    ("Halaman login: masukkan email dan password.", "Dashboard Super Admin: KPI, grafik, aktivitas, dan quick action.")
))
story += [Spacer(1, 3 * mm)]
story.append(Table([[
    card("Login", [
        "Buka alamat aplikasi melalui browser yang disediakan perusahaan.",
        "Masukkan email dan password, lalu klik Login.",
        "Jika lupa akses, hubungi Super Admin/Admin untuk reset akun.",
    ], 81 * mm, BLUE),
    card("Navigasi", [
        "Menu kiri dikelompokkan menjadi Sales, Marketing, Analytics, dan System.",
        "Gunakan pencarian, filter, tab status, dan rentang tanggal untuk mempersempit data.",
        "Klik nomor dokumen atau ikon detail untuk membuka pekerjaan.",
    ], 81 * mm, CYAN),
    card("Dashboard", [
        "KPI yang terlihat mengikuti role: revenue, DO, leads, pipeline, aktivitas, dan reminder.",
        "Quick Action membuka form yang paling sering digunakan.",
        "Grafik adalah ringkasan; gunakan laporan untuk angka final per periode.",
    ], 81 * mm, GREEN),
]], colWidths=[84 * mm] * 3, style=TableStyle([
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 0),
    ("RIGHTPADDING", (0, 0), (-1, -1), 3),
])))
story += [PageBreak()]

# Access matrix
story.extend(section_title(2, "Matriks Hak Akses per Role", "Ringkasan berikut menunjukkan akses utama. Akses detail tetap mengikuti konfigurasi sistem."))
roles = ["Super Admin", "Admin", "Sales Manager", "Sales Executive", "Sales Admin", "Transport Planner", "Finance"]
modules = [
    ("Dashboard", [1, 1, 1, 1, 1, 1, 1]),
    ("Leads / Pipeline", [1, 1, 1, 1, 0, 1, 0]),
    ("Penawaran", [1, 1, 1, 1, 1, 0, 0]),
    ("Customer", [1, 1, 1, 1, 1, 1, 1]),
    ("Vendor", [1, 1, 1, 0, 1, 1, 1]),
    ("Request DO", [1, 1, 1, 1, 1, 1, 1]),
    ("Verifikasi Request", [1, 1, 0, 0, 1, 0, 0]),
    ("Harga / Job Cost", [1, 1, 0, 0, 0, 0, 1]),
    ("Dispatch", [1, 1, 0, 0, 1, 1, 0]),
    ("Approval Assignment", [1, 1, 1, 0, 0, 0, 0]),
    ("Delivery Order", [1, 1, 1, 0, 1, 1, 1]),
    ("Surat Jalan / POD", [1, 1, 0, 0, 1, 0, 0]),
    ("Invoice / Pembayaran", [1, 1, 1, 0, 0, 0, 1]),
    ("Reports / Analytics", [1, 1, 1, 0, 0, 0, 1]),
    ("Users / Settings", [1, 1, 1, 0, 0, 0, 0]),
]
matrix = [[P("Modul / Tindakan", "TableHead")] + [P(r, "TableHead") for r in roles]]
for module, flags in modules:
    matrix.append([P(module, "TableCell")] + [P("YA" if f else "-", "TableCellCenter") for f in flags])
matrix_tbl = Table(matrix, colWidths=[49 * mm] + [28.5 * mm] * 7, repeatRows=1)
matrix_tbl.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, 0), NAVY),
    ("GRID", (0, 0), (-1, -1), 0.35, LINE),
    ("ROWBACKGROUNDS", (0, 1), (-1, -1), [WHITE, PANEL]),
    ("TEXTCOLOR", (1, 1), (-1, -1), GREEN),
    ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
    ("ALIGN", (1, 1), (-1, -1), "CENTER"),
    ("LEFTPADDING", (0, 0), (-1, -1), 4),
    ("RIGHTPADDING", (0, 0), (-1, -1), 4),
    ("TOPPADDING", (0, 0), (-1, -1), 3.7),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 3.7),
]))
story += [matrix_tbl, Spacer(1, 3 * mm), P(
    "Keterangan: Super Admin memiliki akses penuh. Admin memiliki akses operasional luas, tetapi persetujuan edit invoice tertentu tetap menjadi kewenangan Super Admin. Sales Manager dapat melihat invoice dan laporan, sedangkan transaksi pembayaran dilakukan Finance.",
    "Small"
), PageBreak()]

# Role pages
role_page(story, 3, "Super Admin",
          "Pemilik kontrol tertinggi sistem, data master, persetujuan khusus, dan konfigurasi aplikasi.",
          ["Seluruh menu aplikasi.", "Users, Settings, Permintaan Hapus.", "Analytics, Reports, Laporan Logistik."],
          ["Membuat/mengubah akun dan role.", "Mengatur identitas perusahaan, logo, TOP, format tanggal, mata uang.", "Menyetujui/menolak permintaan edit invoice Finance.", "Menangani persetujuan penghapusan data."],
          ["Menerima eskalasi akses, penghapusan, dan edit invoice.", "Memastikan konfigurasi tidak mengganggu proses berjalan.", "Mengembalikan pekerjaan ke role terkait setelah review."],
          ["Akun aktif dengan role yang benar.", "Konfigurasi dokumen dan perusahaan yang konsisten.", "Audit trail keputusan edit/penghapusan."], NAVY)

role_page(story, 4, "Admin",
          "Administrator operasional dengan akses lintas modul untuk membantu proses harian dan pelaporan.",
          ["Sales, Marketing, Analytics, dan sebagian System.", "Request DO, Delivery Order, Invoice.", "Users, Reports, Laporan Logistik."],
          ["Membantu verifikasi, harga, dispatch, approval, dan operasional DO.", "Mengelola customer, vendor, service type, pekerjaan.", "Mengelola laporan dan pengguna."],
          ["Menjaga antrian pekerjaan tidak terhenti.", "Meneruskan edit invoice sensitif ke Super Admin.", "Mencatat alasan saat koreksi, penolakan, atau pembatalan."],
          ["Data master yang siap dipakai.", "Request/DO bergerak ke tahap berikutnya.", "Laporan dan kontrol operasional lintas role."], BLUE)

role_page(story, 5, "Sales Manager",
          "Mengawasi pipeline penjualan serta menyetujui assignment dan harga jual pekerjaan.",
          ["Leads, Pipeline, Penawaran, Customer/Vendor.", "Request DO dan Delivery Order (monitoring).", "Invoice (view), Analytics, Reports, Laporan Logistik."],
          ["Review pipeline dan target sales.", "Approve/reject assignment armada/vendor.", "Approve/reject harga DO berdasarkan jual vs HPP.", "Melihat invoice dan laporan performa."],
          ["Menerima request yang sudah lengkap dari Sales Admin/Transport Planner/Finance.", "Jika ditolak, wajib beri alasan agar role sebelumnya dapat memperbaiki."],
          ["Assignment disetujui sehingga DO terbit.", "Harga DO disetujui untuk dasar invoice.", "Keputusan sales tercatat dan terukur."], GREEN)

role_page(story, 6, "Sales Executive",
          "Mengelola prospek, aktivitas penjualan, penawaran, dan membuat kebutuhan pengiriman awal.",
          ["Sales Activity, Leads, Pipeline, Penawaran.", "Calendar, Tasks/Reminder, Customer.", "Request DO sesuai kewenangan."],
          ["Tambah/update lead dan tahap pipeline.", "Catat aktivitas/follow-up dan potensi revenue.", "Buat penawaran.", "Buat Request DO dengan customer, rute, jadwal, dan detail pekerjaan."],
          ["Serahkan Request DO kepada Sales Admin untuk verifikasi.", "Pastikan kebutuhan customer, lokasi muat/bongkar, container/seal, dan jadwal jelas."],
          ["Lead terukur hingga Won/Closing.", "Penawaran siap dikirim ke customer.", "Request DO lengkap untuk diproses operasional."], CYAN)

role_page(story, 7, "Sales Admin",
          "Gerbang verifikasi Request DO dan pelaksana administrasi Delivery Order di lapangan.",
          ["Penawaran, Customer, Vendor.", "Request DO dan Delivery Order.", "Calendar dan Tasks/Reminder."],
          ["Verifikasi Request DO.", "Dispatch bila ditugaskan.", "Cetak/upload surat jalan, tandai pickup dan delivered.", "Upload serta verifikasi POD, input biaya aktual, lalu close DO."],
          ["Request valid diteruskan ke Finance & DP.", "Setelah DO berjalan, update status dan lampiran tepat waktu.", "Close hanya setelah POD dan biaya aktual lengkap."],
          ["Request terverifikasi.", "Surat jalan dan POD terdokumentasi.", "DO closed yang otomatis menyiapkan draft invoice."], ORANGE)

role_page(story, 8, "Transport Planner",
          "Merencanakan armada/vendor, driver, dan estimasi biaya pelaksanaan pengiriman.",
          ["Request DO dan Delivery Order (monitoring).", "Customer, Vendor, Service Type.", "Leads/Pipeline dan Master Pekerjaan sesuai akses."],
          ["Pilih armada internal atau vendor eksternal.", "Isi kendaraan, nomor polisi, driver, kontak, dan estimasi biaya.", "Dispatch Request DO dan perbarui status operasional bila diperlukan."],
          ["Menerima request setelah review Finance/DP.", "Meneruskan assignment lengkap ke Sales Manager/Admin untuk approval."],
          ["Rencana transportasi yang dapat dieksekusi.", "Estimasi HPP/biaya vendor.", "Assignment siap disetujui dan diterbitkan menjadi DO."], BLUE)

role_page(story, 9, "Finance",
          "Melengkapi harga dan HPP, melakukan review DP, membuat invoice, serta mencatat pembayaran.",
          ["Request DO dan Delivery Order (view).", "Item layanan, job cost, DP review.", "Invoice, pembayaran, dan Laporan Logistik."],
          ["Isi layanan, harga beli/jual, serta rincian biaya pekerjaan.", "Tentukan status dan nilai DP.", "Terbitkan invoice dengan periode, PPN, TOP/due date.", "Catat pembayaran termin/lunas dan ajukan edit bila perlu."],
          ["Menerima request terverifikasi Sales Admin.", "Meneruskan request selesai review ke Transport Planner.", "Invoice diterbitkan setelah DO closed, POD ada, dan harga disetujui."],
          ["Revenue, HPP, GP, dan margin yang valid.", "Invoice TR/NTR dan laporan sesuai periode.", "Outstanding, aging, dan pembayaran yang terbarui."], GREEN)

# End-to-end flow
story.extend(section_title(10, "Flow End-to-End: Lead hingga Invoice Lunas", "Setiap perubahan status berarti pekerjaan berpindah ke role berikutnya."))
story.append(flow_steps([
    ("1. Lead", "Sales Executive mencatat prospek dan aktivitas."),
    ("2. Penawaran", "Harga/ruang lingkup dikirim ke customer."),
    ("3. Request DO", "Kebutuhan pengiriman dan detail pekerjaan dibuat."),
    ("4. Verifikasi", "Sales Admin memeriksa kelengkapan."),
    ("5. Finance & DP", "Finance mengisi layanan, harga, HPP, dan DP."),
], accent=BLUE))
story += [Spacer(1, 4 * mm)]
story.append(flow_steps([
    ("6. Dispatch", "Transport Planner memilih armada/vendor dan driver."),
    ("7. Approval", "Sales Manager/Admin menyetujui assignment dan harga."),
    ("8. Delivery", "Surat jalan, pickup, delivered, dan POD di-update."),
    ("9. Close DO", "Biaya aktual dilengkapi; draft invoice terbentuk."),
    ("10. Invoice", "Finance publish, mencatat termin, lalu lunas."),
], accent=GREEN))
story += [Spacer(1, 6 * mm)]
status_data = [
    ["Dokumen", "Status utama", "Penanggung jawab dominan", "Output"],
    ["Lead", "Identifying -> Approaching -> Follow Up -> Won/Closing -> Maintaining", "Sales Executive / Sales Manager", "Pipeline dan customer"],
    ["Request DO", "Draft -> Verifikasi -> Finance & DP -> Dispatch -> Approval -> Assigned", "Sales Admin / Finance / Planner / Manager", "Delivery Order terbit"],
    ["Delivery Order", "Surat Jalan -> Pickup -> In Delivery -> POD -> Verifikasi POD -> Closed", "Sales Admin / Admin", "POD dan draft invoice"],
    ["Invoice", "Draft -> Invoice/Submitted -> Termin atau Paid", "Finance", "Tagihan, outstanding, pembayaran"],
]
status_tbl = Table([[P(c, "TableHead") for c in status_data[0]]] +
                   [[P(c, "TableCell") for c in row] for row in status_data[1:]],
                   colWidths=[35 * mm, 95 * mm, 72 * mm, 47 * mm])
status_tbl.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, 0), NAVY),
    ("GRID", (0, 0), (-1, -1), 0.4, LINE),
    ("ROWBACKGROUNDS", (0, 1), (-1, -1), [WHITE, PANEL]),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 5),
    ("RIGHTPADDING", (0, 0), (-1, -1), 5),
    ("TOPPADDING", (0, 0), (-1, -1), 5),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
]))
story += [status_tbl, PageBreak()]

# Leads & pipeline
story.extend(section_title(11, "Lead, Sales Activity, dan Pipeline", "Tujuan: mengubah prospek menjadi pekerjaan yang dapat ditindaklanjuti."))
story.append(screenshot_pair(
    "04-form-tambah-lead.png", "20-pipeline.png",
    ("Form Tambah Lead.", "Pipeline memperlihatkan posisi prospek per tahap.")
))
story += [Spacer(1, 3 * mm)]
story.append(Table([[
    card("Input form", ["Perusahaan, industri, lokasi/alamat.", "Nama/jabatan PIC, telepon, email.", "Sumber lead, estimasi closing, potensi revenue, Sales PIC."], 81 * mm, BLUE),
    card("Flow", ["Tambah Lead.", "Catat aktivitas/follow-up.", "Perbarui tahap: Identifying hingga Maintaining.", "Saat Won/Closing, lanjutkan penawaran/request."], 81 * mm, CYAN),
    card("Output", ["Daftar lead dan histori aktivitas.", "Nilai pipeline dan forecast.", "Reminder follow-up dan customer Existing/Potential."], 81 * mm, GREEN),
]], colWidths=[84 * mm] * 3, style=TableStyle([
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 0),
    ("RIGHTPADDING", (0, 0), (-1, -1), 3),
])))
story += [PageBreak()]

# Customer vendor
story.extend(section_title(12, "Database Customer dan Vendor", "Data master yang benar mencegah pengulangan input di transaksi berikutnya."))
story.append(screenshot_pair(
    "16-customers.png", "18-form-vendor.png",
    ("Daftar customer dengan filter, import, dan export.", "Form vendor: identitas, layanan, PIC, term, rating, dan alamat.")
))
story += [Spacer(1, 3 * mm)]
story.append(Table([[
    card("Customer", ["Nama perusahaan dan PIC/kontak.", "Status Potential/Existing, Sales PIC, TOP hari.", "Alamat dan kebutuhan administrasi.", "Dapat import/export; transfer sales sesuai akses."], 122 * mm, BLUE),
    card("Vendor", ["Nama/jenis vendor, service type/mode.", "Payment term, PIC, kontak, rating, status, relationship.", "Alamat dan daftar layanan vendor.", "Dapat import/export untuk pemeliharaan massal."], 122 * mm, GREEN),
]], colWidths=[126 * mm, 126 * mm], style=TableStyle([
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 0),
    ("RIGHTPADDING", (0, 0), (-1, -1), 3),
])))
story += [PageBreak()]

# Quotation
story.extend(section_title(13, "Penawaran", "Penawaran dibuat dari database customer dan dapat dipreview sebelum menjadi PDF."))
story.append(screenshot("19-form-penawaran.png", "Form Penawaran: identitas dokumen, penerima, isi penawaran, baris harga, penutup, dan penandatangan.", width=180 * mm))
story += [Spacer(1, 3 * mm)]
story.append(Table([[
    card("Field utama", ["Customer, nomor/tanggal, status.", "Penerima, alamat, subject/lampiran.", "Pembuka, baris layanan/harga, penutup.", "Kota dan penandatangan."], 81 * mm, BLUE),
    card("Flow", ["Buat penawaran dari customer.", "Lengkapi isi dan harga.", "Preview dokumen.", "Simpan dan unduh/cetak PDF untuk customer."], 81 * mm, CYAN),
    card("Output", ["Dokumen penawaran bernomor.", "PDF siap kirim.", "Histori penawaran per customer dan status."], 81 * mm, GREEN),
]], colWidths=[84 * mm] * 3, style=TableStyle([
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 0),
    ("RIGHTPADDING", (0, 0), (-1, -1), 3),
])))
story += [PageBreak()]

# Request DO form
story.extend(section_title(14, "Request DO - Membuat Permintaan", "Request DO mengumpulkan kebutuhan komersial dan operasional sebelum Finance dan Transport Planner bekerja."))
story.append(screenshot("06-form-request-do.png", "Form Tambah Request DO. Isi bertahap mulai customer hingga detail operasional.", width=180 * mm))
story += [Spacer(1, 2.5 * mm)]
request_form = [
    ["Bagian", "Field utama", "Catatan pengisian"],
    ["1. Customer & assignment", "Customer*, Sales PIC*, tanggal order*, currency, linked lead, catatan", "Gunakan customer dan Sales PIC yang benar karena menjadi dasar ownership."],
    ["2. Rute & jadwal", "Service type, origin, destination, ETA, tracking", "Tulis lokasi secara spesifik agar dapat dipakai Finance dan operasional."],
    ["3. Detail pekerjaan", "Checker, truck type, no. polisi, komoditas, depot, lokasi/tanggal muat-bongkar, container, seal, grade, driver dan kontak", "Lengkapi sebanyak mungkin; data ini tampil di detail Request DO dan Delivery Order."],
]
rf_tbl = Table([[P(c, "TableHead") for c in request_form[0]]] +
               [[P(c, "TableCell") for c in row] for row in request_form[1:]],
               colWidths=[50 * mm, 105 * mm, 94 * mm])
rf_tbl.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, 0), NAVY),
    ("GRID", (0, 0), (-1, -1), 0.4, LINE),
    ("ROWBACKGROUNDS", (0, 1), (-1, -1), [WHITE, PANEL]),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 5),
    ("RIGHTPADDING", (0, 0), (-1, -1), 5),
    ("TOPPADDING", (0, 0), (-1, -1), 4),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
]))
story += [rf_tbl, PageBreak()]

# Request DO workflow
story.extend(section_title(15, "Request DO - Review, Harga, dan Approval", "Daftar utama menampilkan data operasional; revenue/HPP/GP/margin tersedia pada detail item."))
story.append(screenshot_pair(
    "05-request-do-list.png", "07-request-do-detail-flow.png",
    ("Daftar Request DO dengan filter periode dan status.", "Detail Request DO menunjukkan status flow serta informasi pekerjaan.")
))
story += [Spacer(1, 3 * mm)]
story.append(flow_steps([
    ("Sales Admin", "Verifikasi data dan teruskan."),
    ("Finance", "Isi layanan, jual, HPP, job cost, dan DP."),
    ("Planner", "Pilih armada/vendor, kendaraan, driver, estimasi."),
    ("Manager/Admin", "Approve/reject assignment dan harga."),
    ("Sistem", "Membuat Delivery Order."),
], accent=BLUE))
story += [Spacer(1, 3 * mm)]
story.append(Table([[
    card("Informasi tabel utama", ["Lokasi muat.", "Lokasi bongkar.", "Nomor container.", "Nomor seal."], 81 * mm, CYAN),
    card("Informasi sub-tabel", ["Layanan, satuan, tonase, qty.", "Harga beli dan harga jual.", "Subtotal revenue, HPP, GP, margin."], 81 * mm, GREEN),
    card("Koreksi & status", ["Penolakan wajib disertai alasan.", "Operational status: running, pending, rescheduled, cancelled.", "Request dapat dibatalkan/diaktifkan kembali sesuai akses."], 81 * mm, ORANGE),
]], colWidths=[84 * mm] * 3, style=TableStyle([
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 0),
    ("RIGHTPADDING", (0, 0), (-1, -1), 3),
])))
story += [PageBreak()]

# DO
story.extend(section_title(16, "Delivery Order - Pelaksanaan dan POD", "Delivery Order terbit setelah assignment disetujui dan menjadi pusat administrasi pengiriman."))
story.append(screenshot_pair(
    "08-delivery-orders-list.png", "09-delivery-order-detail.png",
    ("Daftar Delivery Order dan status lapangan.", "Detail DO memuat armada/vendor, driver, rute, container, seal, dan milestone.")
))
story += [Spacer(1, 3 * mm)]
story.append(flow_steps([
    ("Surat Jalan", "Internal: cetak sistem. Eksternal: upload vendor."),
    ("Pickup", "Isi tanggal pickup/keberangkatan."),
    ("Delivered", "Isi tanggal tiba/selesai bongkar."),
    ("POD", "Upload PDF/foto, maksimal 5 MB."),
    ("Close", "Verifikasi POD, biaya aktual, lalu tutup DO."),
], accent=GREEN))
story += [Spacer(1, 3 * mm)]
story.append(Table([[
    card("Lampiran wajib", ["Surat jalan sesuai tipe armada.", "POD yang dapat dibaca dan sesuai nomor DO.", "Catatan kejadian bila ada selisih/kendala."], 122 * mm, BLUE),
    card("Output", ["Riwayat milestone pengiriman.", "Planned HPP vs actual HPP dan variance.", "DO Closed yang otomatis membuat draft invoice."], 122 * mm, GREEN),
]], colWidths=[126 * mm, 126 * mm], style=TableStyle([
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 0),
    ("RIGHTPADDING", (0, 0), (-1, -1), 3),
])))
story += [PageBreak()]

# Invoice list and detail
story.extend(section_title(17, "Invoice - Draft, Publish, dan Pembayaran", "Invoice berasal dari DO Closed dengan POD dan harga yang telah disetujui."))
story.append(screenshot_pair(
    "10-invoice-list-period.png", "12-invoice-detail.png",
    ("Daftar invoice dengan filter periode dan status pembayaran.", "Detail invoice: periode, tanggal submit, jatuh tempo, item, PPN, dan pembayaran.")
))
story += [Spacer(1, 3 * mm)]
story.append(flow_steps([
    ("Draft otomatis", "Komponen TR dan NTR tetap terpisah."),
    ("Review", "Pilih DO, periode, PPN, TOP/due date."),
    ("Publish", "Nomor final dan submitted_at tercatat."),
    ("Termin", "Catat pembayaran parsial dan sisa."),
    ("Paid", "Pelunasan menutup outstanding."),
], accent=GREEN))
story += [Spacer(1, 3 * mm)]
story.append(Table([[
    card("Field invoice", ["Customer dan komponen DO.", "Periode invoice, tanggal pembuatan, TOP/jatuh tempo.", "Tipe TR/NTR, PPN 11% atau 1,1%, catatan."], 122 * mm, BLUE),
    card("Edit terkontrol", ["Finance mengajukan permintaan edit.", "Super Admin approve/reject.", "Finance melakukan koreksi lalu Finish & Lock.", "Semua perubahan mempertahankan jejak keputusan."], 122 * mm, ORANGE),
]], colWidths=[126 * mm, 126 * mm], style=TableStyle([
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 0),
    ("RIGHTPADDING", (0, 0), (-1, -1), 3),
])))
story += [PageBreak()]

# Invoice period/report
story.extend(section_title(18, "Periode Invoice, TOP, dan Laporan", "Periode laporan dapat berbeda dari tanggal invoice dipublikasikan."))
story.append(screenshot_pair(
    "11-invoice-expanded.png", "13-laporan-invoice.png",
    ("Bundle invoice customer dapat diperluas untuk melihat komponen.", "Laporan invoice menggunakan filter periode dan status.")
))
story += [Spacer(1, 3 * mm)]
example = Table([[
    P("CONTOH", "RoleTitle"),
    P("Pekerjaan Agustus baru lengkap dan invoice dipublikasikan pada 10 September. Finance memilih <b>Periode Agustus</b>; tanggal submit tetap 10 September. Jika TOP 30 hari dan due date tidak diubah manual, jatuh tempo dihitung dari tanggal publish.", "Callout")
]], colWidths=[28 * mm, 218 * mm])
example.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (0, 0), BLUE),
    ("BACKGROUND", (1, 0), (1, 0), LIGHT_BLUE),
    ("BOX", (0, 0), (-1, -1), 0.7, BLUE),
    ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
    ("LEFTPADDING", (0, 0), (-1, -1), 8),
    ("RIGHTPADDING", (0, 0), (-1, -1), 8),
    ("TOPPADDING", (0, 0), (-1, -1), 7),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
]))
story += [example, Spacer(1, 3 * mm)]
story.append(Table([[
    card("Periode", ["Menentukan bulan laporan/accounting.", "Dipakai oleh filter dan export.", "Tidak mengubah tanggal submit aktual."], 81 * mm, BLUE),
    card("TOP & aging", ["TOP otomatis dari tanggal publish bila tidak diubah manual.", "Aging/outstanding memakai submitted_at dan due date."], 81 * mm, ORANGE),
    card("Output laporan", ["Invoice per periode.", "Paid/unpaid dan outstanding.", "Aging bucket serta export PDF/Excel."], 81 * mm, GREEN),
]], colWidths=[84 * mm] * 3, style=TableStyle([
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 0),
    ("RIGHTPADDING", (0, 0), (-1, -1), 3),
])))
story += [PageBreak()]

# Reports
story.extend(section_title(19, "Analytics dan Laporan Logistik", "Gunakan filter yang konsisten sebelum membandingkan atau mengekspor angka."))
story.append(screenshot("21-laporan-logistik.png", "Laporan Logistik merangkum kinerja DO, invoice, outstanding, dan biaya berdasarkan periode.", width=180 * mm))
story += [Spacer(1, 3 * mm)]
story.append(Table([[
    card("Sebelum melihat laporan", ["Pilih tanggal awal dan akhir/periode.", "Pilih status, Sales PIC, customer, atau filter lain jika tersedia.", "Pastikan DO/Invoice sudah berada pada status yang benar."], 81 * mm, BLUE),
    card("Interpretasi angka", ["Revenue berasal dari harga jual.", "HPP dapat terdiri dari estimasi dan aktual.", "Gross Profit = Revenue - HPP; Margin = GP / Revenue."], 81 * mm, CYAN),
    card("Output", ["Ringkasan performa DO dan invoice.", "Planned vs actual cost dan variance.", "Export laporan untuk review manajemen/Finance."], 81 * mm, GREEN),
]], colWidths=[84 * mm] * 3, style=TableStyle([
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 0),
    ("RIGHTPADDING", (0, 0), (-1, -1), 3),
])))
story += [PageBreak()]

# Users and settings
story.extend(section_title(20, "Users, Roles, dan Settings", "Bagian ini dikelola oleh Super Admin/Admin untuk menjaga kontrol akses dan identitas dokumen."))
story.append(screenshot_pair(
    "14-users-roles.png", "15-settings.png",
    ("Users: akun, role, status, dan target.", "Settings: identitas perusahaan, dokumen, logo, QR, format, dan default TOP.")
))
story += [Spacer(1, 3 * mm)]
story.append(Table([[
    card("Form pengguna", ["Nama, email, telepon, password.", "Jabatan, role, status aktif/nonaktif.", "Target bila digunakan untuk pemantauan kinerja."], 122 * mm, BLUE),
    card("Settings", ["Nama/kontak/alamat perusahaan dan kota.", "Penandatangan, default TOP, mata uang, timezone, bahasa, format tanggal.", "Logo sidebar/login/favicon/dokumen dan QR tanda tangan."], 122 * mm, GREEN),
]], colWidths=[126 * mm, 126 * mm], style=TableStyle([
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 0),
    ("RIGHTPADDING", (0, 0), (-1, -1), 3),
])))
story += [PageBreak()]

# SOP checklist
story.extend(section_title(21, "Checklist Operasional Harian", "Gunakan checklist ini untuk mengurangi pekerjaan tertahan dan koreksi lintas role."))
check_data = [
    ["Waktu", "Role", "Checklist", "Tanda selesai"],
    ["Awal hari", "Sales Executive", "Periksa reminder, follow-up lead, update pipeline dan jadwal customer.", "Aktivitas tercatat"],
    ["Setelah request masuk", "Sales Admin", "Cek customer, rute, tanggal, lokasi muat/bongkar, container, seal, dan kebutuhan kendaraan.", "Request terverifikasi"],
    ["Setelah verifikasi", "Finance", "Isi layanan, jual, HPP, job cost, DP, dan pastikan margin wajar.", "Review Finance selesai"],
    ["Sebelum assignment", "Transport Planner", "Konfirmasi vendor/armada, kendaraan, driver, kontak, jadwal, dan estimasi.", "Siap approval"],
    ["Saat approval", "Sales Manager/Admin", "Bandingkan scope, assignment, harga jual, HPP, margin, dan risiko.", "Approve/reject beralasan"],
    ["Saat pengiriman", "Sales Admin/Admin", "Update surat jalan, pickup, delivered, POD, dan kejadian khusus.", "Milestone aktual"],
    ["Setelah POD", "Sales Admin/Admin", "Verifikasi POD dan biaya aktual, lalu close DO.", "Draft invoice tersedia"],
    ["Penagihan", "Finance", "Pilih periode, PPN, TOP, publish, lalu catat termin/lunas.", "Outstanding akurat"],
    ["Akhir hari", "Semua role", "Cek antrian kuning/menunggu dan pekerjaan yang ditolak/dikembalikan.", "Tidak ada handoff terlupa"],
]
check_tbl = Table([[P(c, "TableHead") for c in check_data[0]]] +
                  [[P(c, "TableCell") for c in row] for row in check_data[1:]],
                  colWidths=[26 * mm, 42 * mm, 132 * mm, 49 * mm], repeatRows=1)
check_tbl.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, 0), NAVY),
    ("GRID", (0, 0), (-1, -1), 0.4, LINE),
    ("ROWBACKGROUNDS", (0, 1), (-1, -1), [WHITE, PANEL]),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 5),
    ("RIGHTPADDING", (0, 0), (-1, -1), 5),
    ("TOPPADDING", (0, 0), (-1, -1), 5),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
]))
story += [check_tbl, Spacer(1, 3 * mm)]
story.append(P("Prinsip utama: data lengkap di awal akan mengurangi koreksi pada Finance, Planner, Delivery Order, dan Invoice.", "Small"))
story += [PageBreak()]

# Troubleshooting + screenshot index
story.extend(section_title(22, "Troubleshooting dan Daftar Output", "Langkah aman ketika aplikasi lambat, sesi lama tidak aktif, atau data belum terlihat."))
story.append(Table([[
    card("Aplikasi berputar/lemot setelah standby", [
        "Tunggu proses aktif maksimal sekitar 10 detik; jangan klik Simpan berulang.",
        "Refresh halaman satu kali. Jika kembali ke login, masuk kembali karena sesi telah berakhir.",
        "Buka kembali dokumen melalui nomor DO/Invoice dan periksa apakah perubahan terakhir sudah tersimpan.",
        "Jika berulang, catat waktu, menu, nomor dokumen, dan kirim screenshot ke administrator.",
    ], 122 * mm, ORANGE),
    card("Data tidak ditemukan", [
        "Periksa rentang tanggal/periode dan tab status.",
        "Hapus filter tahap, Sales PIC, customer, atau kata pencarian.",
        "Pastikan role memiliki akses ke menu tersebut.",
        "Untuk invoice lama, cari dengan Periode Invoice, bukan hanya tanggal publish.",
    ], 122 * mm, BLUE),
]], colWidths=[126 * mm, 126 * mm], style=TableStyle([
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 0),
    ("RIGHTPADDING", (0, 0), (-1, -1), 3),
])))
story += [Spacer(1, 4 * mm)]
output_data = [
    ["Modul", "Output utama", "Digunakan oleh"],
    ["Leads/Pipeline", "Histori aktivitas, tahap prospek, forecast revenue", "Sales & Manager"],
    ["Penawaran", "Dokumen/PDF penawaran", "Sales & Customer"],
    ["Request DO", "Request terverifikasi, rincian harga/HPP, assignment", "Sales Admin, Finance, Planner, Manager"],
    ["Delivery Order", "DO, surat jalan, milestone, POD, biaya aktual", "Operasional & Finance"],
    ["Invoice", "Invoice TR/NTR, pembayaran, outstanding, aging", "Finance & Management"],
    ["Reports", "Export performa, logistik, invoice, GP/margin", "Finance & Management"],
    ["Users/Settings", "Akun/role dan format identitas dokumen", "Super Admin/Admin"],
]
out_tbl = Table([[P(c, "TableHead") for c in output_data[0]]] +
                [[P(c, "TableCell") for c in row] for row in output_data[1:]],
                colWidths=[55 * mm, 120 * mm, 74 * mm])
out_tbl.setStyle(TableStyle([
    ("BACKGROUND", (0, 0), (-1, 0), NAVY),
    ("GRID", (0, 0), (-1, -1), 0.4, LINE),
    ("ROWBACKGROUNDS", (0, 1), (-1, -1), [WHITE, PANEL]),
    ("VALIGN", (0, 0), (-1, -1), "TOP"),
    ("LEFTPADDING", (0, 0), (-1, -1), 5),
    ("RIGHTPADDING", (0, 0), (-1, -1), 5),
    ("TOPPADDING", (0, 0), (-1, -1), 4),
    ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
]))
story += [out_tbl, Spacer(1, 4 * mm)]
story.append(P("Bantuan internal: sertakan nama akun, role, nomor dokumen, menu, waktu kejadian, langkah terakhir, dan screenshot agar kendala cepat ditelusuri.", "Small"))

doc.build(story, onFirstPage=header_footer, onLaterPages=header_footer)
print(OUTPUT)
