"""Validated local storage for user-supplied profile images."""

from __future__ import annotations

import os
import re
import zipfile
from dataclasses import dataclass
from io import BytesIO
from pathlib import Path
from uuid import uuid4

import filetype  # type: ignore[import-untyped]
from PIL import Image, UnidentifiedImageError

MAX_AVATAR_BYTES = 5 * 1024 * 1024
MAX_AVATAR_PIXELS = 40_000_000
_FORMAT_EXTENSION = {"JPEG": "jpg", "PNG": "png", "GIF": "gif", "WEBP": "webp"}
MAX_CHAT_ATTACHMENT_BYTES = 2 * 1024 * 1024
MAX_CHAT_ATTACHMENT_ZIP_EXPANDED_BYTES = 20 * 1024 * 1024
_CHAT_IMAGE_MIME_TYPES = {
    "image/jpeg",
    "image/png",
    "image/gif",
    "image/webp",
}
_CHAT_AUDIO_SIGNATURES = {
    "audio/mpeg": ("mp3",),
    "audio/ogg": ("ogg",),
    "audio/wav": ("wav",),
    "audio/webm": ("webm",),
    "audio/mp4": ("mp4",),
}


class AvatarUploadError(Exception):
    """An uploaded avatar failed a bounded content check."""


class UploadStorageError(Exception):
    """Validated upload bytes could not be persisted safely."""


@dataclass(frozen=True, slots=True)
class PreparedAvatar:
    """Validated and normalized avatar bytes ready for storage."""

    content: bytes
    extension: str
    original_filename: str


@dataclass(frozen=True, slots=True)
class StoredAvatar:
    """Persisted avatar metadata used by the database transaction."""

    relative_path: str
    filename: str
    original_filename: str


@dataclass(frozen=True, slots=True)
class PreparedChatAttachment:
    """Validated private chat bytes; never use the browser-supplied MIME type."""

    content: bytes
    extension: str
    original_filename: str
    mime_type: str
    kind: str


def prepare_avatar(filename: str | None, content: bytes) -> PreparedAvatar:
    """Verify, bound, re-encode, and strip metadata from an avatar image."""
    if not content:
        raise AvatarUploadError("Avatar file is empty")
    if len(content) > MAX_AVATAR_BYTES:
        raise AvatarUploadError("Avatar must not exceed 5 MB")
    try:
        with Image.open(BytesIO(content)) as image:
            image.verify()
        with Image.open(BytesIO(content)) as image:
            image_format = (image.format or "").upper()
            extension = _FORMAT_EXTENSION.get(image_format)
            if extension is None:
                raise AvatarUploadError("Avatar must be a JPEG, PNG, or GIF image")
            width, height = image.size
            if width <= 0 or height <= 0 or width * height > MAX_AVATAR_PIXELS:
                raise AvatarUploadError("Avatar image dimensions are invalid or too large")
            output = BytesIO()
            if image_format == "JPEG":
                image.convert("RGB").save(output, format="JPEG", quality=90, optimize=True)
            elif image_format == "PNG":
                normalized = (
                    image.convert("RGBA") if "A" in image.getbands() else image.convert("RGB")
                )
                normalized.save(output, format="PNG", optimize=True)
            elif image_format == "WEBP":
                normalized = (
                    image.convert("RGBA") if "A" in image.getbands() else image.convert("RGB")
                )
                normalized.save(output, format="WEBP", quality=90, method=6)
            else:
                image.seek(0)
                image.convert("P").save(output, format="GIF", optimize=True)
    except AvatarUploadError:
        raise
    except Image.DecompressionBombError as exc:
        raise AvatarUploadError("Avatar image dimensions are invalid or too large") from exc
    except (OSError, UnidentifiedImageError, ValueError) as exc:
        raise AvatarUploadError("Avatar file is not a valid image") from exc

    clean_name = re.sub(r"[^A-Za-z0-9._-]", "_", Path(filename or "avatar").name)[:200]
    return PreparedAvatar(
        content=output.getvalue(),
        extension=extension,
        original_filename=clean_name or f"avatar.{extension}",
    )


