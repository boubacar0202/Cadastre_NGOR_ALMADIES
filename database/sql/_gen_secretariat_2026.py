#!/usr/bin/env python3
"""Génère l'import secrétariat 2026 et son rapport de comparaison.

Règles convenues : NICAD d'abord, sinon titre + lot ; pas d'UPDATE ;
id 13413 et n° d'ordre 13411 si les imports 2023-2025 s'arrêtent bien
à 13412 / 13410 ; agent et date de dépôt seulement parmi les dates
du registre ; commune NGOR, OUAKAM ou YOFF si le NICAD ne la donne pas.
"""

from __future__ import annotations

import re
import statistics
import zipfile
from collections import Counter, defaultdict
from datetime import datetime, timedelta
from decimal import Decimal, InvalidOperation
from pathlib import Path
from xml.etree import ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
SQL_DIR = ROOT / "database" / "sql"
XLSX = Path("/Users/7maksacodpc/Desktop/secretariat 2026.xlsx")
OUT_SQL = SQL_DIR / "import_secretariat_2026.sql"
OUT_TXT = SQL_DIR / "rapport_import_secretariat_2026.txt"

PRIOR = {
    2023: SQL_DIR / "import_secretariat_2023.sql",
    2024: SQL_DIR / "import_secretariat_2024.sql",
    2025: SQL_DIR / "import_secretariat_2025.sql",
}
EXPECTED_TERRAINS = {2023: 12402, 2024: 291, 2025: 716}
EXPECTED_READ_2025 = 2155

COMMUNE_BY_PREFIX = {"01130132": 1, "01130131": 2, "01130133": 3}
COMMUNE_NAME = {1: "Ngor", 2: "Ouakam", 3: "Yoff"}
ZONE_COMMUNE = {"NGOR": 1, "OUAKAM": 2, "YOFF": 3}
EPOCH = datetime(1899, 12, 30)
NS = {"m": "http://schemas.openxmlformats.org/spreadsheetml/2006/main"}
COMPANY_RE = re.compile(
    r"(?<![\w])(?:SUARL|SARL|SAS|GIE|COMPANY|SA)(?![\w])"
)

START_ID = 13413
START_ORDRE = 13411
BATCH = 40


def parse_tuple(line: str) -> list[tuple[bool, str]]:
    s = line.strip()
    if s.endswith(";"):
        s = s[:-1].rstrip()
    if s.endswith(","):
        s = s[:-1].rstrip()
    if not s.startswith("(") or not s.endswith(")"):
        raise ValueError(f"tuple illisible: {s[:80]}")
    s = s[1:-1]
    fields: list[tuple[bool, str]] = []
    buf: list[str] = []
    is_str = False
    in_str = False
    depth = 0
    i = 0
    while i < len(s):
        c = s[i]
        if in_str:
            if c == "'" and i + 1 < len(s) and s[i + 1] == "'":
                buf.append("'")
                i += 2
                continue
            if c == "\\" and i + 1 < len(s):
                buf.append(s[i + 1])
                i += 2
                continue
            if c == "'":
                in_str = False
                i += 1
                continue
            buf.append(c)
            i += 1
            continue
        if c == "'":
            in_str = True
            is_str = True
            i += 1
            continue
        if c == "(":
            depth += 1
            buf.append(c)
            i += 1
            continue
        if c == ")":
            if depth == 0:
                raise ValueError(f"parenthèse en trop: {line[:80]}")
            depth -= 1
            buf.append(c)
            i += 1
            continue
        if c == "," and depth == 0:
            fields.append((is_str, "".join(buf).strip()))
            buf = []
            is_str = False
            i += 1
            continue
        buf.append(c)
        i += 1
    fields.append((is_str, "".join(buf).strip()))
    return fields


def token(field: tuple[bool, str]) -> str | None:
    is_str, value = field
    if not is_str and value.upper() == "NULL":
        return None
    if is_str and value == "":
        return None
    return value


def iter_rows(path: Path, table: str):
    prefix = f"INSERT INTO `{table}`"
    active = False
    with path.open(encoding="utf-8") as handle:
        for line in handle:
            if prefix in line:
                active = True
                continue
            if not active:
                continue
            stripped = line.strip()
            if not stripped or stripped.startswith("--"):
                continue
            if stripped.startswith("("):
                yield parse_tuple(stripped)
            if stripped.endswith(";"):
                active = False


def norm_space(value: str | None) -> str:
    return re.sub(r"\s+", " ", (value or "").strip())


def norm_titre(value: str | None) -> str | None:
    text = norm_space(value).upper()
    text = re.sub(r"\s*/\s*", "/", text)
    if text in {"", "-", "—", "–", "0", "000", "#VALUE!"}:
        return None
    return text


def surface_amount(value: str | None) -> str | None:
    match = re.fullmatch(
        r"(\d+(?:[.,]\d+)?)\s*m(?:²|2)?",
        norm_space(value),
        flags=re.IGNORECASE,
    )
    if not match:
        return None
    try:
        amount = Decimal(match.group(1).replace(",", ".")).quantize(Decimal("0.01"))
    except InvalidOperation:
        return None
    return format(amount, "f")


def norm_lot(value: str | None) -> str | None:
    if surface_amount(value):
        return None
    text = norm_space(value).upper()
    text = re.sub(r"\s*/\s*", "/", text)
    if text in {"", "-", "—", "–", "0", "000", "#VALUE!", "N/A", "NA"}:
        return None
    return text


