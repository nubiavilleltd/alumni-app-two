"""Authentication primitive tests independent of the database."""

from datetime import UTC, datetime, timedelta

import bcrypt
import jwt
import pytest

from app.core.config import Settings
from app.core.errors import InvalidAccessTokenError, TokenConfigurationError
from app.core.security import PasswordService, TokenService


def test_legacy_bcrypt_verifies_and_requests_argon2_rehash() -> None:
    """Ion Auth `$2y$` hashes remain usable only as a migration bridge."""
    passwords = PasswordService()
    encoded = bcrypt.hashpw(b"correct horse", bcrypt.gensalt(rounds=8)).decode("ascii")
    legacy = encoded.replace("$2b$", "$2y$", 1)
    assert passwords.verify("correct horse", legacy) is True
    assert passwords.verify("wrong horse", legacy) is False
    assert passwords.needs_rehash(legacy) is True


def test_argon2_hash_round_trip_and_malformed_hashes() -> None:
    """New hashes use Argon2id and invalid stored values fail closed."""
    passwords = PasswordService()
    encoded = passwords.hash("a strong passphrase")
    assert encoded.startswith("$argon2id$")
    assert passwords.verify("a strong passphrase", encoded) is True
    assert passwords.verify("wrong", encoded) is False
    assert passwords.verify("anything", None) is False
    assert passwords.verify("anything", "$argon2id$broken") is False
    assert passwords.verify("anything", "sha1-is-not-supported") is False
    assert passwords.needs_rehash("broken") is True


def test_access_token_round_trip_and_refresh_hash(
    auth_settings: Settings,
) -> None:
    """Access claims are signed and refresh-token storage is non-reversible."""
    service = TokenService(auth_settings)
    issued = service.issue(
        {
            "id": 42,
            "email": "member@example.com",
            "user_role": "alumni",
            "fullname": "Synthetic Member",
        },
        now=datetime.now(UTC),
    )
    claims = service.decode_access(issued.access_token)
    assert claims["sub"] == "42"
    assert claims["type"] == "access"
    assert issued.access_expires_in == 900
    digest = service.hash_refresh_token(issued.refresh_token)
    assert len(digest) == 64
    assert issued.refresh_token not in digest


def test_access_token_rejects_tampering_and_wrong_type(
    auth_settings: Settings,
    rsa_pem_pair: tuple[str, str],
) -> None:
    """Signature and access-token type validation fail closed."""
    service = TokenService(auth_settings)
    issued = service.issue({"id": 1, "email": "x@example.com"})
    header, payload, signature = issued.access_token.split(".")
    replacement = "A" if signature[0] != "A" else "B"
    tampered = f"{header}.{payload}.{replacement}{signature[1:]}"
    with pytest.raises(InvalidAccessTokenError):
        service.decode_access(tampered)

    private_pem, _ = rsa_pem_pair
    now = datetime.now(UTC)
    wrong_type = jwt.encode(
        {
            "sub": "1",
            "type": "refresh",
            "iat": now,
            "nbf": now,
            "exp": now + timedelta(minutes=1),
            "iss": auth_settings.jwt_issuer,
            "aud": auth_settings.jwt_audience,
            "jti": "synthetic",
        },
        private_pem,
        algorithm="RS256",
    )
    with pytest.raises(InvalidAccessTokenError):
        service.decode_access(wrong_type)


def test_token_service_requires_both_key_halves(rsa_pem_pair: tuple[str, str]) -> None:
    """Missing signing or verification material produces a safe configuration error."""
    _, public_pem = rsa_pem_pair
    with pytest.raises(TokenConfigurationError, match="signing key"):
        TokenService(Settings(jwt_verification_key=public_pem)).issue(
            {"id": 1, "email": "x@example.com"}
        )
    with pytest.raises(TokenConfigurationError, match="verification key"):
        TokenService(Settings(jwt_signing_key=rsa_pem_pair[0])).decode_access("invalid")


def test_token_service_rejects_invalid_key_material(rsa_pem_pair: tuple[str, str]) -> None:
    """Present but malformed asymmetric keys are configuration failures, not token failures."""
    malformed = "not a PEM key"
    service = TokenService(Settings(jwt_signing_key=malformed, jwt_verification_key=malformed))
    with pytest.raises(TokenConfigurationError, match="signing key is invalid"):
        service.issue({"id": 1, "email": "member@example.com"})
    valid = TokenService(
        Settings(jwt_signing_key=rsa_pem_pair[0], jwt_verification_key=rsa_pem_pair[1])
    ).issue({"id": 1, "email": "member@example.com"})
    with pytest.raises(TokenConfigurationError, match="verification key is invalid"):
        service.decode_access(valid.access_token)