def _clean_chat_name(filename: str | None, extension: str) -> str:
    stem = re.sub(r"[^A-Za-z0-9._-]", "_", Path(filename or "attachment").stem)[:180]
    return f"{stem or 'attachment'}.{extension}"


def _prepare_chat_image(filename: str | None, content: bytes) -> PreparedChatAttachment:
    avatar = prepare_avatar(filename, content)
    mime_type = {
        "jpg": "image/jpeg",
        "png": "image/png",
        "gif": "image/gif",
        "webp": "image/webp",
    }[avatar.extension]
    return PreparedChatAttachment(
        content=avatar.content,
        extension=avatar.extension,
        original_filename=_clean_chat_name(filename, avatar.extension),
        mime_type=mime_type,
        kind="image",
    )


def _prepare_chat_office_document(filename: str | None, content: bytes) -> PreparedChatAttachment:
    extension = Path(filename or "").suffix.lower()
    if content.startswith(b"\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1") and extension == ".doc":
        return PreparedChatAttachment(
            content=content,
            extension="doc",
            original_filename=_clean_chat_name(filename, "doc"),
            mime_type="application/msword",
            kind="file",
        )
    if extension != ".docx" or not zipfile.is_zipfile(BytesIO(content)):
        raise AvatarUploadError("Document content does not match its file type")
    try:
        with zipfile.ZipFile(BytesIO(content)) as archive:
            names = set(archive.namelist())
            expanded_size = sum(item.file_size for item in archive.infolist())
            if (
                "[Content_Types].xml" not in names
                or "word/document.xml" not in names
                or len(names) > 10_000
                or expanded_size > MAX_CHAT_ATTACHMENT_ZIP_EXPANDED_BYTES
            ):
                raise AvatarUploadError("Document content is invalid or too large")
    except (OSError, zipfile.BadZipFile) as exc:
        raise AvatarUploadError("Document content is invalid") from exc
    return PreparedChatAttachment(
        content=content,
        extension="docx",
        original_filename=_clean_chat_name(filename, "docx"),
        mime_type="application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        kind="file",
    )


def _prepare_chat_text(filename: str | None, content: bytes) -> PreparedChatAttachment:
    try:
        content.decode("utf-8")
    except UnicodeDecodeError as exc:
        raise AvatarUploadError("Text attachments must be valid UTF-8") from exc
    if b"\x00" in content:
        raise AvatarUploadError("Text attachments must not contain binary data")
    extension = Path(filename or "").suffix.lower()
    if extension not in {".txt", ".csv"}:
        raise AvatarUploadError("Text attachment extension is not supported")
    mime_type = "text/csv" if extension == ".csv" else "text/plain"
    return PreparedChatAttachment(
        content=content,
        extension=extension[1:],
        original_filename=_clean_chat_name(filename, extension[1:]),
        mime_type=mime_type,
        kind="file",
    )


def _prepare_chat_audio(
    filename: str | None, content: bytes, detected_extension: str
) -> PreparedChatAttachment:
    extension = Path(filename or "").suffix.lower().lstrip(".")
    mime_type = next(
        (
            mime
            for mime, extensions in _CHAT_AUDIO_SIGNATURES.items()
            if detected_extension in extensions
        ),
        None,
    )
    if mime_type is None or extension not in _CHAT_AUDIO_SIGNATURES[mime_type]:
        raise AvatarUploadError("Audio content does not match its file type")
    return PreparedChatAttachment(
        content=content,
        extension=extension,
        original_filename=_clean_chat_name(filename, extension),
        mime_type=mime_type,
        kind="audio",
    )


