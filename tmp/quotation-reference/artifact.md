# Quotation template contract

## Reference

- Source: `D:\Project\Assets 2026\Firman Tangguh (Logistic)\Surat Penawaran Harga FTL-PT Garam Gresik - Sidoarjo 03 Agustus 2026.docx`
- SHA-256: `237CB74AAFAB11F1DAB33A23CE17D22BE1F93A1F24D3952A632FA0A6E29C1D24`
- Output inspected: `D:\Project\1017website 2026\logistic-crm\tmp\quotation-reference\reference.pdf`
- Render inspected: `D:\Project\1017website 2026\logistic-crm\tmp\quotation-reference\page-1.png`
- Page count: 1; section count: 1.
- Page system: A4 portrait, 210 x 297 mm, Word section margins 25.4 mm on all sides, no different first page, no odd/even header variation.

## Visual system

- Header: wide horizontal company logo at left; company name, address, telephone, website, and email at right.
- Header separator: thick rounded rule spanning the usable page width, transitioning from orange/gold into cyan and black.
- Body: black sans-serif type with compact line-height. Body blocks are inset farther than the rate table.
- Metadata: two label/value rows for `Lampiran` and `Perihal`.
- Recipient: `Yth.`, recipient title/name, company, and address/location on separate lines.
- Rate table: seven columns (`NO`, `ORIGIN`, `DESTINATION`, `COMDTY`, `TONASE`, `UNIT`, `RATE`), thin black borders, bold centered header, left-aligned text except number, and emphasized tonnage/unit/rate values.
- Terms: bold italic lead-in followed by a bold numbered list.
- Closing: prose paragraph followed by city/date, acknowledgement, signatory title, optional stamp/signature image, and signatory name.

## Content flow and slots

1. Company logo and contact details (from CRM Settings; preserve the two-column header relationship).
2. Attachment and subject.
3. Recipient title/name, company, and address.
4. Opening paragraph naming the recipient company.
5. One or more rate rows with the seven fields above.
6. One or more numbered terms.
7. Closing paragraph with contact name and phone.
8. Document city/date, signatory title, optional signature/stamp, and signatory name.

## Fidelity gates

- Export is PDF-only and uses A4 portrait.
- Company branding remains at the top and the rate table is the central visual anchor.
- Table headers and numbered conditions remain intact across page breaks.
- No clipped columns, overlapping signature, broken Indonesian text, or browser print controls.
- The reference DOCX is read-only and must not be modified.
