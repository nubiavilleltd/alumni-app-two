"""Password compatibility, JWT signing, and opaque refresh-token primitives."""

from __future__ import annotations

import hashlib
import secrets
from dataclasses import dataclass
from datetime import UTC, datetime, timedelta
from typing import Any

import bcrypt
import jwt
from argon2 import PasswordHasher
from argon2.exceptions import InvalidHashError, VerificationError, VerifyMismatchError
from jwt.exceptions import InvalidKeyError

from app.core.config import Settings
from app.core.errors import InvalidAccessTokenError, TokenConfigurationError

_ARGON2 = PasswordHasher(time_cost=3, memory_cost=65536, parallelism=4)
_DUMMY_BCRYPT_HASH = b"$2b$08$3euPcmQFCiblsZeEu5s7p.9HDaY8Xoukkxnq4ZVhE1/xpqHGozJma"


class PasswordService:
    """Verify legacy bcrypt and modern Argon2id hashes without account oracles."""

    def verify(self, password: str, encoded_hash: str | None) -> bool:
        """Verify a password, doing comparable work when the account is absent."""
        candidate = password.encode("utf-8")
        if not encoded_hash:
            bcrypt.checkpw(candidate, _DUMMY_BCRYPT_HASH)
            return False
        try:
            if encoded_hash.startswith(("$2y$", "$2b$", "$2a$")):
                return bcrypt.checkpw(candidate, encoded_hash.encode("ascii"))
            if encoded_hash.startswith("$argon2"):
                return _ARGON2.verify(encoded_hash, password)
        except (ValueError, VerificationError, VerifyMismatchError, InvalidHashError):
            return False
        return False

    def needs_rehash(self, encoded_hash: str) -> bool:
        """Return whether a successful login should replace the stored hash."""
        if not encoded_hash.startswith("$argon2"):
            return True
        try:
            return _ARGON2.check_needs_rehash(encoded_hash)
        except InvalidHashError:
            return True

    def hash(self, password: str) -> str:
        """Hash a password using the approved Argon2id policy."""
        return _ARGON2.hash(password)


@dataclass(frozen=True, slots=True)
class IssuedTokens:
    """A short-lived access token paired with a one-time refresh token."""

    access_token: str
    refresh_token: str
    access_expires_in: int
    refresh_expires_at: datetime


class TokenService:
    """Issue and validate asymmetric access JWTs and opaque refresh tokens."""

    def __init__(self, settings: Settings) -> None:
        self._settings = settings

    def issue(self, user: dict[str, Any], now: datetime | None = None) -> IssuedTokens:
        """Create a signed access JWT and a cryptographically random refresh token."""
        issued_at = (now or datetime.now(UTC)).astimezone(UTC)
        access_expiry = issued_at + timedelta(minutes=self._settings.access_token_ttl_minutes)
        refresh_expiry = issued_at + timedelta(days=self._settings.refresh_token_ttl_days)
        signing_key = self._signing_key()
        claims: dict[str, Any] = {
            "sub": str(user["id"]),
            "type": "access",
            "email": user["email"],
            "user_role": user.get("user_role"),
            "fullname": user.get("fullname"),
            "iat": issued_at,
            "nbf": issued_at,
            "exp": access_expiry,
            "iss": self._settings.jwt_issuer,
            "aud": self._settings.jwt_audience,
            "jti": secrets.token_hex(16),
        }
        try:
            access_token = jwt.encode(
                claims,
                signing_key,
                algorithm=self._settings.jwt_algorithm,
            )
        except (InvalidKeyError, TypeError, ValueError) as exc:
            raise TokenConfigurationError("JWT signing key is invalid") from exc
        return IssuedTokens(
            access_token=access_token,
            refresh_token=secrets.token_urlsafe(48),
            access_expires_in=self._settings.access_token_ttl_minutes * 60,
            refresh_expires_at=refresh_expiry.replace(tzinfo=None),
        )

    def decode_access(self, token: str) -> dict[str, Any]:
        """Validate an access token and return its bounded claims."""
        try:
            claims = jwt.decode(
                token,
                self._verification_key(),
                algorithms=[self._settings.jwt_algorithm],
                audience=self._settings.jwt_audience,
                issuer=self._settings.jwt_issuer,
                options={"require": ["sub", "type", "iat", "nbf", "exp", "iss", "aud", "jti"]},
            )
        except InvalidKeyError as exc:
            raise TokenConfigurationError("JWT verification key is invalid") from exc
        except jwt.PyJWTError as exc:
            raise InvalidAccessTokenError from exc
        if claims.get("type") != "access":
            raise InvalidAccessTokenError
        return claims

    @staticmethod
    def hash_refresh_token(token: str) -> str:
        """Return the non-reversible database representation of a refresh token."""
        return hashlib.sha256(token.encode("utf-8")).hexdigest()

    def _signing_key(self) -> str:
        key = self._settings.jwt_signing_key_value()
        if not key:
            raise TokenConfigurationError("JWT signing key is not configured")
        return key

    def _verification_key(self) -> str:
        key = self._settings.jwt_verification_key_value()
        if not key:
            raise TokenConfigurationError("JWT verification key is not configured")
        return key