def prepare_chat_attachment(filename: str | None, content: bytes) -> PreparedChatAttachment:
    """Validate a bounded, private chat attachment from content, not request metadata.

    SVG is intentionally not accepted: browser SVG rendering can execute active
    content. Office documents are structurally checked but are download-only;
    malware-scanning/provider approval remains a deployment gate.
    """
    if not content:
        raise AvatarUploadError("Attachment file is empty")
    if len(content) > MAX_CHAT_ATTACHMENT_BYTES:
        raise AvatarUploadError("Attachments must not exceed 2 MB")

    detected = filetype.guess(content)
    detected_mime = detected.mime if detected is not None else None
    detected_extension = detected.extension if detected is not None else ""
    if detected_mime in _CHAT_IMAGE_MIME_TYPES:
        return _prepare_chat_image(filename, content)
    if content.startswith(b"%PDF-") and Path(filename or "").suffix.lower() == ".pdf":
        return PreparedChatAttachment(
            content=content,
            extension="pdf",
            original_filename=_clean_chat_name(filename, "pdf"),
            mime_type="application/pdf",
            kind="file",
        )
    if Path(filename or "").suffix.lower() in {".doc", ".docx"}:
        return _prepare_chat_office_document(filename, content)
    if Path(filename or "").suffix.lower() in {".txt", ".csv"}:
        return _prepare_chat_text(filename, content)
    return _prepare_chat_audio(filename, content, detected_extension)


class ChatAttachmentStorage:
    """Private chat file storage. This directory is never mounted as static media."""

    def __init__(self, upload_root: Path) -> None:
        self._upload_root = upload_root.resolve()

    def save(self, user_id: int, attachment: PreparedChatAttachment) -> StoredAvatar:
        directory = (self._upload_root / "chat").resolve()
        if directory.parent != self._upload_root:
            raise UploadStorageError("Chat storage path is invalid")
        filename = f"{user_id}_{uuid4().hex}.{attachment.extension}"
        target = (directory / filename).resolve()
        if target.parent != directory:
            raise UploadStorageError("Chat storage path is invalid")
        temporary = target.with_suffix(target.suffix + ".part")
        try:
            directory.mkdir(parents=True, exist_ok=True)
            with temporary.open("xb") as handle:
                handle.write(attachment.content)
                handle.flush()
                os.fsync(handle.fileno())
            os.replace(temporary, target)
        except OSError as exc:
            temporary.unlink(missing_ok=True)
            raise UploadStorageError("Chat attachment could not be stored") from exc
        return StoredAvatar(f"chat/{filename}", filename, attachment.original_filename)

    def path(self, relative_path: str) -> Path:
        filename = Path(relative_path).name
        directory = (self._upload_root / "chat").resolve()
        target = (directory / filename).resolve()
        if relative_path != f"chat/{filename}" or target.parent != directory:
            raise UploadStorageError("Chat storage path is invalid")
        return target

    def delete(self, stored: StoredAvatar) -> None:
        try:
            self.path(stored.relative_path).unlink(missing_ok=True)
        except UploadStorageError:
            return


class AvatarStorage:
    """Write generated avatar names only beneath the configured upload root."""

    def __init__(self, upload_root: Path) -> None:
        self._upload_root = upload_root.resolve()

    def save(self, user_id: int, avatar: PreparedAvatar) -> StoredAvatar:
        """Atomically place one validated avatar in the profiles directory."""
        directory = (self._upload_root / "profiles").resolve()
        if directory.parent != self._upload_root:
            raise UploadStorageError("Avatar storage path is invalid")
        filename = f"{user_id}_{uuid4().hex}_avatar.{avatar.extension}"
        target = (directory / filename).resolve()
        if target.parent != directory:
            raise UploadStorageError("Avatar storage path is invalid")
        temporary = target.with_suffix(target.suffix + ".part")
        try:
            directory.mkdir(parents=True, exist_ok=True)
            with temporary.open("xb") as handle:
                handle.write(avatar.content)
                handle.flush()
                os.fsync(handle.fileno())
            os.replace(temporary, target)
        except OSError as exc:
            temporary.unlink(missing_ok=True)
            raise UploadStorageError("Avatar could not be stored") from exc
        return StoredAvatar(
            relative_path=f"uploads/profiles/{filename}",
            filename=filename,
            original_filename=avatar.original_filename,
        )

    def delete(self, stored: StoredAvatar) -> None:
        """Remove only a file name previously generated by this storage adapter."""
        target = (self._upload_root / "profiles" / stored.filename).resolve()
        directory = (self._upload_root / "profiles").resolve()
        if target.parent == directory and target.name == stored.filename:
            target.unlink(missing_ok=True)


