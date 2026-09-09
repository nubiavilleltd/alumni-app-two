"""Create a deterministic, non-secret inventory of legacy PHP controller methods."""

from __future__ import annotations

import argparse
import hashlib
import json
import re
from collections import Counter
from collections.abc import Iterable, Sequence
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Literal

METHOD_PATTERN = re.compile(
    r"^\s*(?:(public|protected|private)\s+)?(?:static\s+)?function\s+&?\s*"
    r"([A-Za-z_][A-Za-z0-9_]*)\s*\(",
    re.MULTILINE,
)
INPUT_PATTERN = re.compile(
    r"\$this->input->(post|get)\(\s*['\"]([A-Za-z_][A-Za-z0-9_.-]*)['\"]",
    re.IGNORECASE,
)
FILES_PATTERN = re.compile(r"\$_FILES\s*\[\s*['\"]([^'\"]+)['\"]\s*\]")
TABLE_PATTERN = re.compile(
    r"\$this->db->(?:from|get|insert|update|delete|join)\(\s*['\"]"
    r"([A-Za-z_][A-Za-z0-9_]*)",
    re.IGNORECASE,
)
MODEL_CALL_PATTERN = re.compile(
    r"\$this->([A-Za-z_][A-Za-z0-9_]*_(?:model|library))->"
    r"([A-Za-z_][A-Za-z0-9_]*)\s*\(",
    re.IGNORECASE,
)
STATUS_PATTERNS = (
    re.compile(r"set_status_header\s*\(\s*(\d{3})"),
    re.compile(r"\$this->response\s*\(.*?,\s*(\d{3})\s*\)", re.DOTALL),
)

PRIMARY_CONTROLLERS = frozenset(
    {"Api", "Blog_api", "Chat_api", "News", "Product", "Pwa", "Socials"}
)
LEGACY_CONTROLLERS = frozenset({"Api_backup", "Api_with_jwt", "Setup22", "news2", "oldApi"})
SERVER_RENDERED_CONTROLLERS = frozenset({"Auth", "Home", "Setup"})
UNSAFE_METHODS = frozenset(
    {
        "change_user_password1",
        "check_reset_password",
        "getAPIKey2",
        "sendUserOTP",
        "test_qr",
        "trackUser",
    }
)
CONSOLIDATED_METHODS = frozenset({"resend_otp", "verify_otp"})

AUTH_SIGNALS: dict[str, re.Pattern[str]] = {
    "authorization_header": re.compile(r"Authorization|HTTP_AUTHORIZATION", re.IGNORECASE),
    "ion_auth": re.compile(r"\$this->ion_auth->(?:logged_in|is_admin|in_group)\s*\("),
    "jwt": re.compile(r"\bjwt\b|validate_token|refresh_token", re.IGNORECASE),
    "role_check": re.compile(r"user_role|role_id|is_admin|in_group", re.IGNORECASE),
}

Disposition = Literal[
    "candidate-retain",
    "candidate-retire",
    "contain-or-remove",
    "internal-only",
    "needs-runtime-proof",
    "platform-replace",
    "replaced-by-consolidated-route",
    "retired-security-risk",
    "retire-noncontroller-artifact",
]
RequestMode = Literal["GET", "POST", "MIXED", "UNKNOWN"]


@dataclass(frozen=True, slots=True)
class MethodRecord:
    """Safe static evidence for one callable PHP controller method."""

    legacy_endpoint: str
    controller: str
    method: str
    source: str
    line: int
    declared_visibility: Literal["public", "implicit-public"]
    codeigniter_routable: bool
    disposition: Disposition
    inferred_request_mode: Literal["GET", "POST", "MIXED", "UNKNOWN"]
    input_fields: tuple[str, ...]
    upload_fields: tuple[str, ...]
    database_tables: tuple[str, ...]
    model_calls: tuple[str, ...]
    auth_signals: tuple[str, ...]
    response_statuses: tuple[int, ...]
    body_sha256: str


def _request_mode(matches: Iterable[tuple[str, str]], has_upload: bool) -> RequestMode:
    methods = {method.upper() for method, _field in matches}
    if has_upload:
        methods.add("POST")
    if methods == {"GET"}:
        return "GET"
    if methods == {"POST"}:
        return "POST"
    if methods == {"GET", "POST"}:
        return "MIXED"
    return "UNKNOWN"


def _disposition(controller: str, method: str, *, root_artifact: bool) -> Disposition:
    if root_artifact:
        return "retire-noncontroller-artifact"
    if method.startswith("_"):
        return "internal-only"
    if controller == "Api" and method in UNSAFE_METHODS:
        return "retired-security-risk"
    if controller == "RateAgentApi" or method in UNSAFE_METHODS:
        return "contain-or-remove"
    if controller in LEGACY_CONTROLLERS:
        return "candidate-retire"
    if controller == "Api" and method in CONSOLIDATED_METHODS:
        return "replaced-by-consolidated-route"
    if controller in SERVER_RENDERED_CONTROLLERS:
        return "needs-runtime-proof"
    if controller == "my404":
        return "platform-replace"
    if controller in PRIMARY_CONTROLLERS:
        return "candidate-retain"
    return "needs-runtime-proof"