def norm_nicad(value: str | None) -> tuple[dict | None, str]:
    digits = re.sub(r"\D", "", value or "")
    if not digits:
        return None, "vide"
    if len(digits) in {15, 18} and digits.startswith("1130"):
        digits = "0" + digits
    if len(digits) not in {16, 19}:
        return None, "illisible"
    prefix = digits[:8]
    if prefix not in COMMUNE_BY_PREFIX:
        return None, "illisible"
    return {
        "nicad": digits,
        "commune": COMMUNE_BY_PREFIX[prefix],
        "section": digits[8:11],
        "parcelle": digits[11:16],
        "appart": digits[16:19] if len(digits) == 19 else None,
    }, "ok"


def nicad_aliases(value: str | None) -> set[str]:
    digits = re.sub(r"\D", "", value or "")
    aliases = set()
    if digits:
        aliases.add(digits)
    parsed, status = norm_nicad(value)
    if status == "ok" and parsed:
        aliases.add(parsed["nicad"])
    return aliases


def parse_date(value: str | None):
    text = norm_space(value)
    if not text or text.startswith("#"):
        return None
    if re.fullmatch(r"\d+(?:\.\d+)?", text):
        serial = int(float(text))
        if 40000 <= serial <= 60000:
            return (EPOCH + timedelta(days=serial)).date()
        return None
    for fmt in ("%Y-%m-%d", "%d/%m/%Y", "%d-%m-%Y"):
        try:
            return datetime.strptime(text[:10], fmt).date()
        except ValueError:
            continue
    return None


def clean_name(value: str | None) -> str | None:
    text = norm_space(value)
    text = re.sub(r'["«»“”]', " ", text)
    text = norm_space(text)
    return text.upper() if text else None


def embedded_nicads(value: str | None) -> list[str]:
    found = []
    for run in re.findall(r"\d+", value or ""):
        parsed, status = norm_nicad(run)
        if status == "ok" and parsed["nicad"] not in found:
            found.append(parsed["nicad"])
    return found


def is_company(value: str | None) -> bool:
    raw = value or ""
    if any(mark in raw for mark in ['"', "«", "»", "“", "”"]):
        return True
    return COMPANY_RE.search(clean_name(raw) or "") is not None


def zone_commune(value: str | None) -> int | None:
    text = norm_space(value).upper()
    return ZONE_COMMUNE.get(text)


def is_recupere(value: str | None) -> bool:
    text = norm_space(value).upper()
    text = (
        text.replace("Ë", "E")
        .replace("É", "E")
        .replace("È", "E")
        .replace("Ê", "E")
    )
    return text == "RECUPERE"


def sql_str(value) -> str:
    if value is None:
        return "NULL"
    text = str(value).replace("\\", "\\\\").replace("'", "''")
    text = text.replace("\r", " ").replace("\n", " ")
    return "'" + text + "'"


def sql_num(value) -> str:
    if value is None:
        return "NULL"
    return str(value)


def load_history():
    nicad_years: dict[str, set[int]] = defaultdict(set)
    nicad_id: dict[tuple[int, str], int] = {}
    tl_years: dict[tuple[str, str], set[int]] = defaultdict(set)
    tl_id: dict[tuple[int, str, str], int] = {}
    counts = {}
    communes = Counter()
    sans = Counter()
    max_id = 0
    max_ordre = 0
    dossier_nums = set()
    for year, path in PRIOR.items():
        terrains = 0
        for fields in iter_rows(path, "terrains"):
            if len(fields) != 28:
                raise SystemExit(f"{year}: terrain à {len(fields)} champs, 28 attendus")
            terrains += 1
            terrain_id = int(token(fields[0]))
            lot = norm_lot(token(fields[4]))
            titre = norm_titre(token(fields[8]))
            raw_nicad = token(fields[13])
            commune = int(token(fields[22]))
            communes[(year, commune)] += 1
            if raw_nicad:
                for alias in nicad_aliases(raw_nicad):
                    nicad_years[alias].add(year)
                    nicad_id.setdefault((year, alias), terrain_id)
            else:
                sans[year] += 1
            if titre and lot:
                tl_years[(titre, lot)].add(year)
                tl_id.setdefault((year, titre, lot), terrain_id)
        dossiers = 0
        for fields in iter_rows(path, "dossiers"):
            if len(fields) != 10:
                raise SystemExit(f"{year}: dossier à {len(fields)} champs, 10 attendus")
            dossiers += 1
            dossier_id = int(token(fields[0]))
            numero = token(fields[1])
            ordre = int(token(fields[2]))
            max_id = max(max_id, dossier_id)
            max_ordre = max(max_ordre, ordre)
            dossier_nums.add(numero)
        if terrains != EXPECTED_TERRAINS[year] or dossiers != terrains:
            raise SystemExit(
                f"{year}: {terrains} terrains et {dossiers} dossiers, "
                f"{EXPECTED_TERRAINS[year]} attendus"
            )
        counts[year] = terrains
    if max_id != START_ID - 1 or max_ordre != START_ORDRE - 1:
        raise SystemExit(
            f"identifiants inattendus: max id {max_id}, max ordre {max_ordre}"
        )
    return {
        "nicad_years": nicad_years,
        "nicad_id": nicad_id,
        "tl_years": tl_years,
        "tl_id": tl_id,
        "counts": counts,
        "communes": communes,
        "sans": sans,
        "dossier_nums": dossier_nums,
    }


