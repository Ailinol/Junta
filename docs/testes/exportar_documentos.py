"""Exporta os quatro entregáveis Markdown para HTML e DOCX sem dependências extra.

Executar: python docs/testes/exportar_documentos.py
O HTML pode ser impresso para PDF num navegador. Não executa testes ou a aplicação.
"""

from __future__ import annotations

import html
import re
import zipfile
from pathlib import Path
from xml.sax.saxutils import escape


BASE = Path(__file__).resolve().parent
FILES = [
    "Plano_Estrategia_Testes.md",
    "Matriz_Casos_Teste.md",
    "Relatorio_Execucao_Testes.md",
    "Plano_Apresentacao.md",
]


def blocks(text: str) -> list[tuple]:
    lines = text.splitlines()
    result = []
    index = 0
    while index < len(lines):
        line = lines[index]
        if not line.strip():
            index += 1
            continue
        if line.startswith("```"):
            language = line[3:].strip()
            index += 1
            content = []
            while index < len(lines) and not lines[index].startswith("```"):
                content.append(lines[index])
                index += 1
            result.append(("code", language, "\n".join(content)))
            index += 1
            continue
        heading = re.match(r"^(#{1,6})\s+(.+)$", line)
        if heading:
            result.append(("heading", len(heading[1]), heading[2]))
            index += 1
            continue
        if line.lstrip().startswith("|"):
            rows = []
            while index < len(lines) and lines[index].lstrip().startswith("|"):
                cells = [cell.strip() for cell in lines[index].strip().strip("|").split("|")]
                if not all(re.fullmatch(r":?-{2,}:?", cell or " ") for cell in cells):
                    rows.append(cells)
                index += 1
            result.append(("table", rows))
            continue
        if re.match(r"^\s*(?:- |\d+\. )", line):
            items = []
            while index < len(lines) and re.match(r"^\s*(?:- |\d+\. )", lines[index]):
                items.append(lines[index].strip())
                index += 1
            result.append(("list", items))
            continue
        paragraph = [line]
        index += 1
        while index < len(lines) and lines[index].strip() and not re.match(r"^(?:#|\||```|\s*- |\d+\. )", lines[index]):
            paragraph.append(lines[index])
            index += 1
        result.append(("paragraph", "\n".join(paragraph)))
    return result


def plain(text: str) -> str:
    text = re.sub(r"\[([^\]]+)\]\(([^)]+)\)", r"\1 (\2)", text)
    return text.replace("**", "").replace("`", "").replace("  \n", "\n")


def inline(text: str) -> str:
    protected = []

    def store(value: str) -> str:
        protected.append(value)
        return f"\x00{len(protected) - 1}\x00"

    text = re.sub(r"`([^`]+)`", lambda m: store("<code>" + html.escape(m[1]) + "</code>"), text)

    def link(match: re.Match) -> str:
        target = match[2]
        if target in FILES:
            target = "#document-" + str(FILES.index(target))
        return store('<a href="' + html.escape(target, quote=True) + '">' + html.escape(match[1]) + "</a>")

    text = re.sub(r"\[([^\]]+)\]\(([^)]+)\)", link, text)
    text = html.escape(text)
    text = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", text)
    text = text.replace("  \n", "<br>").replace("\n", " ")
    for index, value in enumerate(protected):
        text = text.replace(f"\x00{index}\x00", value)
    return text


