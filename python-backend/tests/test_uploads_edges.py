"""Upload boundary coverage for Goal 9 media handling.

These are process-local unit tests: they prove that every storage target and
content validator fails closed with a typed error instead of crashing or
silently accepting unsafe content. No database, provider, or network is used.
"""

from __future__ import annotations

import zipfile
from io import BytesIO
from pathlib import Path
from typing import Any

import pytest
from PIL import Image

from app.integrations.uploads import (
    MAX_AVATAR_BYTES,
    MAX_CHAT_ATTACHMENT_BYTES,
    AnnouncementStorage,
    AvatarStorage,
    AvatarUploadError,
    BlogGalleryStorage,
    CarouselStorage,
    ChatAttachmentStorage,
    EventStorage,
    LeadershipStorage,
    MarketplaceStorage,
    PreparedAvatar,
    PreparedChatAttachment,
    ProductStorage,
    ProjectStorage,
    StoredAvatar,
    UploadStorageError,
    VacancyStorage,
    prepare_avatar,
    prepare_chat_attachment,
)


def _image_bytes(image_format: str, *, mode: str = "RGB") -> bytes:
    buffer = BytesIO()
    Image.new(mode, (8, 8), color="red" if mode != "P" else 1).save(buffer, format=image_format)
    return buffer.getvalue()


def _avatar(extension: str = "png") -> PreparedAvatar:
    return PreparedAvatar(
        content=_image_bytes("PNG"),
        extension=extension,
        original_filename=f"edge.{extension}",
    )


def _attachment(kind: str = "file") -> PreparedChatAttachment:
    return PreparedChatAttachment(
        content=b"payload",
        extension="pdf",
        original_filename="edge.pdf",
        mime_type="application/pdf",
        kind=kind,
    )


# ═════════════════════════════════════════════════════════════
# AVATAR CONTENT VALIDATION
# ═════════════════════════════════════════════════════════════


def test_prepare_avatar_rejects_empty_and_oversized_content() -> None:
    with pytest.raises(AvatarUploadError, match="Avatar file is empty"):
        prepare_avatar("empty.png", b"")
    with pytest.raises(AvatarUploadError, match="must not exceed 5 MB"):
        prepare_avatar("huge.png", b"x" * (MAX_AVATAR_BYTES + 1))


def test_prepare_avatar_rejects_non_image_and_unsupported_formats() -> None:
    with pytest.raises(AvatarUploadError, match="not a valid image"):
        prepare_avatar("fake.png", b"definitely not an image")
    with pytest.raises(AvatarUploadError, match="must be a JPEG, PNG, or GIF image"):
        prepare_avatar("bitmap.bmp", _image_bytes("BMP"))


@pytest.mark.parametrize(
    ("image_format", "mode", "expected_extension"),
    [
        ("PNG", "RGB", "png"),
        ("PNG", "RGBA", "png"),
        ("JPEG", "RGB", "jpg"),
        ("GIF", "P", "gif"),
    ],
)
def test_prepare_avatar_re_encodes_each_supported_format(
    image_format: str,
    mode: str,
    expected_extension: str,
) -> None:
    prepared = prepare_avatar(
        f"sample.{image_format.lower()}", _image_bytes(image_format, mode=mode)
    )
    assert prepared.extension == expected_extension
    assert prepared.original_filename == f"sample.{image_format.lower()}"
    # The returned bytes are a freshly re-encoded image, not the upload verbatim.
    with Image.open(BytesIO(prepared.content)) as reopened:
        assert reopened.size == (8, 8)


def test_prepare_avatar_re_encodes_webp_when_the_runtime_supports_it() -> None:
    try:
        source = _image_bytes("WEBP", mode="RGBA")
    except (OSError, ValueError):  # pragma: no cover - depends on Pillow build
        pytest.skip("This Pillow build cannot write WEBP")
    prepared = prepare_avatar("sample.webp", source)
    assert prepared.extension == "webp"


def test_prepare_avatar_sanitizes_the_original_filename() -> None:
    prepared = prepare_avatar("../../etc/weird name!.png", _image_bytes("PNG"))
    assert "/" not in prepared.original_filename
    assert "\\" not in prepared.original_filename


# ═════════════════════════════════════════════════════════════
# CHAT ATTACHMENT CONTENT VALIDATION
# ═════════════════════════════════════════════════════════════


def test_chat_attachment_rejects_empty_and_oversized_payloads() -> None:
    with pytest.raises(AvatarUploadError, match="Attachment file is empty"):
        prepare_chat_attachment("a.pdf", b"")
    with pytest.raises(AvatarUploadError, match="must not exceed 2 MB"):
        prepare_chat_attachment("a.pdf", b"%PDF-" + b"x" * MAX_CHAT_ATTACHMENT_BYTES)