def load_xlsx():
    with zipfile.ZipFile(XLSX) as book:
        shared = []
        if "xl/sharedStrings.xml" in book.namelist():
            root = ET.fromstring(book.read("xl/sharedStrings.xml"))
            for item in root.findall("m:si", NS):
                shared.append(
                    "".join(
                        node.text or ""
                        for node in item.iter(
                            "{http://schemas.openxmlformats.org/spreadsheetml/2006/main}t"
                        )
                    )
                )
        sheet = ET.fromstring(book.read("xl/worksheets/sheet1.xml"))
        rows = []
        for row in sheet.findall("m:sheetData/m:row", NS):
            excel = int(row.attrib.get("r", "0"))
            if excel <= 1:
                continue
            cells = {}
            for cell in row.findall("m:c", NS):
                ref = cell.attrib.get("r", "")
                column = "".join(char for char in ref if char.isalpha())
                kind = cell.attrib.get("t")
                node = cell.find("m:v", NS)
                value = node.text if node is not None else ""
                if kind == "s" and value.isdigit():
                    value = shared[int(value)]
                inline = cell.find("m:is", NS)
                if inline is not None:
                    value = "".join(
                        node.text or ""
                        for node in inline.iter(
                            "{http://schemas.openxmlformats.org/spreadsheetml/2006/main}t"
                        )
                    )
                cells[column] = value
            if not any(norm_space(cells.get(col)) for col in "ABCDEFGHIJKLM"):
                continue
            parsed, status = norm_nicad(cells.get("K"))
            titre = norm_titre(cells.get("E"))
            lot = norm_lot(cells.get("F"))
            depot = parse_date(cells.get("B"))
            deliv = parse_date(cells.get("I"))
            retrait = parse_date(cells.get("J"))
            if depot:
                creation, source = depot, "depot"
            elif deliv:
                creation, source = deliv, "delivrance"
            else:
                creation, source = None, None
            prenom_raw = cells.get("C")
            item = {
                "excel": excel,
                "ordre": norm_space(cells.get("A")) or None,
                "depot": depot,
                "deliv": deliv,
                "retrait": retrait,
                "creation": creation,
                "date_source": source,
                "prenom_raw": prenom_raw,
                "nom_raw": cells.get("D"),
                "prenom": clean_name(prenom_raw),
                "nom": clean_name(cells.get("D")),
                "company": is_company(prenom_raw),
                "company_nom": is_company(cells.get("D")),
                "titre": titre,
                "lot": lot,
                "surface": surface_amount(cells.get("F")),
                "zone": norm_space(cells.get("G")) or None,
                "nicad_raw": norm_space(cells.get("K")) or None,
                "nicad": parsed["nicad"] if parsed else None,
                "nicad_status": status,
                "section": parsed["section"] if parsed else None,
                "parcelle": parsed["parcelle"] if parsed else None,
                "appart": parsed["appart"] if parsed else None,
                "commune_nicad": parsed["commune"] if parsed else None,
                "commune_zone": zone_commune(cells.get("G")),
                "agent": clean_name(cells.get("L")),
                "obs": norm_space(cells.get("M")) or None,
                "recupere": is_recupere(cells.get("M")),
                "tl": (titre, lot) if titre and lot else None,
            }
            if item["commune_nicad"]:
                item["commune"] = item["commune_nicad"]
            else:
                item["commune"] = item["commune_zone"]
            rows.append(item)
    rows.sort(key=lambda item: item["excel"])
    return rows


def classify(rows, history):
    seen_nicad = {}
    seen_tl = {}
    for item in rows:
        item["dup_of"] = None
        item["dup_kind"] = None
        nicad = item["nicad"]
        tl = item["tl"]
        if nicad and nicad in seen_nicad:
            item["dup_of"] = seen_nicad[nicad]
            item["dup_kind"] = "nicad"
        elif not nicad and tl and tl in seen_tl:
            item["dup_of"] = seen_tl[tl]
            item["dup_kind"] = "titre_lot"
        else:
            if nicad:
                seen_nicad[nicad] = item["excel"]
            if tl and tl not in seen_tl:
                seen_tl[tl] = item["excel"]

        years = set()
        if nicad:
            years |= history["nicad_years"].get(nicad, set())
            if item["nicad_raw"]:
                years |= history["nicad_years"].get(
                    re.sub(r"\D", "", item["nicad_raw"]), set()
                )
        item["nicad_years"] = years
        item["tl_years"] = history["tl_years"].get(tl, set()) if tl else set()
        item["embedded"] = [] if nicad else embedded_nicads(item["nicad_raw"])
        embedded_years = set()
        for alias in item["embedded"]:
            embedded_years |= history["nicad_years"].get(alias, set())
        item["embedded_years"] = embedded_years

        if item["dup_of"]:
            item["reason"] = "doublon"
        elif item["creation"] is None:
            item["reason"] = "dates"
        elif nicad and 2025 in years:
            item["reason"] = "deja_2025"
        elif nicad and years & {2023, 2024}:
            item["reason"] = "deja_ancien"
        elif not nicad and tl and 2025 in item["tl_years"]:
            item["reason"] = "deja_2025"
        elif not nicad and tl and item["tl_years"] & {2023, 2024}:
            item["reason"] = "deja_ancien"
        elif 2025 in embedded_years:
            item["reason"] = "deja_2025"
        elif embedded_years & {2023, 2024}:
            item["reason"] = "deja_ancien"
        elif item["commune"] is None:
            item["reason"] = "commune"
        else:
            item["reason"] = "retenue"
        item["nouveau_titre"] = bool(
            item["reason"] == "retenue" and nicad and tl and item["tl_years"]
        )