def extract_methods(
    source_path: Path,
    *,
    display_path: str,
    root_artifact: bool = False,
) -> list[MethodRecord]:
    """Extract callable methods and bounded static signals from one PHP source file."""
    source = source_path.read_text(encoding="utf-8", errors="replace")
    declarations = list(METHOD_PATTERN.finditer(source))
    controller = source_path.stem
    records: list[MethodRecord] = []

    for index, declaration in enumerate(declarations):
        raw_visibility = declaration.group(1)
        method = declaration.group(2)
        if raw_visibility in {"private", "protected"} or method == "__construct":
            continue
        visibility: Literal["public", "implicit-public"] = (
            "public" if raw_visibility == "public" else "implicit-public"
        )

        body_end = declarations[index + 1].start() if index + 1 < len(declarations) else len(source)
        body = source[declaration.start() : body_end]
        inputs = INPUT_PATTERN.findall(body)
        upload_fields = tuple(sorted(set(FILES_PATTERN.findall(body))))
        input_fields = tuple(sorted({field for _method, field in inputs}))
        database_tables = tuple(sorted(set(TABLE_PATTERN.findall(body))))
        model_calls = tuple(
            sorted({f"{model}.{call}" for model, call in MODEL_CALL_PATTERN.findall(body)})
        )
        auth_signals = tuple(
            name for name, pattern in AUTH_SIGNALS.items() if pattern.search(body) is not None
        )
        response_statuses = tuple(
            sorted({int(value) for pattern in STATUS_PATTERNS for value in pattern.findall(body)})
        )
        line = source.count("\n", 0, declaration.start()) + 1
        endpoint = f"{controller.casefold()}/{method}"
        records.append(
            MethodRecord(
                legacy_endpoint=endpoint,
                controller=controller,
                method=method,
                source=display_path,
                line=line,
                declared_visibility=visibility,
                codeigniter_routable=not method.startswith("_"),
                disposition=_disposition(controller, method, root_artifact=root_artifact),
                inferred_request_mode=_request_mode(inputs, bool(upload_fields)),
                input_fields=input_fields,
                upload_fields=upload_fields,
                database_tables=database_tables,
                model_calls=model_calls,
                auth_signals=auth_signals,
                response_statuses=response_statuses,
                body_sha256=hashlib.sha256(body.encode("utf-8")).hexdigest(),
            )
        )
    return records


def build_inventory(php_root: Path) -> dict[str, object]:
    """Build inventory data for CodeIgniter controllers and the root artifact."""
    controller_root = php_root / "application" / "controllers"
    records: list[MethodRecord] = []
    controller_files = sorted(controller_root.glob("*.php"), key=lambda path: path.name.casefold())
    for path in controller_files:
        records.extend(
            extract_methods(
                path,
                display_path=path.relative_to(php_root.parent.parent).as_posix(),
            )
        )

    root_api = php_root / "Api.php"
    root_records = extract_methods(
        root_api,
        display_path=root_api.relative_to(php_root.parent.parent).as_posix(),
        root_artifact=True,
    )
    records.extend(root_records)

    controller_records = records[: -len(root_records)] if root_records else records
    log_reference_pattern = re.compile(r"application/controllers/([A-Za-z0-9_]+\.php)")
    log_references: Counter[str] = Counter()
    for log_path in sorted((php_root / "application" / "logs").glob("log-*.php")):
        log_references.update(
            log_reference_pattern.findall(log_path.read_text(encoding="utf-8", errors="replace"))
        )
    return {
        "snapshot_date": "2026-09-09",
        "evidence_boundary": (
            "Static source inventory plus value-free controller filename counts from "
            "checked-in framework logs. Request modes, callers, and candidate controller "
            "dispositions remain provisional until verified against frontend or traffic evidence; "
            "explicit replacement/retirement dispositions reflect reviewed migration decisions."
        ),
        "controller_files": len(controller_files),
        "controller_callable_methods": len(controller_records),
        "controller_routable_candidates": sum(
            record.codeigniter_routable for record in controller_records
        ),
        "root_artifact_callable_methods": len(root_records),
        "root_artifact_route_status": "outside-controller-directory-and-directly-guarded",
        "observed_controller_log_references": dict(sorted(log_references.items())),
        "records": [asdict(record) for record in records],
    }


def parse_args(argv: Sequence[str] | None = None) -> argparse.Namespace:
    """Parse command-line arguments."""
    workspace_root = Path(__file__).resolve().parents[2]
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--php-root",
        type=Path,
        default=workspace_root / "Backend" / "alumniappV2 - PHP",
    )
    parser.add_argument(
        "--output",
        type=Path,
        default=workspace_root / "python-backend" / "docs" / "endpoint-inventory.json",
    )
    return parser.parse_args(argv)


def main(argv: Sequence[str] | None = None) -> int:
    """Write the deterministic endpoint inventory and return a process status."""
    args = parse_args(argv)
    inventory = build_inventory(args.php_root.resolve())
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(
        json.dumps(inventory, indent=2, sort_keys=True) + "\n",
        encoding="utf-8",
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