class AnnouncementStorage(AvatarStorage):
    """Store validated announcement images separately from member avatars."""

    def save(self, user_id: int, avatar: PreparedAvatar) -> StoredAvatar:
        directory = (self._upload_root / "announcements").resolve()
        if directory.parent != self._upload_root:
            raise UploadStorageError("Announcement storage path is invalid")
        filename = f"{user_id}_{uuid4().hex}_announcement.{avatar.extension}"
        target = (directory / filename).resolve()
        if target.parent != directory:
            raise UploadStorageError("Announcement storage path is invalid")
        temporary = target.with_suffix(target.suffix + ".part")
        try:
            directory.mkdir(parents=True, exist_ok=True)
            with temporary.open("xb") as handle:
                handle.write(avatar.content)
                handle.flush()
                os.fsync(handle.fileno())
            os.replace(temporary, target)
        except OSError as exc:
            temporary.unlink(missing_ok=True)
            raise UploadStorageError("Announcement image could not be stored") from exc
        return StoredAvatar(
            relative_path=f"uploads/announcements/{filename}",
            filename=filename,
            original_filename=avatar.original_filename,
        )

    def delete(self, stored: StoredAvatar) -> None:
        target = (self._upload_root / "announcements" / stored.filename).resolve()
        directory = (self._upload_root / "announcements").resolve()
        if target.parent == directory and target.name == stored.filename:
            target.unlink(missing_ok=True)


class MarketplaceStorage(AvatarStorage):
    """Store validated marketplace images separately from profile assets."""

    def save(self, user_id: int, avatar: PreparedAvatar) -> StoredAvatar:
        directory = (self._upload_root / "marketplace").resolve()
        if directory.parent != self._upload_root:
            raise UploadStorageError("Marketplace storage path is invalid")
        filename = f"{user_id}_{uuid4().hex}_listing.{avatar.extension}"
        target = (directory / filename).resolve()
        if target.parent != directory:
            raise UploadStorageError("Marketplace storage path is invalid")
        temporary = target.with_suffix(target.suffix + ".part")
        try:
            directory.mkdir(parents=True, exist_ok=True)
            with temporary.open("xb") as handle:
                handle.write(avatar.content)
                handle.flush()
                os.fsync(handle.fileno())
            os.replace(temporary, target)
        except OSError as exc:
            temporary.unlink(missing_ok=True)
            raise UploadStorageError("Marketplace image could not be stored") from exc
        return StoredAvatar(
            relative_path=f"uploads/marketplace/{filename}",
            filename=filename,
            original_filename=avatar.original_filename,
        )

    def delete(self, stored: StoredAvatar) -> None:
        target = (self._upload_root / "marketplace" / stored.filename).resolve()
        directory = (self._upload_root / "marketplace").resolve()
        if target.parent == directory and target.name == stored.filename:
            target.unlink(missing_ok=True)


class ProjectStorage(AvatarStorage):
    """Store validated project images separately from every other content type."""

    def save(self, user_id: int, avatar: PreparedAvatar) -> StoredAvatar:
        directory = (self._upload_root / "projects").resolve()
        if directory.parent != self._upload_root:
            raise UploadStorageError("Project storage path is invalid")
        filename = f"{user_id}_{uuid4().hex}_project.{avatar.extension}"
        target = (directory / filename).resolve()
        if target.parent != directory:
            raise UploadStorageError("Project storage path is invalid")
        temporary = target.with_suffix(target.suffix + ".part")
        try:
            directory.mkdir(parents=True, exist_ok=True)
            with temporary.open("xb") as handle:
                handle.write(avatar.content)
                handle.flush()
                os.fsync(handle.fileno())
            os.replace(temporary, target)
        except OSError as exc:
            temporary.unlink(missing_ok=True)
            raise UploadStorageError("Project image could not be stored") from exc
        return StoredAvatar(
            relative_path=f"uploads/projects/{filename}",
            filename=filename,
            original_filename=avatar.original_filename,
        )

    def delete(self, stored: StoredAvatar) -> None:
        target = (self._upload_root / "projects" / stored.filename).resolve()
        directory = (self._upload_root / "projects").resolve()
        if target.parent == directory and target.name == stored.filename:
            target.unlink(missing_ok=True)