def base_id(history, item):
    if item["nicad"] and item["nicad_years"]:
        year = 2025 if 2025 in item["nicad_years"] else min(item["nicad_years"])
        found = history["nicad_id"].get((year, item["nicad"]))
        if found:
            return year, found
    if item.get("embedded_years"):
        year = 2025 if 2025 in item["embedded_years"] else min(item["embedded_years"])
        for alias in item["embedded"]:
            found = history["nicad_id"].get((year, alias))
            if found:
                return year, found
    if item["tl"] and item["tl_years"]:
        year = 2025 if 2025 in item["tl_years"] else min(item["tl_years"])
        titre, lot = item["tl"]
        found = history["tl_id"].get((year, titre, lot))
        if found:
            return year, found
    return None, None


def person_label(item) -> str:
    if item["company"]:
        return item["prenom"] or item["nom"] or "-"
    parts = [part for part in (item["prenom"], item["nom"]) if part]
    return " ".join(parts) if parts else "-"


def describe(item, extra="") -> str:
    lot = item["lot"] or (item["surface"] + " m²" if item["surface"] else "-")
    text = (
        f"Feuil1 l.{item['excel']} | ordre={item['ordre'] or '-'} | "
        f"titre={item['titre'] or '-'} | lot={lot} | "
        f"nicad={item['nicad'] or item['nicad_raw'] or '-'} | "
        f"zone={item['zone'] or '-'} | {person_label(item)}"
    )
    return text + (f" | {extra}" if extra else "")


def lf_code(titre: str | None) -> str | None:
    if not titre or "/" not in titre:
        return None
    suffix = titre.rsplit("/", 1)[1]
    if re.fullmatch(r"[A-Z]{2,6}", suffix):
        return suffix
    return None


def build_retained(rows):
    retained = [item for item in rows if item["reason"] == "retenue"]
    for offset, item in enumerate(retained):
        item["id"] = START_ID + offset
        item["ordre_db"] = START_ORDRE + offset
        item["dossier"] = f"{offset + 1:05d}/2026"
        if any(len(item[key] or "") > 255 for key in ("prenom", "nom", "titre", "lot", "agent", "zone")):
            raise SystemExit(f"valeur trop longue en ligne {item['excel']}")
    return retained


def write_batches(handle, table, columns, values):
    for start in range(0, len(values), BATCH):
        chunk = values[start : start + BATCH]
        handle.write(f"INSERT INTO `{table}` ({columns}) VALUES\n")
        for index, value in enumerate(chunk):
            end = ";\n" if index == len(chunk) - 1 else ",\n"
            handle.write(value + end)
        handle.write("\n")


