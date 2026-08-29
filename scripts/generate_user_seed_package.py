#!/usr/bin/env python3
from __future__ import annotations

import json
import math
import random
import zipfile
from datetime import datetime, timedelta
from pathlib import Path
from xml.sax.saxutils import escape

from PIL import Image, ImageDraw, ImageFont


ROOT = Path(__file__).resolve().parents[1]
OUTPUT_DIR = ROOT / "outputs" / "user-seed-demo"
AVATAR_DIR = OUTPUT_DIR / "avatars"
USER_COUNT = 100
AVATAR_COUNT = 40
WORKBOOK_PATH = OUTPUT_DIR / f"users_seed_{USER_COUNT}.xlsx"

RANDOM = random.Random(20260828)

FIRST_NAMES = [
    "Ada",
    "Tunde",
    "Amara",
    "Kelechi",
    "Ife",
    "Damilola",
    "Chidi",
    "Zainab",
    "Tomi",
    "Nneka",
    "Bayo",
    "Sade",
    "Kunle",
    "Efe",
    "Mariam",
    "Obinna",
    "Aisha",
    "Femi",
    "Chinwe",
    "Seun",
    "Ngozi",
    "Yusuf",
    "Lola",
    "Emeka",
    "Kemi",
    "Ibrahim",
    "Uche",
    "Temi",
    "Nadia",
    "Fola",
    "Chioma",
    "Musa",
    "Rita",
    "Seyi",
    "Tara",
    "Niyi",
    "Halima",
    "Jide",
    "Bisi",
    "Somto",
    "Wale",
    "Anita",
    "David",
    "Esther",
    "Victor",
    "Morenike",
    "Ayo",
    "Fatima",
    "Ikenna",
    "Joy",
    "Samuel",
    "Maryam",
    "Tope",
    "Ijeoma",
    "Gbenga",
    "Blessing",
    "Nosa",
    "Pearl",
    "Chika",
    "Mide",
    "Hadiza",
    "Nonso",
    "Bukola",
    "Irene",
    "Lekan",
    "Tosin",
    "Nkem",
    "Rasheed",
    "Dara",
    "Karo",
    "Enenche",
    "Ochanya",
    "Oche",
    "Agbenu",
    "Onuh",
    "Ameh",
    "Ene",
    "Ogbole",
    "Inalegwu",
    "Ojoma",
    "Terhemba",
    "Dooshima",
    "Tersoo",
    "Msendoo",
    "Orngu",
    "Iorfa",
    "Seember",
    "Kpamor",
    "Torkwase",
    "Mlumun",
    "Tari",
    "Timi",
    "Ebi",
    "Seiyefa",
    "Preye",
    "Tonye",
    "Boma",
    "Douye",
    "Tamara",
    "Oyin",
]

LAST_NAMES = [
    "Okafor",
    "Adebayo",
    "Eze",
    "Balogun",
    "Okonkwo",
    "Ibrahim",
    "Nwosu",
    "Ogunleye",
    "Abubakar",
    "Adeyemi",
    "Udo",
    "Onyeka",
    "Bello",
    "Olawale",
    "Chukwu",
    "Afolabi",
    "Okoro",
    "Lawal",
    "Nnamani",
    "Usman",
    "Agada",
    "Ameh",
    "Audu",
    "Oche",
    "Onuh",
    "Ogwuche",
    "Elaigwu",
    "Enenche",
    "Ortom",
    "Iorhemba",
    "Tyav",
    "Tarka",
    "Utaan",
    "Shija",
    "Tor",
    "Akume",
    "Dokubo",
    "Tamuno",
    "Amachree",
    "Briggs",
    "Waritimi",
    "Owei",
    "Ekiyor",
    "Alaibe",
    "Omubo",
    "Daukoru",
]

NICKNAMES = [
    "Ace",
    "Bee",
    "Kay",
    "Tee",
    "Nini",
    "Dara",
    "Zee",
    "CJ",
    "Mimi",
    "Sunny",
    "Prof",
    "Star",
]