class LeadershipStorage(AvatarStorage):
    """Store validated leadership-photo overrides separately from profiles."""

    def save(self, user_id: int, avatar: PreparedAvatar) -> StoredAvatar:
        directory = (self._upload_root / "leadership").resolve()
        if directory.parent != self._upload_root:
            raise UploadStorageError("Leadership storage path is invalid")
        filename = f"{user_id}_{uuid4().hex}_leader.{avatar.extension}"
        target = (directory / filename).resolve()
        if target.parent != directory:
            raise UploadStorageError("Leadership storage path is invalid")
        temporary = target.with_suffix(target.suffix + ".part")
        try:
            directory.mkdir(parents=True, exist_ok=True)
            with temporary.open("xb") as handle:
                handle.write(avatar.content)
                handle.flush()
                os.fsync(handle.fileno())
            os.replace(temporary, target)
        except OSError as exc:
            temporary.unlink(missing_ok=True)
            raise UploadStorageError("Leadership image could not be stored") from exc
        return StoredAvatar(f"uploads/leadership/{filename}", filename, avatar.original_filename)

    def delete(self, stored: StoredAvatar) -> None:
        target = (self._upload_root / "leadership" / stored.filename).resolve()
        directory = (self._upload_root / "leadership").resolve()
        if target.parent == directory and target.name == stored.filename:
            target.unlink(missing_ok=True)


class VacancyStorage(AvatarStorage):
    """Store validated vacancy flyers separately from other user assets."""

    def save(self, user_id: int, avatar: PreparedAvatar) -> StoredAvatar:
        directory = (self._upload_root / "vacancies").resolve()
        if directory.parent != self._upload_root:
            raise UploadStorageError("Vacancy storage path is invalid")
        filename = f"{user_id}_{uuid4().hex}_vacancy.{avatar.extension}"
        target = (directory / filename).resolve()
        if target.parent != directory:
            raise UploadStorageError("Vacancy storage path is invalid")
        temporary = target.with_suffix(target.suffix + ".part")
        try:
            directory.mkdir(parents=True, exist_ok=True)
            with temporary.open("xb") as handle:
                handle.write(avatar.content)
                handle.flush()
                os.fsync(handle.fileno())
            os.replace(temporary, target)
        except OSError as exc:
            temporary.unlink(missing_ok=True)
            raise UploadStorageError("Vacancy flyer could not be stored") from exc
        return StoredAvatar(f"uploads/vacancies/{filename}", filename, avatar.original_filename)

    def delete(self, stored: StoredAvatar) -> None:
        target = (self._upload_root / "vacancies" / stored.filename).resolve()
        directory = (self._upload_root / "vacancies").resolve()
        if target.parent == directory and target.name == stored.filename:
            target.unlink(missing_ok=True)


class EventStorage(AvatarStorage):
    """Store generated event banners independently of user profile assets."""

    def save(self, user_id: int, avatar: PreparedAvatar) -> StoredAvatar:
        directory = (self._upload_root / "events").resolve()
        if directory.parent != self._upload_root:
            raise UploadStorageError("Event storage path is invalid")
        filename = f"{user_id}_{uuid4().hex}_event.{avatar.extension}"
        target = (directory / filename).resolve()
        if target.parent != directory:
            raise UploadStorageError("Event storage path is invalid")
        temporary = target.with_suffix(target.suffix + ".part")
        try:
            directory.mkdir(parents=True, exist_ok=True)
            with temporary.open("xb") as handle:
                handle.write(avatar.content)
                handle.flush()
                os.fsync(handle.fileno())
            os.replace(temporary, target)
        except OSError as exc:
            temporary.unlink(missing_ok=True)
            raise UploadStorageError("Event banner could not be stored") from exc
        return StoredAvatar(f"uploads/events/{filename}", filename, avatar.original_filename)

    def delete(self, stored: StoredAvatar) -> None:
        target = (self._upload_root / "events" / stored.filename).resolve()
        directory = (self._upload_root / "events").resolve()
        if target.parent == directory and target.name == stored.filename:
            target.unlink(missing_ok=True)