def write_sql(rows, retained, history):
    counts = Counter(item["reason"] for item in rows)
    nouveau = sum(1 for item in retained if item["nouveau_titre"])
    nicad_ok = sum(1 for item in retained if item["nicad"])
    appart = sum(1 for item in retained if item["appart"] and item["appart"] != "000")
    zone_only = sum(1 for item in retained if not item["nicad"])
    fallback = sum(1 for item in retained if item["date_source"] == "delivrance")
    header = f"""-- Import secrétariat 2026
-- Source : Desktop/secretariat 2026.xlsx, feuille Feuil1.
-- Comparé aux imports 2023, 2024 et 2025. Aucun UPDATE.
-- Ne reprend pas une base vivante qui aurait déjà dépassé ces fichiers :
--   le dernier dossier y est {START_ID - 1}, le dernier n° d'ordre {START_ORDRE - 1}.
--
-- Tables du formulaire : dossiers, references_cadastrales, titulaires, terrains.
--   Pas d'attribution.
--   Date de dépôt dans dt_date_creation. Si elle est illisible, date de délivrance.
--   Agent dans txt_agentTraiteur. Zone, retrait, observation et délivrance du
--   dossier restent dans le rapport : aucune colonne ne les porte.
--   dt_date_delivrance du titulaire n'est pas la délivrance du dossier.
--   rd_immatriculation_terrain est obligatoire. Un titre donne
--   Terrain Immatriculé, sinon Terrain Non Immatriculé. slt_lf reçoit le
--   suffixe du titre quand il existe. Les autres champs de la référence restent vides.
--
-- NICAD = code commune + section (3) + parcelle (5) + appartement (3).
--   Ngor 01130132, Ouakam 01130131, Yoff 01130133.
--   15 ou 18 chiffres commençant par 1130 : le zéro manquant est ajouté.
--   Sans NICAD lisible, la commune est prise seulement si la zone est
--   exactement NGOR, OUAKAM ou YOFF.
--
-- Doublon dans le classeur : même NICAD, ou NICAD vide avec le même titre et le même lot.
--   On garde la première ligne. 0 et 000 ne sont pas un lot.
--   Un lot écrit 1425m² devient une surface.
-- Déjà en base : NICAD présent dans un import antérieur.
--   Un NICAD complet écrit dans une case illisible compte aussi.
--   NICAD vide et même titre + lot déjà en base : écarté.
--   NICAD nouveau avec un titre + lot déjà connu : conservé.
--
-- Lignes lues : {len(rows)}
-- Lignes retenues : {len(retained)}
-- Déjà en 2025 : {counts['deja_2025']}
-- Déjà en 2023 ou 2024 : {counts['deja_ancien']}
-- Doublons internes écartés : {counts['doublon']}
-- Commune inconnue : {counts['commune']}
-- Dates illisibles : {counts['dates']}
-- NICAD nouveau, titre et lot déjà connus, conservées : {nouveau}
-- NICAD découpés : {nicad_ok}
-- Entrées par la zone seule : {zone_only}
-- Date de création prise sur la délivrance : {fallback}
-- Appartements autres que 000 : {appart}
-- Identifiants à partir de {START_ID}. Dossiers 00001/2026 et suivants.
-- N° d'ordre à partir de {START_ORDRE}.
-- Détail : rapport_import_secretariat_2026.txt

SET NAMES utf8mb4;
START TRANSACTION;

"""
    dossier_cols = (
        "id, txt_num_dossier, txt_num_dordre, slt_service_rendu, txt_etat_cession, "
        "txt_cession_definitive, dt_date_creation, txt_agentTraiteur, created_at, updated_at"
    )
    ref_cols = (
        "id, rd_immatriculation_terrain, slt_dependant_domaine, ussu_bornage, slt_lf, "
        "txt_num_requisition, txt_surface_bornage, dt_date_bornage, txt_nom_geometre, "
        "created_at, updated_at"
    )
    titulaire_cols = (
        "id, slt_titulaire, txt_nationalite, slt_civilite, txt_prenom, txt_nom, slt_piece, "
        "txt_numPiece, dt_date_delivrance, dt_date_naissance, txt_lieu_naissance, txt_adresse, "
        "tel_telephone, eml_email, txt_representant, tel_telRepresentant, slt_categoriePM, "
        "txt_formJuridiquePM, txt_denominationPM, txt_nineaPM, txt_perssonneRepresentantPM, "
        "txt_fonctionPM, txt_telephonePM, txt_emailPM, txt_adressePM, slt_etablissementPA, "
        "txt_personneResponsablePA, txt_fonctionResponsablePA, txt_telephonePA, txt_emailPA, "
        "`txt_ministèreTutelePA`, fichierPDF, created_at, updated_at"
    )
    terrain_cols = (
        "id, txt_num_parcelle, txt_lotissement, txt_HorsLotissement, txt_num_lotissement, "
        "txt_num_section, nbr_surface, txt_titre_mere, txt_num_titre, txt_appartement, "
        "slt_document_admin, txt_num_deliberation, dt_date_deliberation, txt_nicad, "
        "slt_statut_nicad, slt_motif_annulation_nicad, slt_signalement, slt_statutSignalement, "
        "slt_designationSignalement, slt_region, slt_departement, slt_arrondissement, "
        "slt_commune, txt_num_dossier, reference_cadastrale_id, titulaire_id, created_at, updated_at"
    )
    dossiers, refs, titulaires, terrains = [], [], [], []
    for item in retained:
        ident = item["id"]
        dossiers.append(
            "("
            + ", ".join(
                [
                    str(ident),
                    sql_str(item["dossier"]),
                    str(item["ordre_db"]),
                    "NULL",
                    "NULL",
                    "NULL",
                    sql_str(item["creation"].isoformat()),
                    sql_str(item["agent"]),
                    "NOW()",
                    "NOW()",
                ]
            )
            + ")"
        )
        if item["titre"]:
            refs.append(
                "("
                + ", ".join(
                    [
                        str(ident),
                        sql_str("Terrain Immatriculé"),
                        "NULL",
                        "NULL",
                        sql_str(lf_code(item["titre"])),
                        "NULL",
                        "NULL",
                        "NULL",
                        "NULL",
                        "NOW()",
                        "NOW()",
                    ]
                )
                + ")"
            )
        else:
            refs.append(
                f"({ident}, 'Terrain Non Immatriculé', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NOW(), NOW())"
            )
        if item["company"]:
            titulaires.append(
                "("
                + ", ".join(
                    [
                        str(ident),
                        sql_str("Personne Morale"),
                        "NULL",
                        "NULL",
                        "NULL",
                        sql_str(item["nom"]),
                        "NULL",
                        "NULL",
                        "NULL",
                        "NULL",
                        "NULL",
                        "NULL",
                        "NULL",
                        "NULL",
                        "NULL",
                        "NULL",
                        "NULL",
                        "NULL",
                        sql_str(item["prenom"]),
                    ]
                    + ["NULL"] * 13
                    + ["NOW()", "NOW()"]
                )
                + ")"
            )
        else:
            titulaires.append(
                "("
                + ", ".join(
                    [
                        str(ident),
                        sql_str("Personne Physique"),
                        "NULL",
                        "NULL",
                        sql_str(item["prenom"]),
                        sql_str(item["nom"]),
                    ]
                    + ["NULL"] * 26
                    + ["NOW()", "NOW()"]
                )
                + ")"
            )
        statut = "Actif" if item["nicad"] else "Sans NICAD"
        terrains.append(
            "("
            + ", ".join(
                [
                    str(ident),
                    sql_str(item["parcelle"]),
                    "NULL",
                    "NULL",
                    sql_str(item["lot"]),
                    sql_str(item["section"]),
                    sql_str(item["surface"]),
                    "NULL",
                    sql_str(item["titre"]),
                    sql_str(item["appart"]),
                    "NULL",
                    "NULL",
                    "NULL",
                    sql_str(item["nicad"]),
                    sql_str(statut),
                    "NULL",
                    "NULL",
                    "NULL",
                    "NULL",
                    "1",
                    "1",
                    "1",
                    str(item["commune"]),
                    str(ident),
                    str(ident),
                    str(ident),
                    "NOW()",
                    "NOW()",
                ]
            )
            + ")"
        )
    with OUT_SQL.open("w", encoding="utf-8") as handle:
        handle.write(header)
        write_batches(handle, "dossiers", dossier_cols, dossiers)
        write_batches(handle, "references_cadastrales", ref_cols, refs)
        write_batches(handle, "titulaires", titulaire_cols, titulaires)
        write_batches(handle, "terrains", terrain_cols, terrains)
        handle.write(
            "COMMIT;\n\n"
            "-- Contrôle : NICAD = code commune + section + parcelle + appartement.\n"
            "SELECT COUNT(*) AS nicad_incoherents FROM terrains t\n"
            "JOIN communes c ON c.id = t.slt_commune\n"
            "WHERE t.txt_nicad IS NOT NULL AND REPLACE(t.txt_nicad, ' ', '') <> CONCAT(\n"
            "  REPLACE(c.txt_codeCommune, ' ', ''), IFNULL(t.txt_num_section, ''),\n"
            "  IFNULL(t.txt_num_parcelle, ''), IFNULL(t.txt_appartement, ''));\n"
        )
    if history["dossier_nums"] & {item["dossier"] for item in retained}:
        raise SystemExit("numéro de dossier 2026 déjà présent dans un import antérieur")


