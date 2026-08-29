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
OUTPUT_DIR = ROOT / "outputs" / "event-seed-demo"
BANNER_DIR = OUTPUT_DIR / "event-banners"
EVENT_COUNT = 80
BANNER_COUNT = 40
WORKBOOK_PATH = OUTPUT_DIR / f"events_seed_{EVENT_COUNT}.xlsx"
RANDOM = random.Random(20260828)

EVENT_HEADERS = [
    "id",
    "title",
    "description",
    "event_banner",
    "local_banner_file",
    "start_date",
    "end_date",
    "start_time",
    "end_time",
    "location",
    "is_virtual",
    "virtual_link",
    "attire",
    "category",
    "tags",
    "is_featured",
    "status",
    "visibility",
    "max_attendees",
    "allow_guests",
    "attendee_count",
    "created_by",
    "created_by_name",
    "chapter_id",
    "year",
    "is_approved",
    "has_registration_questions",
    "registration_form_count",
    "my_rsvp",
    "created_at",
    "updated_at",
]

ATTENDEE_HEADERS = [
    "id",
    "event_id",
    "user_id",
    "fullname",
    "email",
    "phone",
    "graduation_year",
    "status",
    "registered_at",
    "guest_count",
    "additional_info",
]

FIELD_MAP_ROWS = [
    ["Column", "Backend/frontend expectation"],
    ["id", "Primary event identifier. Detail view fetches POST /api/get_events with {id}."],
    ["title", "Displayed on event cards, detail pages, admin list, and registration pages."],
    ["description", "Mapped to both frontend description and content."],
    ["event_banner", "Image URL/path used directly by the frontend as Event.image."],
    ["local_banner_file", "Package file path for backend upload convenience; not an API field."],
    ["start_date / event_date", "Frontend accepts start_date first, then event_date fallback."],
    ["start_time / end_time", "Frontend slices to HH:mm if seconds are included."],
    ["status", "upcoming/active -> published; completed/cancelled/draft map directly."],
    ["tags", "JSON array or comma-separated string. Include event_survey to signal forms."],
    ["has_registration_questions", "1/0 flag consumed by the registration form availability logic."],
    ["my_rsvp", "Optional current-user status: going, maybe, not_going."],
    ["attendee_count", "Shown on event cards/detail where available."],
]

NOTE_ROWS = [
    ["Dataset", f"{EVENT_COUNT} frontend-compatible event seed records"],
    ["Generated", datetime.now().isoformat(timespec="seconds")],
    ["Banner count", f"{BANNER_COUNT} PNG files in event-banners/; remaining rows reuse default event image paths"],
    ["Primary read endpoint", "POST /api/get_events"],
    ["All events payload currently sent by app", "{}"],
    ["Single event payload", '{"id":"2001"}'],
    ["User events payload", '{"user_id":"1001"}'],
    ["Attendees endpoint", "POST /api/get_event_attendees"],
    ["Attendees payload", '{"event_id":"2001","status":"going"}; status is optional'],
    ["List response shape", "{\"events\":[...]} or {\"data\":[...]} or bare array"],
]

LOCATIONS = [
    ("Lagos Civic Centre, Victoria Island", "Lagos"),
    ("Transcorp Hilton, Abuja", "Abuja"),
    ("Landmark Event Centre, Lagos", "Lagos"),
    ("The Dome, Port Harcourt", "Port Harcourt"),
    ("International Conference Centre, Abuja", "Abuja"),
    ("MUSON Centre, Lagos", "Lagos"),
    ("Ibom Hall, Uyo", "Uyo"),
    ("Virtual Event", "Online"),
    ("Eko Hotel Convention Centre", "Lagos"),
    ("Enugu Sports Club", "Enugu"),
]

CATEGORIES = [
    "Networking",
    "Mentorship",
    "Fundraising",
    "Career",
    "Wellness",
    "Community",
    "Leadership",
    "Reunion",
]

TITLES = [
    "Lagos Chapter Sunday Brunch",
    "Abuja Business Breakfast",
    "Port Harcourt Karaoke Night",
    "Diaspora Virtual Townhall",
    "Career Growth Roundtable",
    "Mentorship Match Day",
    "Wellness Walk and Breakfast",
    "Founders and Operators Mixer",
    "Regional Chapter Family Picnic",
    "Community Service Day",
    "Class Set Reunion Dinner",
    "Professional Skills Clinic",
    "Legacy Giving Dinner",
    "Young Alumni Welcome Mixer",
    "Policy and Civic Leadership Talk",
    "Creative Industry Hangout",
    "Finance and Investment Masterclass",
    "Healthcare Professionals Forum",
    "Tech Product Demo Night",
    "Book Club and Wine Evening",
    "Volunteer Strategy Workshop",
    "Members Appreciation Lunch",
    "Back to School Support Drive",
    "Independence Day Mixer",
]