class CarouselStorage(AvatarStorage):
    """Store homepage carousel images independently of user profile assets."""

    def save(self, user_id: int, avatar: PreparedAvatar) -> StoredAvatar:
        directory = (self._upload_root / "homepage" / "carousel").resolve()
        if directory.parent.parent != self._upload_root:
            raise UploadStorageError("Carousel storage path is invalid")
        filename = f"{user_id}_{uuid4().hex}_carousel.{avatar.extension}"
        target = (directory / filename).resolve()
        if target.parent != directory:
            raise UploadStorageError("Carousel storage path is invalid")
        temporary = target.with_suffix(target.suffix + ".part")
        try:
            directory.mkdir(parents=True, exist_ok=True)
            with temporary.open("xb") as handle:
                handle.write(avatar.content)
                handle.flush()
                os.fsync(handle.fileno())
            os.replace(temporary, target)
        except OSError as exc:
            temporary.unlink(missing_ok=True)
            raise UploadStorageError("Carousel image could not be stored") from exc
        return StoredAvatar(
            f"uploads/homepage/carousel/{filename}", filename, avatar.original_filename
        )

    def delete(self, stored: StoredAvatar) -> None:
        target = (self._upload_root / "homepage" / "carousel" / stored.filename).resolve()
        directory = (self._upload_root / "homepage" / "carousel").resolve()
        if target.parent == directory and target.name == stored.filename:
            target.unlink(missing_ok=True)


class BlogGalleryStorage(AvatarStorage):
    """Store blog gallery images independently of other assets."""

    def save(self, user_id: int, avatar: PreparedAvatar) -> StoredAvatar:
        directory = (self._upload_root / "blog" / "gallery").resolve()
        if directory.parent.parent != self._upload_root:
            raise UploadStorageError("Blog gallery storage path is invalid")
        filename = f"{user_id}_{uuid4().hex}_gallery.{avatar.extension}"
        target = (directory / filename).resolve()
        if target.parent != directory:
            raise UploadStorageError("Blog gallery storage path is invalid")
        temporary = target.with_suffix(target.suffix + ".part")
        try:
            directory.mkdir(parents=True, exist_ok=True)
            with temporary.open("xb") as handle:
                handle.write(avatar.content)
                handle.flush()
                os.fsync(handle.fileno())
            os.replace(temporary, target)
        except OSError as exc:
            temporary.unlink(missing_ok=True)
            raise UploadStorageError("Blog gallery image could not be stored") from exc
        return StoredAvatar(f"uploads/blog/gallery/{filename}", filename, avatar.original_filename)

    def delete(self, stored: StoredAvatar) -> None:
        target = (self._upload_root / "blog" / "gallery" / stored.filename).resolve()
        directory = (self._upload_root / "blog" / "gallery").resolve()
        if target.parent == directory and target.name == stored.filename:
            target.unlink(missing_ok=True)


class ProductStorage(AvatarStorage):
    """Store product catalogue images independently of other assets."""

    def save(self, user_id: int, avatar: PreparedAvatar) -> StoredAvatar:
        directory = (self._upload_root / "products").resolve()
        if directory.parent != self._upload_root:
            raise UploadStorageError("Product storage path is invalid")
        filename = f"{user_id}_{uuid4().hex}_product.{avatar.extension}"
        target = (directory / filename).resolve()
        if target.parent != directory:
            raise UploadStorageError("Product storage path is invalid")
        temporary = target.with_suffix(target.suffix + ".part")
        try:
            directory.mkdir(parents=True, exist_ok=True)
            with temporary.open("xb") as handle:
                handle.write(avatar.content)
                handle.flush()
                os.fsync(handle.fileno())
            os.replace(temporary, target)
        except OSError as exc:
            temporary.unlink(missing_ok=True)
            raise UploadStorageError("Product image could not be stored") from exc
        return StoredAvatar(f"uploads/products/{filename}", filename, avatar.original_filename)

    def delete(self, stored: StoredAvatar | str) -> None:
        filename = stored if isinstance(stored, str) else stored.filename
        filename = Path(filename).name
        target = (self._upload_root / "products" / filename).resolve()
        directory = (self._upload_root / "products").resolve()
        if target.parent == directory and target.name == filename:
            target.unlink(missing_ok=True)
