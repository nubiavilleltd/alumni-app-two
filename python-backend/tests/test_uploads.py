"""Tests for bounded profile-image validation and local storage."""

from io import BytesIO
from pathlib import Path

import pytest
from PIL import Image

from app.integrations.uploads import (
    MAX_AVATAR_BYTES,
    AvatarStorage,
    AvatarUploadError,
    prepare_avatar,
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