HOUSES = ["Blue", "Green", "Red", "Yellow", "Purple"]
CITIES = [
    ("Lagos", "Lagos", "Lagos Zone", "Lekki Phase 1"),
    ("Abuja", "FCT", "North Central Zone", "Wuse 2"),
    ("Port Harcourt", "Rivers", "South South Zone", "GRA Phase 2"),
    ("Enugu", "Enugu", "South East Zone", "Independence Layout"),
    ("Ibadan", "Oyo", "South West Zone", "Bodija"),
    ("Kano", "Kano", "North West Zone", "Nassarawa"),
    ("London", "England", "Diaspora Zone", "Canary Wharf"),
    ("Houston", "Texas", "Diaspora Zone", "Midtown"),
]
EMPLOYMENT = ["Employed", "Founder", "Consultant", "Self-employed", "Postgraduate Student"]
OCCUPATIONS = [
    ("Product Management", "Technology", "Product Manager"),
    ("Software Engineering", "Technology", "Software Engineer"),
    ("Medicine", "Healthcare", "Medical Doctor"),
    ("Finance", "Financial Services", "Finance Manager"),
    ("Legal Practice", "Legal Services", "Legal Counsel"),
    ("Education", "Education", "Lecturer"),
    ("Marketing", "Media & Communications", "Brand Strategist"),
    ("Operations", "Logistics", "Operations Lead"),
    ("Architecture", "Construction", "Architect"),
    ("Entrepreneurship", "Retail", "Founder"),
]
COMPANIES = [
    "Nubiaville",
    "CivicBridge",
    "BluePeak Labs",
    "Northstar Health",
    "Sterling Advisory",
    "Maple & Stone",
    "Greenline Energy",
    "BrightPath Learning",
    "CloudNest",
    "Bridgewell Group",
]
ROLES = ["member"] * 58 + ["admin", "approval admin", "content admin", "storekeeper admin", "event admin", "finance admin"] * 2
DUES = ["paid"] * 50 + ["owing"] * 12 + ["overdue"] * 5 + ["unknown"] * 3

HEADERS = [
    "id",
    "user_id",
    "user_code",
    "first_name",
    "last_name",
    "fullname",
    "email",
    "phone",
    "alternative_phone",
    "name_in_school",
    "nick_name",
    "graduation_year",
    "house_color",
    "birth_date",
    "bio",
    "avatar",
    "local_avatar_file",
    "residential_address",
    "area",
    "city",
    "state",
    "zone_name",
    "employment_status",
    "occupation",
    "industry_sector",
    "years_of_experience",
    "is_coordinator",
    "is_volunteer",
    "is_approved",
    "email_verified",
    "active",
    "user_role",
    "dues_status",
    "created_at",
    "approved_at",
    "profile_city",
    "profile_country",
    "profile_current_company",
    "profile_current_position",
    "profile_linkedin",
    "profile_twitter",
    "profile_facebook",
    "profile_instagram",
    "profile_tiktok",
    "profile_website",
    "profile_is_visible",
    "profile_field_visibility",
]

FIELD_MAP_ROWS = [
    ["Column", "Backend/frontend expectation"],
    ["id / user_id", "Primary identifier; frontend uses both names in different adapters."],
    ["fullname", "Display name for directory, profile, admin, messages, and birthday references."],
    ["avatar", "Backend upload/storage path. Frontend prefixes VITE_API_BASE_URL for relative paths."],
    ["local_avatar_file", "File path in this package for backend upload convenience; not an API field."],
    ["graduation_year", "Used for class filters, profile display, and dashboard suggested classmates."],
    ["is_approved, email_verified, active", "Use 1/0 values for approval, verification, and account status."],
    ["user_role", "member/admin/admin-category value. Frontend maps admin to super admin."],
    ["profile_*", "Nested profile fields can be imported into user_profiles or equivalent table."],
    ["profile_field_visibility", "JSON object consumed by privacy adapter; values: public, members, private."],
]


def initials(first: str, last: str) -> str:
    return f"{first[:1]}{last[:1]}".upper()