def html_document(documents: list[list[tuple]]) -> str:
    output = []
    for document_index, document in enumerate(documents):
        css_class = "matrix" if document_index == 1 else "portrait"
        output.append(f'<section class="document {css_class}" id="document-{document_index}">')
        cover_open = document_index == 0
        if cover_open:
            output.append('<div class="cover">')
        for block in document:
            kind = block[0]
            if kind == "heading":
                if cover_open and block[1] == 2:
                    output.append("</div>")
                    cover_open = False
                output.append(f"<h{block[1]}>{inline(block[2])}</h{block[1]}>")
            elif kind == "paragraph":
                output.append("<p>" + inline(block[1]) + "</p>")
            elif kind == "code":
                output.append('<pre><code>' + html.escape(block[2]) + "</code></pre>")
            elif kind == "list":
                output.append('<div class="list">' + "".join("<p>" + inline(item) + "</p>" for item in block[1]) + "</div>")
            elif kind == "table":
                rows = block[1]
                output.append("<table><thead><tr>" + "".join("<th>" + inline(cell) + "</th>" for cell in rows[0]) + "</tr></thead><tbody>")
                for row in rows[1:]:
                    output.append("<tr>" + "".join("<td>" + inline(cell) + "</td>" for cell in row) + "</tr>")
                output.append("</tbody></table>")
        if cover_open:
            output.append("</div>")
        output.append("</section>")
    style = """
    @page { size: A4; margin: 18mm 16mm 20mm; @bottom-center { content: 'Junta · Engenharia de Software · ' counter(page); font: 8pt Arial; color: #60746b; } }
    @page matrix { size: A4 landscape; margin: 16mm; }
    * { box-sizing: border-box; }
    body { margin: 0; color: #20342c; font: 10.5pt/1.48 Georgia, 'Times New Roman', serif; }
    h1,h2,h3,h4 { font-family: Arial, sans-serif; color: #215640; line-height: 1.22; break-after: avoid; }
    h1 { font-size: 23pt; margin: 0 0 24pt; } h2 { font-size: 16pt; margin: 22pt 0 10pt; }
    h3 { font-size: 12pt; margin: 16pt 0 8pt; } p { margin: 0 0 9pt; orphans: 3; widows: 3; }
    a { color: #246d57; text-decoration: none; overflow-wrap: anywhere; }
    code { font-family: Consolas, monospace; font-size: .88em; overflow-wrap: anywhere; }
    pre { white-space: pre-wrap; background: #f0f4f1; padding: 10pt; border-left: 3pt solid #6e9f83; font-size: 9pt; break-inside: auto; }
    table { border-collapse: collapse; width: 100%; margin: 10pt 0 16pt; table-layout: auto; font: 8.2pt/1.35 Arial,sans-serif; }
    th,td { border: .5pt solid #c2d1c8; padding: 5pt; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
    th { background: #e4eee7; color: #244d3b; font-weight: bold; } tr { break-inside: avoid; }
    thead { display: table-header-group; } .list p { padding-left: 10pt; }
    .document + .document { break-before: page; } .matrix { page: matrix; }
    .cover { min-height: 235mm; padding: 24mm 8mm 12mm; border-top: 5pt solid #2b6f50; break-after: page; }
    .cover h1 { font-size: 32pt; margin-bottom: 22mm; }
    .cover p { font: 11pt/1.8 Arial,sans-serif; }
    @media screen { body { max-width: 1100px; padding: 36px; margin: auto; background: #fff; } }
    """
    return '<!doctype html><html lang="pt-MZ"><meta charset="utf-8"><title>Junta — Plano e Estratégia de Testes</title><style>' + style + "</style><body>" + "\n".join(output) + "</body></html>"


def paragraph(text: str, style: str = "Normal", small: bool = False) -> str:
    size = '<w:sz w:val="16"/>' if small else ""
    runs = []
    content = text if style == "Code" else plain(text)
    for index, line in enumerate(content.splitlines()):
        if index:
            runs.append("<w:r><w:br/></w:r>")
        runs.append('<w:r><w:rPr>' + size + '</w:rPr><w:t xml:space="preserve">' + escape(line) + "</w:t></w:r>")
    return '<w:p><w:pPr><w:pStyle w:val="' + style + '"/></w:pPr>' + "".join(runs) + "</w:p>"


def section(landscape: bool = False) -> str:
    size = 'w:w="16838" w:h="11906" w:orient="landscape"' if landscape else 'w:w="11906" w:h="16838"'
    return '<w:sectPr><w:footerReference w:type="default" r:id="footer"/><w:type w:val="nextPage"/><w:pgSz ' + size + '/><w:pgMar w:top="1134" w:right="907" w:bottom="1134" w:left="907" w:header="567" w:footer="567"/></w:sectPr>'