USER_NAMES = [
    ("Ada", "Okafor"),
    ("Tunde", "Adebayo"),
    ("Ochanya", "Agada"),
    ("Terhemba", "Ortom"),
    ("Preye", "Dokubo"),
    ("Dooshima", "Tarka"),
    ("Tonye", "Tamuno"),
    ("Ife", "Adeyemi"),
    ("Mariam", "Bello"),
    ("Kelechi", "Eze"),
]

SPECIAL_EVENTS = [
    {
        "title": "Christmas Party 2025",
        "date": "2025-12-21",
        "time": "17:00:00",
        "category": "Social",
        "location": "Eko Hotel Convention Centre",
        "city": "Lagos",
        "status": "completed",
        "attire": "Festive",
        "featured": "0",
        "forms": False,
    },
    {
        "title": "New Year's Eve Gala 2025",
        "date": "2025-12-31",
        "time": "20:00:00",
        "category": "Social",
        "location": "Landmark Event Centre, Lagos",
        "city": "Lagos",
        "status": "completed",
        "attire": "Black tie",
        "featured": "0",
        "forms": False,
    },
    {
        "title": "New Year's Eve Countdown 2026",
        "date": "2026-12-31",
        "time": "20:00:00",
        "category": "Social",
        "location": "Lagos Civic Centre, Victoria Island",
        "city": "Lagos",
        "status": "upcoming",
        "attire": "Cocktail",
        "featured": "1",
        "forms": True,
    },
    {
        "title": "Karaoke and Games Night",
        "date": "2026-09-18",
        "time": "18:30:00",
        "category": "Social",
        "location": "Muri Okunola Park Lounge, Victoria Island",
        "city": "Lagos",
        "status": "upcoming",
        "attire": "Casual",
        "featured": "1",
        "forms": False,
    },
    {
        "title": "Abuja Karaoke Hangout",
        "date": "2026-10-10",
        "time": "18:00:00",
        "category": "Social",
        "location": "Transcorp Hilton, Abuja",
        "city": "Abuja",
        "status": "upcoming",
        "attire": "Smart casual",
        "featured": "0",
        "forms": False,
    },
    {
        "title": "Lagos Chapter Christmas Carol and Dinner",
        "date": "2026-12-20",
        "time": "17:30:00",
        "category": "Social",
        "location": "MUSON Centre, Lagos",
        "city": "Lagos",
        "status": "upcoming",
        "attire": "Festive",
        "featured": "1",
        "forms": True,
    },
    {
        "title": "Valentine's Charity Dinner",
        "date": "2026-02-14",
        "time": "18:00:00",
        "category": "Fundraising",
        "location": "The Wheatbaker, Ikoyi",
        "city": "Lagos",
        "status": "completed",
        "attire": "Formal",
        "featured": "0",
        "forms": False,
    },
    {
        "title": "International Women's Day Leadership Brunch",
        "date": "2026-03-08",
        "time": "11:00:00",
        "category": "Leadership",
        "location": "Civic Centre, Victoria Island",
        "city": "Lagos",
        "status": "completed",
        "attire": "Business casual",
        "featured": "0",
        "forms": True,
    },
    {
        "title": "Easter Family Picnic",
        "date": "2026-04-06",
        "time": "12:00:00",
        "category": "Community",
        "location": "Jabi Lake Park, Abuja",
        "city": "Abuja",
        "status": "completed",
        "attire": "Casual",
        "featured": "0",
        "forms": False,
    },
    {
        "title": "Mid-Year Networking Mixer",
        "date": "2026-06-28",
        "time": "16:00:00",
        "category": "Networking",
        "location": "The George Hotel, Ikoyi",
        "city": "Lagos",
        "status": "completed",
        "attire": "Smart casual",
        "featured": "0",
        "forms": False,
    },
]


def load_font(size: int, bold: bool = False) -> ImageFont.ImageFont:
    candidates = [
        "/System/Library/Fonts/Supplemental/Arial Bold.ttf" if bold else "/System/Library/Fonts/Supplemental/Arial.ttf",
        "/Library/Fonts/Arial.ttf",
    ]
    for candidate in candidates:
        path = Path(candidate)
        if path.exists():
            return ImageFont.truetype(str(path), size=size)
    return ImageFont.load_default()