def avatar_colors(index: int) -> tuple[str, str]:
    palettes = [
        ("#1D4ED8", "#DBEAFE"),
        ("#047857", "#D1FAE5"),
        ("#B45309", "#FEF3C7"),
        ("#BE123C", "#FFE4E6"),
        ("#6D28D9", "#EDE9FE"),
        ("#0F766E", "#CCFBF1"),
        ("#334155", "#E2E8F0"),
        ("#A21CAF", "#FAE8FF"),
    ]
    return palettes[index % len(palettes)]


def load_font(size: int) -> ImageFont.ImageFont:
    for path in [
        "/System/Library/Fonts/Supplemental/Arial Bold.ttf",
        "/System/Library/Fonts/Supplemental/Arial.ttf",
        "/Library/Fonts/Arial.ttf",
    ]:
        font_path = Path(path)
        if font_path.exists():
            return ImageFont.truetype(str(font_path), size=size)
    return ImageFont.load_default()


def generate_avatar(path: Path, first: str, last: str, index: int) -> None:
    size = 512
    bg, fg = avatar_colors(index)
    image = Image.new("RGB", (size, size), bg)
    draw = ImageDraw.Draw(image)

    for radius, alpha in [(230, 40), (170, 35), (110, 30)]:
        overlay = Image.new("RGBA", (size, size), (255, 255, 255, 0))
        overlay_draw = ImageDraw.Draw(overlay)
        x = 256 + int(math.sin(index + radius) * 90)
        y = 256 + int(math.cos(index + radius) * 80)
        overlay_draw.ellipse((x - radius, y - radius, x + radius, y + radius), fill=(255, 255, 255, alpha))
        image = Image.alpha_composite(image.convert("RGBA"), overlay).convert("RGB")
        draw = ImageDraw.Draw(image)

    avatar_font = load_font(152)
    label = initials(first, last)
    bbox = draw.textbbox((0, 0), label, font=avatar_font)
    x = (size - (bbox[2] - bbox[0])) / 2
    y = (size - (bbox[3] - bbox[1])) / 2 - 10
    draw.ellipse((76, 76, 436, 436), fill=fg)
    draw.text((x, y), label, fill=bg, font=avatar_font)
    image.save(path, "PNG", optimize=True)


