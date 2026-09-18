"""Tests for bounded profile-image validation and local storage."""

from io import BytesIO
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

import pytest
from PIL import Image

from app.integrations.uploads import (
    MAX_AVATAR_BYTES,
    MAX_CHAT_ATTACHMENT_BYTES,
    AvatarStorage,
    AvatarUploadError,
    ChatAttachmentStorage,
    UploadStorageError,
    prepare_avatar,
    prepare_chat_attachment,
)


def _image_bytes(image_format: str = "PNG") -> bytes:
    output = BytesIO()
    Image.new("RGB", (8, 6), color=(12, 34, 56)).save(output, format=image_format)
    return output.getvalue()


def test_avatar_is_verified_reencoded_and_given_a_safe_name(tmp_path: Path) -> None:
    """Stored avatars use server names and normalized image bytes beneath upload_root."""
    prepared = prepare_avatar("../../A profile?.png", _image_bytes())
    assert prepared.extension == "png"
    assert prepared.original_filename == "A_profile_.png"
    with Image.open(BytesIO(prepared.content)) as image:
        assert image.format == "PNG"
        assert image.size == (8, 6)

    storage = AvatarStorage(tmp_path / "uploads")
    stored = storage.save(42, prepared)
    target = tmp_path / "uploads" / "profiles" / stored.filename
    assert target.is_file()
    assert stored.relative_path == f"uploads/profiles/{stored.filename}"
    assert stored.filename.startswith("42_")
    storage.delete(stored)
    assert not target.exists()


@pytest.mark.parametrize(
    ("filename", "content", "message"),
    [
        ("empty.png", b"", "empty"),
        ("too-large.png", b"x" * (MAX_AVATAR_BYTES + 1), "5 MB"),
        ("fake.png", b"not an image", "valid image"),
    ],
    ids=("empty", "oversized", "spoofed"),
)
def test_avatar_rejects_empty_oversized_and_spoofed_content(
    filename: str,
    content: bytes,
    message: str,
) -> None:
    """Extension and declared MIME text cannot bypass content checks."""
    with pytest.raises(AvatarUploadError, match=message):
        prepare_avatar(filename, content)


def _docx_bytes() -> bytes:
    output = BytesIO()
    with ZipFile(output, "w", ZIP_DEFLATED) as archive:
        archive.writestr("[Content_Types].xml", "<Types />")
        archive.writestr("word/document.xml", "<w:document />")
    return output.getvalue()


@pytest.mark.parametrize(
    ("filename", "content", "mime_type", "kind"),
    [
        ("photo.png", _image_bytes(), "image/png", "image"),
        ("report.pdf", b"%PDF-1.7\nsynthetic", "application/pdf", "file"),
        (
            "report.doc",
            b"\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1synthetic",
            "application/msword",
            "file",
        ),
        (
            "report.docx",
            _docx_bytes(),
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "file",
        ),
        ("notes.txt", b"Synthetic private notes", "text/plain", "file"),
        ("contacts.csv", b"name,email\nSynthetic,none@example.invalid\n", "text/csv", "file"),
    ],
    ids=("image", "pdf", "doc", "docx", "text", "csv"),
)
def test_chat_attachment_validation_uses_content_and_safe_metadata(
    filename: str, content: bytes, mime_type: str, kind: str
) -> None:
    """Every retained browser attachment family gets a bounded server-side representation."""
    prepared = prepare_chat_attachment(f"../../{filename}", content)
    assert prepared.mime_type == mime_type
    assert prepared.kind == kind
    assert prepared.original_filename == filename
    assert prepared.content


@pytest.mark.parametrize(
    ("filename", "content", "message"),
    [
        ("unsafe.svg", b"<svg onload='alert(1)'/>", "Audio content"),
        ("not-a-pdf.pdf", b"not really a PDF", "Audio content"),
        ("binary.txt", b"text\x00payload", "binary"),
        ("bad.docx", b"not-a-zip", "Document content"),
        ("oversized.txt", b"x" * (MAX_CHAT_ATTACHMENT_BYTES + 1), "2 MB"),
    ],
    ids=("svg", "pdf-spoof", "binary-text", "docx-spoof", "oversized"),
)
def test_chat_attachment_validation_rejects_unsafe_or_spoofed_bytes(
    filename: str, content: bytes, message: str
) -> None:
    with pytest.raises(AvatarUploadError, match=message):
        prepare_chat_attachment(filename, content)


def test_chat_attachment_storage_is_private_and_rejects_path_traversal(tmp_path: Path) -> None:
    """Storage returns only a generated private path and never follows caller paths."""
    prepared = prepare_chat_attachment("notes.txt", b"Private synthetic note")
    storage = ChatAttachmentStorage(tmp_path / "uploads")
    stored = storage.save(42, prepared)
    target = storage.path(stored.relative_path)
    assert target.is_file()
    assert stored.relative_path == f"chat/{stored.filename}"
    with pytest.raises(UploadStorageError):
        storage.path("chat/../../outside.txt")
    storage.delete(stored)
    assert not target.exists()