def delay_stats(pairs):
    if not pairs:
        return "aucun couple de dates"
    values = sorted(days for _, days in pairs)
    negative = [item for item in pairs if item[1] < 0]
    median = statistics.median(values)
    median_txt = str(int(median)) if float(median).is_integer() else f"{median:.1f}".replace(".", ",")
    return (
        f"n={len(values)}, médiane {median_txt} j, "
        f"min {values[0]} j, max {values[-1]} j, négatifs {len(negative)}"
    )


def write_report(rows, retained, history):
    counts = Counter(item["reason"] for item in rows)
    lines = []
    add = lines.append

    def distinct_nicad(predicate):
        return len({item["nicad"] for item in rows if item["nicad"] and predicate(item)})

    nicad_2025 = distinct_nicad(lambda item: 2025 in item["nicad_years"])
    nicad_ancien = distinct_nicad(
        lambda item: bool(item["nicad_years"] & {2023, 2024}) and 2025 not in item["nicad_years"]
    )
    nicad_both = distinct_nicad(
        lambda item: 2025 in item["nicad_years"] and bool(item["nicad_years"] & {2023, 2024})
    )
    nicad_new = distinct_nicad(lambda item: not item["nicad_years"])
    all_nicad = distinct_nicad(lambda item: True)

    add("Import secrétariat 2026 — analyse et écarts")
    add("Source : Desktop/secretariat 2026.xlsx, feuille Feuil1.")
    add("Comparaison lue dans import_secretariat_2023.sql, import_secretariat_2024.sql et import_secretariat_2025.sql.")
    add("La requête n'a pas été exécutée.")
    add("")
    add("LECTURE")
    add(
        f"Le registre 2026 contient {len(rows)} lignes. "
        f"{all_nicad} NICAD distincts et lisibles y figurent : "
        f"{nicad_2025} sont déjà dans l'import 2025, "
        f"{nicad_ancien} sont dans 2023 ou 2024 sans être dans 2025, "
        f"{nicad_new} sont nouveaux."
    )
    add(
        f"Après les règles d'import, {len(retained)} dossiers sont retenus "
        f"et {len(rows) - len(retained)} lignes sont écartées. "
        f"L'import 2025 avait lu {EXPECTED_READ_2025} lignes et en avait retenu "
        f"{history['counts'][2025]}."
    )
    depots = [item["depot"] for item in rows if item["depot"]]
    add(
        f"Les dépôts lisibles vont du {min(depots).isoformat()} au {max(depots).isoformat()} "
        f"({len(depots)} lignes)."
    )
    add(
        "2025 n'a pas de date de dépôt, de délivrance ni de retrait : "
        "les délais et la mention RECUPERE décrivent le registre 2026 seulement."
    )
    add("")
    add("VOLUMES IMPORTÉS")
    add(f"2023 : {history['counts'][2023]} dossiers")
    add(f"2024 : {history['counts'][2024]} dossiers")
    add(f"2025 : {history['counts'][2025]} dossiers retenus, {EXPECTED_READ_2025} lignes lues")
    add(f"2026 : {len(retained)} dossiers retenus, {len(rows)} lignes lues")
    add("")
    add("ENTONNOIR 2026")
    add("Chaque ligne n'est comptée qu'une fois, dans cet ordre.")
    add(f"Lignes lues : {len(rows)}")
    add(f"Doublons internes : {counts['doublon']}")
    add(f"Dates de dépôt et de délivrance illisibles : {counts['dates']}")
    add(f"Déjà en 2025 : {counts['deja_2025']}")
    add(f"Déjà en 2023 ou 2024 : {counts['deja_ancien']}")
    add(f"Commune inconnue : {counts['commune']}")
    add(f"Retenues : {counts['retenue']}")
    add(
        "NICAD présent à la fois en 2025 et dans 2023 ou 2024 : "
        f"{nicad_both} NICAD distincts. Ils sont comptés avec 2025."
    )
    add("")
    add("COMMUNES")
    add(
        "2023, 2024 et 2025 comptent les dossiers déjà importés. "
        "Les trois colonnes 2026 comptent des lignes : le préfixe NICAD, "
        "puis les retenus qui ont ce NICAD, puis les retenus dont la commune "
        "vient seulement de la zone."
    )
    add(
        f"{'Commune':<12} {'2023':>8} {'2024':>8} {'2025':>8} "
        f"{'NICAD lus':>10} {'retenus NICAD':>14} {'retenus zone':>13}"
    )
    for commune_id, name in COMMUNE_NAME.items():
        read = sum(1 for item in rows if item["commune_nicad"] == commune_id)
        kept = sum(1 for item in retained if item["commune"] == commune_id and item["nicad"])
        by_zone = sum(1 for item in retained if item["commune"] == commune_id and not item["nicad"])
        add(
            f"{name:<12} "
            f"{history['communes'][(2023, commune_id)]:>8} "
            f"{history['communes'][(2024, commune_id)]:>8} "
            f"{history['communes'][(2025, commune_id)]:>8} "
            f"{read:>10} {kept:>14} {by_zone:>13}"
        )
    add(
        "Sans NICAD regroupe, pour 2023 à 2025, les terrains importés sans NICAD. "
        "En 2026, la colonne zone dit combien de retenus n'ont pas de NICAD lisible."
    )
    add("")
    add("ZONES ÉCRITES DANS LE REGISTRE 2026")
    zones = Counter(item["zone"] or "(vide)" for item in rows)
    for name, count in zones.most_common():
        add(f"{count:4}  {name}")
    add("")
    add("DÉLAIS DU REGISTRE 2026")
    add("Calculés sur toutes les lignes dont les deux dates sont lisibles, y compris les lignes écartées de l'import.")

    def pairs(left, right):
        found = []
        for item in rows:
            start = item[left]
            end = item[right]
            if start and end:
                found.append((item, (end - start).days))
        return found

    depot_deliv = pairs("depot", "deliv")
    deliv_retrait = pairs("deliv", "retrait")
    depot_retrait = pairs("depot", "retrait")
    add(f"Dépôt → délivrance : {delay_stats(depot_deliv)}")
    add(f"Délivrance → retrait : {delay_stats(deliv_retrait)}")
    add(f"Dépôt → retrait : {delay_stats(depot_retrait)}")
    kept_pairs = [
        (item["creation"], item["deliv"])
        for item in retained
        if item["date_source"] == "depot" and item["depot"] and item["deliv"]
    ]
    if kept_pairs:
        values = sorted((end - start).days for start, end in kept_pairs)
        median = statistics.median(values)
        median_txt = str(int(median)) if float(median).is_integer() else f"{median:.1f}".replace(".", ",")
        add(f"Dépôt → délivrance, lignes retenues seulement : n={len(values)}, médiane {median_txt} j")
    add("")
    add("RECUPERE PAR AGENT")
    add("RECUPËRE est compté avec RECUPERE. La colonne En signature est vide.")
    agents = sorted({item["agent"] or "(vide)" for item in rows})
    add(f"{'Agent':<12} {'Lignes':>8} {'Récupéré':>10} {'Part':>8}")
    for agent in agents:
        group = [item for item in rows if (item["agent"] or "(vide)") == agent]
        got = sum(1 for item in group if item["recupere"])
        part = f"{got / len(group) * 100:.1f}".replace(".", ",") + " %"
        add(f"{agent:<12} {len(group):>8} {got:>10} {part:>8}")
    other_obs = Counter(
        item["obs"] for item in rows if item["obs"] and not item["recupere"]
    )
    if other_obs:
        add("Autres observations : " + ", ".join(f"{name} ({count})" for name, count in other_obs.most_common()))
    add("")
    add("QUALITÉ")
    add(f"NICAD vides : {sum(1 for item in rows if item['nicad_status'] == 'vide')}")
    add(f"NICAD illisibles : {sum(1 for item in rows if item['nicad_status'] == 'illisible')}")
    add(f"Surfaces lues dans la colonne lot : {sum(1 for item in rows if item['surface'])}")
    add(f"Personnes morales : {sum(1 for item in rows if item['company'])}")
    add(
        "Nom de société écrit dans la colonne NOM, laissé en personne physique : "
        f"{sum(1 for item in rows if item['company_nom'] and not item['company'])}"
    )
    add(f"N° d'ordre illisible : {sum(1 for item in rows if not (item['ordre'] or '').isdigit())}")
    add(
        "Commune de la zone différente du préfixe NICAD : "
        f"{sum(1 for item in rows if item['commune_nicad'] and item['commune_zone'] and item['commune_nicad'] != item['commune_zone'])}"
    )
    add(
        "NICAD nouveau avec un titre et un lot déjà connus, conservés : "
        f"{sum(1 for item in retained if item['nouveau_titre'])}"
    )
    add("")

    def section(title, items, formatter):
        add(title)
        if not items:
            add("(aucune)")
        else:
            for item in items:
                add(formatter(item))
        add("")

    section(
        f"DOUBLONS INTERNES ({counts['doublon']})",
        [item for item in rows if item["reason"] == "doublon"],
        lambda item: describe(item, f"gardé l.{item['dup_of']} ({item['dup_kind']})"),
    )
    section(
        f"DATES ILLISIBLES ({counts['dates']})",
        [item for item in rows if item["reason"] == "dates"],
        lambda item: describe(item, "dépôt et délivrance illisibles"),
    )
    section(
        f"DÉJÀ EN 2025 ({counts['deja_2025']})",
        [item for item in rows if item["reason"] == "deja_2025"],
        lambda item: describe(item, _base_label(history, item)),
    )
    section(
        f"DÉJÀ EN 2023 OU 2024 ({counts['deja_ancien']})",
        [item for item in rows if item["reason"] == "deja_ancien"],
        lambda item: describe(item, _base_label(history, item)),
    )
    section(
        f"COMMUNE INCONNUE ({counts['commune']})",
        [item for item in rows if item["reason"] == "commune"],
        lambda item: describe(item, f"nicad {item['nicad_status']}"),
    )
    section(
        "NICAD NOUVEAU, TITRE ET LOT DÉJÀ CONNUS (conservés)",
        [item for item in retained if item["nouveau_titre"]],
        lambda item: describe(item, _base_label(history, item) + f" | nouveau dossier {item['dossier']}"),
    )
    section(
        "COMMUNE DE LA ZONE DIFFÉRENTE DU NICAD",
        [
            item
            for item in rows
            if item["commune_nicad"] and item["commune_zone"] and item["commune_nicad"] != item["commune_zone"]
        ],
        lambda item: describe(
            item,
            f"fichier={COMMUNE_NAME[item['commune_zone']]} retenu={COMMUNE_NAME[item['commune_nicad']]}",
        ),
    )
    section(
        "NICAD COMPLET ÉCRIT DANS UNE CASE ILLISIBLE",
        [item for item in rows if item["embedded"]],
        lambda item: describe(
            item,
            "tokens="
            + ",".join(item["embedded"])
            + (
                f" | déjà {', '.join(str(year) for year in sorted(item['embedded_years']))}"
                if item["embedded_years"]
                else " | aucun de ces NICAD n'est déjà en base"
            )
            + f" | issue={item['reason']}",
        ),
    )
    section(
        "NICAD ILLISIBLE",
        [item for item in rows if item["nicad_status"] == "illisible"],
        lambda item: describe(item, f"issue={item['reason']}"),
    )
    section(
        "DÉLAIS NÉGATIFS DÉPÔT → DÉLIVRANCE",
        [item for item, days in depot_deliv if days < 0],
        lambda item: describe(
            item,
            f"{item['depot'].isoformat()} → {item['deliv'].isoformat()} ({(item['deliv'] - item['depot']).days} j)",
        ),
    )

    repeated = []
    grouped = defaultdict(list)
    for item in rows:
        if item["titre"] and not item["lot"] and not item["nicad"]:
            grouped[item["titre"]].append(item)
    for titre, group in grouped.items():
        if len(group) > 1:
            repeated.extend(group)
    section(
        "MÊME TITRE, SANS LOT ET SANS NICAD, PLUSIEURS LIGNES (non écartées par la clé)",
        repeated,
        lambda item: describe(item, f"issue={item['reason']}"),
    )

    multi = defaultdict(list)
    for item in rows:
        if item["tl"] and item["nicad"]:
            multi[item["tl"]].append(item)
    mixed = []
    for group in multi.values():
        nicads = {item["nicad"] for item in group}
        if len(nicads) > 1:
            mixed.extend(group)
    section(
        "MÊME TITRE ET LOT, NICAD DIFFÉRENTS DANS LE REGISTRE",
        mixed,
        lambda item: describe(item, f"issue={item['reason']}"),
    )

    OUT_TXT.write_text("\n".join(lines) + "\n", encoding="utf-8")