def banner_palette(index: int) -> tuple[str, str, str]:
    palettes = [
        ("#0F4C81", "#00A6A6", "#F8FAFC"),
        ("#7C2D12", "#F59E0B", "#FFF7ED"),
        ("#14532D", "#22C55E", "#F0FDF4"),
        ("#312E81", "#818CF8", "#EEF2FF"),
        ("#881337", "#FB7185", "#FFF1F2"),
        ("#164E63", "#06B6D4", "#ECFEFF"),
        ("#3F3F46", "#A3E635", "#F7FEE7"),
        ("#4A044E", "#E879F9", "#FDF4FF"),
    ]
    return palettes[index % len(palettes)]


def generate_banner(path: Path, title: str, category: str, index: int) -> None:
    width, height = 1600, 900
    primary, accent, text = banner_palette(index)
    image = Image.new("RGB", (width, height), primary)
    draw = ImageDraw.Draw(image)

    for i in range(12):
        radius = 180 + (i % 4) * 70
        x = int((index * 97 + i * 211) % width)
        y = int((index * 131 + i * 167) % height)
        color = accent if i % 2 == 0 else "#FFFFFF"
        alpha = 55 if i % 2 == 0 else 22
        overlay = Image.new("RGBA", (width, height), (0, 0, 0, 0))
        overlay_draw = ImageDraw.Draw(overlay)
        overlay_draw.ellipse((x - radius, y - radius, x + radius, y + radius), fill=(*hex_to_rgb(color), alpha))
        image = Image.alpha_composite(image.convert("RGBA"), overlay).convert("RGB")
        draw = ImageDraw.Draw(image)

    title_font = load_font(74, bold=True)
    meta_font = load_font(34, bold=True)
    small_font = load_font(28)
    draw.rounded_rectangle((92, 92, 1508, 808), radius=34, outline=text, width=4)
    draw.text((130, 135), category.upper(), fill=accent, font=meta_font)
    wrapped = wrap_text(title, 28)
    y = 255
    for line in wrapped:
        draw.text((130, y), line, fill=text, font=title_font)
        y += 88
    draw.text((130, 728), "Alumni Portal Event", fill=text, font=small_font)
    image.save(path, "PNG", optimize=True)


def hex_to_rgb(value: str) -> tuple[int, int, int]:
    clean = value.lstrip("#")
    return tuple(int(clean[i : i + 2], 16) for i in (0, 2, 4))


def wrap_text(text: str, max_chars: int) -> list[str]:
    words = text.split()
    lines: list[str] = []
    current: list[str] = []
    for word in words:
        candidate = " ".join([*current, word])
        if len(candidate) > max_chars and current:
            lines.append(" ".join(current))
            current = [word]
        else:
            current.append(word)
    if current:
        lines.append(" ".join(current))
    return lines[:4]