def make_users() -> list[dict[str, object]]:
    users = []
    base_date = datetime(2026, 6, 1, 9, 0, 0)
    for index in range(USER_COUNT):
        user_id = 1001 + index
        first = FIRST_NAMES[index % len(FIRST_NAMES)]
        last = LAST_NAMES[index % len(LAST_NAMES)]
        full = f"{first} {last}"
        grad_year = 1995 + (index % 30)
        city, state, zone, area = CITIES[index % len(CITIES)]
        occupation, sector, position = OCCUPATIONS[index % len(OCCUPATIONS)]
        company = COMPANIES[index % len(COMPANIES)]
        has_avatar = index < AVATAR_COUNT
        avatar_name = f"user_{user_id}.png"
        visibility = {
            "avatar": "public",
            "phone": "members" if index % 3 else "private",
            "alternative_phone": "private",
            "birth_date": "private",
            "residential_address": "private",
            "area": "members",
            "city": "public",
            "state": "public",
            "employment_status": "public",
            "socials": "public" if index % 4 else "members",
            "years_of_experience": "public",
        }
        created_at = base_date + timedelta(days=index)
        row = {
            "id": user_id,
            "user_id": user_id,
            "user_code": f"ALU-{user_id}",
            "first_name": first,
            "last_name": last,
            "fullname": full,
            "email": f"{first.lower()}.{last.lower()}{user_id}@example.com",
            "phone": f"+23480{10000000 + index * 137:08d}",
            "alternative_phone": f"+23490{20000000 + index * 149:08d}" if index % 2 == 0 else "",
            "name_in_school": f"{first} {last[:1]}.",
            "nick_name": NICKNAMES[index % len(NICKNAMES)] if index % 5 != 0 else "",
            "graduation_year": grad_year,
            "house_color": HOUSES[index % len(HOUSES)],
            "birth_date": f"{1975 + (index % 24)}-{(index % 12) + 1:02d}-{(index % 27) + 1:02d}",
            "bio": f"{full} is a {occupation.lower()} professional based in {city}, active in mentoring, networking, and community projects.",
            "avatar": f"uploads/avatars/{avatar_name}" if has_avatar else "default.png",
            "local_avatar_file": f"avatars/{avatar_name}" if has_avatar else "",
            "residential_address": f"{12 + index} Community Close",
            "area": area,
            "city": city,
            "state": state,
            "zone_name": zone,
            "employment_status": EMPLOYMENT[index % len(EMPLOYMENT)],
            "occupation": occupation,
            "industry_sector": sector,
            "years_of_experience": 2 + (index % 22),
            "is_coordinator": "1" if index % 14 == 0 else "0",
            "is_volunteer": "1" if index % 4 == 0 else "0",
            "is_approved": "0" if index in {64, 65, 66, 67, 68, 69} else "1",
            "email_verified": "1",
            "active": "0" if index in {62, 63} else "1",
            "user_role": ROLES[index % len(ROLES)],
            "dues_status": DUES[index % len(DUES)],
            "created_at": created_at.isoformat() + "Z",
            "approved_at": (created_at + timedelta(days=1)).isoformat() + "Z" if index < 64 else "",
            "profile_city": city,
            "profile_country": "Nigeria" if city not in {"London", "Houston"} else ("United Kingdom" if city == "London" else "United States"),
            "profile_current_company": company,
            "profile_current_position": position,
            "profile_linkedin": f"https://linkedin.com/in/{first.lower()}-{last.lower()}-{user_id}",
            "profile_twitter": f"https://x.com/{first.lower()}{user_id}" if index % 3 == 0 else "",
            "profile_facebook": "",
            "profile_instagram": f"https://instagram.com/{first.lower()}.{last.lower()}" if index % 4 == 0 else "",
            "profile_tiktok": "",
            "profile_website": f"https://{first.lower()}{last.lower()}.example.com" if index % 6 == 0 else "",
            "profile_is_visible": "1",
            "profile_field_visibility": json.dumps(visibility, separators=(",", ":")),
        }
        users.append(row)
    return users


def cell_ref(row: int, col: int) -> str:
    letters = ""
    while col:
        col, rem = divmod(col - 1, 26)
        letters = chr(65 + rem) + letters
    return f"{letters}{row}"


def sheet_xml(rows: list[list[object]], col_widths: list[float] | None = None, autofilter: bool = True) -> str:
    max_col = max((len(row) for row in rows), default=1)
    col_widths = col_widths or [18.0] * max_col
    cols = "".join(
        f'<col min="{idx}" max="{idx}" width="{width}" customWidth="1"/>'
        for idx, width in enumerate(col_widths[:max_col], start=1)
    )
    sheet_rows = []
    for r_idx, row in enumerate(rows, start=1):
        height = ' ht="32" customHeight="1"' if r_idx == 1 else ""
        cells = []
        for c_idx, value in enumerate(row, start=1):
            ref = cell_ref(r_idx, c_idx)
            style = ' s="1"' if r_idx == 1 else ""
            if isinstance(value, (int, float)) and not isinstance(value, bool):
                cells.append(f'<c r="{ref}"{style}><v>{value}</v></c>')
            else:
                text = escape("" if value is None else str(value))
                cells.append(f'<c r="{ref}" t="inlineStr"{style}><is><t>{text}</t></is></c>')
        sheet_rows.append(f'<row r="{r_idx}"{height}>{"".join(cells)}</row>')
    dimension = f"A1:{cell_ref(len(rows), max_col)}"
    filter_xml = f'<autoFilter ref="{dimension}"/>' if autofilter and len(rows) > 1 else ""
    return f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <dimension ref="{dimension}"/>
  <sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft"/></sheetView></sheetViews>
  <sheetFormatPr defaultRowHeight="18"/>
  <cols>{cols}</cols>
  <sheetData>{"".join(sheet_rows)}</sheetData>
  {filter_xml}
</worksheet>'''


def workbook_xml(sheet_names: list[str]) -> str:
    sheets = "".join(
        f'<sheet name="{escape(name)}" sheetId="{idx}" r:id="rId{idx}"/>'
        for idx, name in enumerate(sheet_names, start=1)
    )
    return f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>{sheets}</sheets>
</workbook>'''