def test_chat_attachment_accepts_a_matching_pdf() -> None:
    prepared = prepare_chat_attachment("notes.pdf", b"%PDF-1.4 synthetic body")
    assert prepared.kind == "file"
    assert prepared.mime_type == "application/pdf"
    assert prepared.extension == "pdf"


def test_chat_attachment_rejects_a_pdf_named_file_without_a_pdf_signature() -> None:
    with pytest.raises(AvatarUploadError, match="Audio content does not match its file type"):
        prepare_chat_attachment("notes.pdf", b"plain text, not a pdf")


def _docx_bytes(*, include_document: bool = True, extra_entries: int = 0) -> bytes:
    buffer = BytesIO()
    with zipfile.ZipFile(buffer, "w") as archive:
        archive.writestr("[Content_Types].xml", "<Types/>")
        if include_document:
            archive.writestr("word/document.xml", "<w:document/>")
        for index in range(extra_entries):
            archive.writestr(f"extra/{index}.xml", "<x/>")
    return buffer.getvalue()


def test_chat_attachment_validates_office_document_structure() -> None:
    accepted = prepare_chat_attachment("report.docx", _docx_bytes())
    assert accepted.extension == "docx"
    assert accepted.kind == "file"

    with pytest.raises(AvatarUploadError, match="does not match its file type"):
        prepare_chat_attachment("report.docx", b"not a zip archive")

    with pytest.raises(AvatarUploadError, match="invalid or too large"):
        prepare_chat_attachment("report.docx", _docx_bytes(include_document=False))

    with pytest.raises(AvatarUploadError, match="invalid or too large"):
        prepare_chat_attachment("report.docx", _docx_bytes(extra_entries=10_001))


def test_chat_attachment_accepts_a_legacy_word_container() -> None:
    legacy = b"\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1" + b"\x00" * 32
    prepared = prepare_chat_attachment("letter.doc", legacy)
    assert prepared.extension == "doc"
    assert prepared.mime_type == "application/msword"


def test_chat_attachment_validates_text_payloads() -> None:
    csv_file = prepare_chat_attachment("roster.csv", b"name,email\nAda,ada@example.test\n")
    assert csv_file.mime_type == "text/csv"
    assert csv_file.extension == "csv"

    with pytest.raises(AvatarUploadError, match="must be valid UTF-8"):
        prepare_chat_attachment("notes.txt", b"\xff\xfe\x00bad")

    with pytest.raises(AvatarUploadError, match="must not contain binary data"):
        prepare_chat_attachment("notes.txt", b"valid prefix\x00binary")

    # The mime sniffing reports octet-stream here, so the text branch rejects the
    # unsupported extension through the audio/content comparison.
    with pytest.raises(AvatarUploadError):
        prepare_chat_attachment("notes.md", b"plain markdown")


def test_chat_attachment_validates_audio_extension_against_detected_content() -> None:
    # MP3 frame sync is detected as audio/mpeg; a mismatched extension is refused.
    mp3_like = b"ID3\x03\x00\x00\x00\x00\x00\x00" + b"\x00" * 16
    with pytest.raises(AvatarUploadError):
        prepare_chat_attachment("recording.wav", mp3_like)


def test_chat_attachment_accepts_a_matching_image_and_sanitizes_its_name() -> None:
    prepared = prepare_chat_attachment("../evil name.png", _image_bytes("PNG"))
    assert prepared.kind == "image"
    assert prepared.mime_type == "image/png"
    assert "/" not in prepared.original_filename


# ═════════════════════════════════════════════════════════════
# STORAGE WRITE FAILURES
# ═════════════════════════════════════════════════════════════

_AVATAR_STORAGES: list[tuple[str, type[AvatarStorage], str]] = [
    ("profiles", AvatarStorage, "Avatar could not be stored"),
    ("announcements", AnnouncementStorage, "Announcement image could not be stored"),
    ("marketplace", MarketplaceStorage, "Marketplace image could not be stored"),
    ("projects", ProjectStorage, "Project image could not be stored"),
    ("leadership", LeadershipStorage, "Leadership image could not be stored"),
    ("vacancies", VacancyStorage, "Vacancy flyer could not be stored"),
    ("events", EventStorage, "Event banner could not be stored"),
    ("products", ProductStorage, "Product image could not be stored"),
]