def word_document(documents: list[list[tuple]]) -> bytes:
    body = []
    for document_index, document in enumerate(documents):
        first_body_heading = True
        for block in document:
            kind = block[0]
            if kind == "heading":
                if document_index == 0 and block[1] == 2 and first_body_heading:
                    body.append('<w:p><w:r><w:br w:type="page"/></w:r></w:p>')
                    first_body_heading = False
                body.append(paragraph(block[2], "Title" if document_index == 0 and block[1] == 1 else "Heading" + str(min(block[1], 3))))
            elif kind == "paragraph":
                body.append(paragraph(block[1]))
            elif kind == "code":
                body.append(paragraph(block[2], "Code"))
            elif kind == "list":
                body.extend(paragraph(item) for item in block[1])
            elif kind == "table":
                rows = block[1]
                body.append('<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>' + "".join('<w:' + side + ' w:val="single" w:sz="4" w:color="C2D1C8"/>' for side in ["top", "left", "bottom", "right", "insideH", "insideV"]) + '</w:tblBorders></w:tblPr>')
                for row_index, row in enumerate(rows):
                    body.append('<w:tr><w:trPr><w:cantSplit/>' + ("<w:tblHeader/>" if row_index == 0 else "") + '</w:trPr>')
                    for cell in row:
                        shade = '<w:shd w:fill="E4EEE7"/>' if row_index == 0 else ""
                        body.append('<w:tc><w:tcPr>' + shade + '</w:tcPr>' + paragraph(cell, "Normal", small=True) + '</w:tc>')
                    body.append('</w:tr>')
                body.append('</w:tbl>')
        if document_index < len(documents) - 1:
            body.append('<w:p><w:pPr>' + section(document_index == 1) + '</w:pPr></w:p>')
    body.append(section())
    return ('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>' + "".join(body) + '</w:body></w:document>').encode("utf-8")


def export() -> None:
    documents = [blocks((BASE / name).read_text(encoding="utf-8")) for name in FILES]
    (BASE / "Plano_Estrategia_Testes.html").write_text(html_document(documents), encoding="utf-8")
    styles = '<?xml version="1.0" encoding="UTF-8"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Cambria" w:hAnsi="Cambria"/><w:sz w:val="22"/><w:lang w:val="pt-MZ"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="140" w:line="300" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
    for name, size in [("Title", 52), ("Heading1", 36), ("Heading2", 28), ("Heading3", 24)]:
        styles += '<w:style w:type="paragraph" w:styleId="' + name + '"><w:name w:val="' + name + '"/><w:basedOn w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="220" w:after="140"/></w:pPr><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:b/><w:color w:val="215640"/><w:sz w:val="' + str(size) + '"/></w:rPr></w:style>'
    styles += '<w:style w:type="paragraph" w:styleId="Code"><w:name w:val="Code"/><w:basedOn w:val="Normal"/><w:pPr><w:shd w:fill="F0F4F1"/></w:pPr><w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:sz w:val="18"/></w:rPr></w:style></w:styles>'
    package = {
        "[Content_Types].xml": '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/></Types>',
        "_rels/.rels": '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="document" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
        "word/document.xml": word_document(documents),
        "word/styles.xml": styles,
        "word/_rels/document.xml.rels": '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="styles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/><Relationship Id="footer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/></Relationships>',
        "word/footer1.xml": '<?xml version="1.0" encoding="UTF-8"?><w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:t>Junta · Engenharia de Software · </w:t></w:r><w:fldSimple w:instr="PAGE"/></w:p></w:ftr>',
    }
    with zipfile.ZipFile(BASE / "Plano_Estrategia_Testes.docx", "w", zipfile.ZIP_DEFLATED) as archive:
        for name, content in package.items():
            archive.writestr(name, content)
    print("HTML e DOCX consolidados gerados em docs/testes/.")


if __name__ == "__main__":
    export()