def make_events() -> list[dict[str, object]]:
    events: list[dict[str, object]] = []
    base_start = datetime(2026, 9, 6, 10, 0, 0)
    statuses = ["upcoming"] * 38 + ["active"] * 8 + ["completed"] * 24 + ["cancelled"] * 5 + ["draft"] * 5

    for index in range(EVENT_COUNT):
        event_id = 2001 + index
        special = SPECIAL_EVENTS[index] if index < len(SPECIAL_EVENTS) else None

        if special:
            title = str(special["title"])
            category = str(special["category"])
            location = str(special["location"])
            city = str(special["city"])
            start = datetime.strptime(
                f"{special['date']} {special['time']}",
                "%Y-%m-%d %H:%M:%S",
            )
            status = str(special["status"])
            attire = str(special["attire"])
            featured = str(special["featured"])
            has_forms = bool(special["forms"])
        else:
            generated_index = index - len(SPECIAL_EVENTS)
            title_year = 2026 + ((generated_index + 4) // 36)
            title = f"{TITLES[generated_index % len(TITLES)]} {title_year}"
            category = CATEGORIES[(generated_index + 2) % len(CATEGORIES)]
            location, city = LOCATIONS[(generated_index + 3) % len(LOCATIONS)]
            start = base_start + timedelta(days=generated_index * 9)
            status = statuses[index % len(statuses)]
            attire = ["Smart casual", "Business casual", "Traditional", "Formal", "Casual"][
                index % 5
            ]
            featured = "1" if index in {12, 24, 40, 56} else "0"
            has_forms = index % 5 == 0

        is_virtual = "1" if city == "Online" or "Virtual" in title else "0"
        end = start + timedelta(hours=4 if is_virtual == "0" else 2)
        has_banner = index < BANNER_COUNT
        banner_name = f"event_{event_id}.png"
        tags = [category.lower(), city.lower().replace(" ", "-")]
        if has_forms:
            tags.append("event_survey")

        attendee_count = 18 + (index * 7) % 135
        max_attendees = max(attendee_count + 20, 60 + (index % 7) * 25)

        events.append(
            {
                "id": event_id,
                "title": title,
                "description": (
                    f"Join alumni members for {title.lower()} in {city}. "
                    "Expect warm conversations, useful introductions, and a well-planned "
                    "programme for members, guests, and friends of the community."
                ),
                "event_banner": f"uploads/events/{banner_name}" if has_banner else "/images/events/default-event-banner.svg",
                "local_banner_file": f"event-banners/{banner_name}" if has_banner else "",
                "start_date": start.strftime("%Y-%m-%d"),
                "end_date": end.strftime("%Y-%m-%d"),
                "start_time": start.strftime("%H:%M:%S"),
                "end_time": end.strftime("%H:%M:%S"),
                "location": location,
                "is_virtual": is_virtual,
                "virtual_link": f"https://meet.example.com/event-{event_id}" if is_virtual == "1" else "",
                "attire": attire,
                "category": category,
                "tags": json.dumps(tags, separators=(",", ":")),
                "is_featured": featured,
                "status": status,
                "visibility": "public",
                "max_attendees": max_attendees,
                "allow_guests": "1" if index % 3 != 0 else "0",
                "attendee_count": attendee_count,
                "created_by": 1001 + (index % 12),
                "created_by_name": f"{USER_NAMES[index % len(USER_NAMES)][0]} {USER_NAMES[index % len(USER_NAMES)][1]}",
                "chapter_id": 1 + (index % 8),
                "year": start.year,
                "is_approved": "1",
                "has_registration_questions": "1" if has_forms else "0",
                "registration_form_count": 2 if has_forms else 0,
                "my_rsvp": ["going", "maybe", "", "not_going"][index % 4],
                "created_at": (start - timedelta(days=30)).isoformat() + "Z",
                "updated_at": (start - timedelta(days=7)).isoformat() + "Z",
            }
        )

    return events


def make_attendees(events: list[dict[str, object]]) -> list[dict[str, object]]:
    attendees: list[dict[str, object]] = []
    row_id = 1
    statuses = ["going", "going", "going", "maybe", "not_going"]
    for event in events[:50]:
        count = 6 + (int(event["id"]) % 9)
        for offset in range(count):
            user_id = 1001 + ((int(event["id"]) + offset * 3) % 100)
            first, last = USER_NAMES[(user_id + offset) % len(USER_NAMES)]
            registered = datetime.fromisoformat(str(event["created_at"]).replace("Z", "")) + timedelta(days=offset + 1)
            attendees.append(
                {
                    "id": row_id,
                    "event_id": event["id"],
                    "user_id": user_id,
                    "fullname": f"{first} {last}",
                    "email": f"{first.lower()}.{last.lower()}{user_id}@example.com",
                    "phone": f"+23480{10000000 + (user_id - 1001) * 137:08d}",
                    "graduation_year": 1995 + ((user_id - 1001) % 30),
                    "status": statuses[(offset + int(event["id"])) % len(statuses)],
                    "registered_at": registered.isoformat() + "Z",
                    "guest_count": offset % 3 if str(event["allow_guests"]) == "1" else 0,
                    "additional_info": "Looking forward to attending." if offset % 4 == 0 else "",
                }
            )
            row_id += 1
    return attendees


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


def write_xlsx(events: list[dict[str, object]], attendees: list[dict[str, object]]) -> None:
    event_rows = [EVENT_HEADERS] + [[event.get(header, "") for header in EVENT_HEADERS] for event in events]
    attendee_rows = [ATTENDEE_HEADERS] + [[attendee.get(header, "") for header in ATTENDEE_HEADERS] for attendee in attendees]
    sheet_names = ["Events", "Event Attendees", "Import Notes", "Field Map"]
    sheet_payloads = [
        sheet_xml(event_rows, [10, 38, 74, 34, 26, 14, 14, 13, 13, 38, 12, 34, 18, 18, 34, 13, 14, 14, 15, 14, 15, 14, 24, 12, 12, 12, 24, 20, 12, 25, 25]),
        sheet_xml(attendee_rows, [10, 12, 12, 24, 34, 18, 16, 14, 25, 13, 40]),
        sheet_xml(NOTE_ROWS, [28, 100], False),
        sheet_xml(FIELD_MAP_ROWS, [30, 110], True),
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
    BANNER_DIR.mkdir(parents=True, exist_ok=True)
    events = make_events()
    attendees = make_attendees(events)
    for idx, event in enumerate(events[:BANNER_COUNT]):
        generate_banner(BANNER_DIR / f"event_{event['id']}.png", str(event["title"]), str(event["category"]), idx)
    write_xlsx(events, attendees)
    print(f"Workbook: {WORKBOOK_PATH}")
    print(f"Events: {len(events)}")
    print(f"Attendees: {len(attendees)}")
    print(f"Banners: {len(list(BANNER_DIR.glob('*.png')))}")


if __name__ == "__main__":
    main()