@pytest.mark.parametrize(("directory", "storage_type", "message"), _AVATAR_STORAGES)
def test_avatar_storages_surface_write_failures_as_typed_errors(
    tmp_path: Path,
    directory: str,
    storage_type: type[AvatarStorage],
    message: str,
) -> None:
    """A path that cannot be created must fail closed, never crash the request."""
    (tmp_path / directory).write_bytes(b"this file blocks the directory")
    with pytest.raises(UploadStorageError, match=message):
        storage_type(tmp_path).save(7, _avatar())


@pytest.mark.parametrize(
    ("directory", "storage_type", "message"),
    [
        ("homepage/carousel", CarouselStorage, "Carousel image could not be stored"),
        ("blog/gallery", BlogGalleryStorage, "Blog gallery image could not be stored"),
    ],
)
def test_nested_avatar_storages_surface_write_failures_as_typed_errors(
    tmp_path: Path,
    directory: str,
    storage_type: type[AvatarStorage],
    message: str,
) -> None:
    blocking = tmp_path / directory
    blocking.parent.mkdir(parents=True, exist_ok=True)
    blocking.write_bytes(b"this file blocks the directory")
    with pytest.raises(UploadStorageError, match=message):
        storage_type(tmp_path).save(7, _avatar())


def test_chat_storage_surfaces_write_failures_as_typed_errors(tmp_path: Path) -> None:
    (tmp_path / "chat").write_bytes(b"blocking file")
    with pytest.raises(UploadStorageError, match="Chat attachment could not be stored"):
        ChatAttachmentStorage(tmp_path).save(7, _attachment())


@pytest.mark.parametrize(("directory", "storage_type", "_message"), _AVATAR_STORAGES)
def test_avatar_storage_delete_is_confined_to_its_own_directory(
    tmp_path: Path,
    directory: str,
    storage_type: type[AvatarStorage],
    _message: str,
) -> None:
    """Traversal attempts through the stored filename must be ignored."""
    storage = storage_type(tmp_path)
    outside = tmp_path / "outside.txt"
    outside.write_bytes(b"keep me")
    storage.delete(
        StoredAvatar(
            relative_path=f"{directory}/../outside.txt",
            filename="../outside.txt",
            original_filename="outside.txt",
        )
    )
    assert outside.exists()

    inside = tmp_path / directory
    inside.mkdir(parents=True, exist_ok=True)
    stored = inside / "owned.png"
    stored.write_bytes(b"remove me")
    storage.delete(
        StoredAvatar(
            relative_path=f"{directory}/owned.png",
            filename="owned.png",
            original_filename="owned.png",
        )
    )
    assert not stored.exists()


def test_chat_storage_delete_swallows_confined_path_failures(tmp_path: Path) -> None:
    """Deleting a file that is already gone is a no-op, not an error."""
    storage = ChatAttachmentStorage(tmp_path)
    (tmp_path / "chat").mkdir(parents=True, exist_ok=True)
    storage.delete(StoredAvatar("chat/edge.pdf", "edge.pdf", "edge.pdf"))  # nothing on disk yet
    assert not (tmp_path / "chat" / "edge.pdf").exists()

    # A traversal path is refused by ``path()`` and swallowed by ``delete()``.
    outside = tmp_path / "outside.pdf"
    outside.write_bytes(b"keep me")
    storage.delete(StoredAvatar("chat/../outside.pdf", "../outside.pdf", "outside.pdf"))
    assert outside.exists()


def test_product_storage_delete_accepts_a_relative_path_string(tmp_path: Path) -> None:
    storage = ProductStorage(tmp_path)
    directory = tmp_path / "products"
    directory.mkdir(parents=True, exist_ok=True)
    target = directory / "listed.png"
    target.write_bytes(b"remove me")

    storage.delete("uploads/products/listed.png")
    assert not target.exists()


def test_avatar_storage_rejects_a_target_outside_its_directory(
    tmp_path: Path,
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    """The relative-path guard must hold even if the root is replaced by an unsafe one."""
    storage = AvatarStorage(tmp_path)
    unsafe_root = Path(str(tmp_path / "nested" / ".." / "nested"))
    monkeypatch.setattr(storage, "_upload_root", unsafe_root)
    with pytest.raises(UploadStorageError, match="Avatar storage path is invalid"):
        storage.save(7, _avatar())


def test_storage_round_trip_returns_a_relative_path(tmp_path: Path) -> None:
    stored: Any = ProductStorage(tmp_path).save(5, _avatar("png"))
    assert stored.relative_path.startswith("uploads/products/")
    assert (tmp_path / "products" / stored.filename).is_file()


def test_chat_storage_round_trip_returns_a_relative_path(tmp_path: Path) -> None:
    stored = ChatAttachmentStorage(tmp_path).save(5, _attachment())
    assert stored.relative_path.startswith("chat/")
    assert (tmp_path / "chat" / stored.filename).is_file()
