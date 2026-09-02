from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

root = Path(r"D:\Project\1017website 2026\logistic-crm\tmp\pdfs")
pages = sorted((root / "rendered-v2").glob("page-*.png"), key=lambda p: int(p.stem.split("-")[-1]))
font_path = Path(r"C:\Windows\Fonts\arialbd.ttf")
font = ImageFont.truetype(str(font_path), 18) if font_path.exists() else ImageFont.load_default()

for batch_index in range(0, len(pages), 9):
    batch = pages[batch_index:batch_index + 9]
    sheet = Image.new("RGB", (1260, 930), "#D8DEE9")
    draw = ImageDraw.Draw(sheet)
    for i, path in enumerate(batch):
        page_num = int(path.stem.split("-")[-1])
        img = Image.open(path).convert("RGB")
        img.thumbnail((400, 270), Image.Resampling.LANCZOS)
        col = i % 3
        row = i // 3
        x = 10 + col * 416
        y = 30 + row * 300
        sheet.paste(img, (x, y))
        draw.text((x, y - 23), f"Halaman {page_num}", fill="#172033", font=font)
    out = root / f"contact-v2-{batch_index // 9 + 1}.png"
    sheet.save(out, quality=92)
    print(out)