def workbook_rels(sheet_count: int) -> str:
    relationships = []
    for idx in range(1, sheet_count + 1):
        relationships.append(
            f'<Relationship Id="rId{idx}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet{idx}.xml"/>'
        )
    relationships.append(
        f'<Relationship Id="rId{sheet_count + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    )
    return f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  {"".join(relationships)}
</Relationships>'''


def content_types(sheet_count: int) -> str:
    overrides = "".join(
        f'<Override PartName="/xl/worksheets/sheet{idx}.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        for idx in range(1, sheet_count + 1)
    )
    return f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  {overrides}
</Types>'''


def root_rels() -> str:
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>'''


def styles_xml() -> str:
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>
    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF0F4C81"/><bgColor indexed="64"/></patternFill></fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border><left style="thin"><color rgb="FFD9E2EC"/></left><right style="thin"><color rgb="FFD9E2EC"/></right><top style="thin"><color rgb="FFD9E2EC"/></top><bottom style="thin"><color rgb="FFD9E2EC"/></bottom><diagonal/></border>
  </borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
  </cellXfs>
  <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>'''


def write_xlsx(users: list[dict[str, object]]) -> None:
    user_rows = [HEADERS] + [[user.get(header, "") for header in HEADERS] for user in users]
    notes_rows = [
        ["Dataset", f"{USER_COUNT} frontend-compatible alumni/user seed records"],
        ["Generated", datetime.now().isoformat(timespec="seconds")],
        ["Avatar count", f"{AVATAR_COUNT} PNG files in avatars/; rows without images use default.png"],
        ["Primary read endpoint", "POST /api/get_users_by_action"],
        ["Single user payload", '{"user_id":"1001"}'],
        ["Admin all users payload", '{"action_type":"all_users"}'],
        ["Current user endpoint", "POST /api/get_user_profile with {\"user_id\":\"1001\"}"],
        ["List response shape", "{\"users\":[...]} or {\"data\":[...]}"],
        ["Import tip", "Use avatar as backend storage path; use local_avatar_file to locate package image files."],
    ]
    lookups_rows = FIELD_MAP_ROWS

    sheet_names = ["Users", "Import Notes", "Field Map"]
    sheet_payloads = [
        sheet_xml(user_rows, [12, 12, 14, 16, 16, 24, 34, 19, 19, 18, 14, 16, 14, 14, 58, 30, 24, 28, 18, 18, 18, 20, 20, 24, 24, 18, 14, 14, 12, 14, 10, 18, 14, 24, 24, 18, 18, 28, 26, 42, 28, 28, 34, 24, 34, 18, 80]),
        sheet_xml(notes_rows, [28, 90], False),
        sheet_xml(lookups_rows, [28, 100], True),
    ]

    with zipfile.ZipFile(WORKBOOK_PATH, "w", zipfile.ZIP_DEFLATED) as zf:
        zf.writestr("[Content_Types].xml", content_types(len(sheet_names)))
        zf.writestr("_rels/.rels", root_rels())
        zf.writestr("xl/workbook.xml", workbook_xml(sheet_names))
        zf.writestr("xl/_rels/workbook.xml.rels", workbook_rels(len(sheet_names)))
        zf.writestr("xl/styles.xml", styles_xml())
        for idx, payload in enumerate(sheet_payloads, start=1):
            zf.writestr(f"xl/worksheets/sheet{idx}.xml", payload)


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    AVATAR_DIR.mkdir(parents=True, exist_ok=True)

    users = make_users()
    for idx, user in enumerate(users[:AVATAR_COUNT]):
        generate_avatar(AVATAR_DIR / f"user_{user['user_id']}.png", str(user["first_name"]), str(user["last_name"]), idx)

    write_xlsx(users)

    print(f"Workbook: {WORKBOOK_PATH}")
    print(f"Users: {len(users)}")
    print(f"Avatars: {len(list(AVATAR_DIR.glob('*.png')))}")


if __name__ == "__main__":
    main()