def _base_label(history, item) -> str:
    year, ident = base_id(history, item)
    if year is None:
        return "déjà en base"
    years = sorted(item["nicad_years"] or item["tl_years"])
    listed = ", ".join(str(value) for value in years)
    return f"import {listed}, id {ident}"


def validate(retained):
    parsed = list(iter_rows(OUT_SQL, "terrains"))
    if len(parsed) != len(retained):
        raise SystemExit(f"relecture SQL: {len(parsed)} terrains, {len(retained)} retenus")
    nicads = []
    ordres = []
    for fields in iter_rows(OUT_SQL, "dossiers"):
        if len(fields) != 10:
            raise SystemExit("dossier généré mal formé")
        ordres.append(int(token(fields[2])))
    for index, fields in enumerate(parsed):
        item = retained[index]
        if len(fields) != 28:
            raise SystemExit("terrain généré mal formé")
        nicad = token(fields[13])
        if nicad != item["nicad"]:
            raise SystemExit(f"NICAD divergent ligne {item['excel']}")
        if nicad:
            nicads.append(nicad)
            parsed_nicad, status = norm_nicad(nicad)
            if status != "ok" or parsed_nicad["commune"] != int(token(fields[22])):
                raise SystemExit(f"NICAD incohérent {nicad}")
            if token(fields[5]) != parsed_nicad["section"] or token(fields[1]) != parsed_nicad["parcelle"]:
                raise SystemExit(f"découpage NICAD incohérent {nicad}")
            if (parsed_nicad["appart"] or None) != (token(fields[9]) or None):
                raise SystemExit(f"appartement incohérent {nicad}")
        elif token(fields[14]) != "Sans NICAD":
            raise SystemExit("statut Sans NICAD manquant")
        if int(token(fields[19])) != 1 or int(token(fields[20])) != 1 or int(token(fields[21])) != 1:
            raise SystemExit("localisation inattendue")
    if len(nicads) != len(set(nicads)):
        raise SystemExit("NICAD en double dans l'import")
    if ordres != list(range(START_ORDRE, START_ORDRE + len(retained))):
        raise SystemExit("n° d'ordre non continus")
    titulaires = list(iter_rows(OUT_SQL, "titulaires"))
    refs = list(iter_rows(OUT_SQL, "references_cadastrales"))
    if not (len(titulaires) == len(refs) == len(retained)):
        raise SystemExit("les quatre tables n'ont pas le même nombre de lignes")
    for fields in titulaires:
        if len(fields) != 34:
            raise SystemExit(f"titulaire à {len(fields)} champs")
    for fields in refs:
        if len(fields) != 11:
            raise SystemExit(f"référence à {len(fields)} champs")


def main():
    history = load_history()
    rows = load_xlsx()
    classify(rows, history)
    retained = build_retained(rows)
    write_sql(rows, retained, history)
    write_report(rows, retained, history)
    validate(retained)
    counts = Counter(item["reason"] for item in rows)
    print(f"lues={len(rows)} retenues={len(retained)} {dict(counts)}")
    print(f"sql={OUT_SQL}")
    print(f"rapport={OUT_TXT}")


if __name__ == "__main__":
    main()
