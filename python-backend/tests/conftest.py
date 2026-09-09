"""Shared cryptographic fixtures for isolated authentication tests."""

import pytest
from cryptography.hazmat.primitives import serialization
from cryptography.hazmat.primitives.asymmetric import rsa

from app.core.config import Settings


@pytest.fixture(scope="session")
def rsa_pem_pair() -> tuple[str, str]:
    """Generate an ephemeral key pair that never leaves the test process."""
    private_key = rsa.generate_private_key(public_exponent=65537, key_size=2048)
    private_pem = private_key.private_bytes(
        encoding=serialization.Encoding.PEM,
        format=serialization.PrivateFormat.PKCS8,
        encryption_algorithm=serialization.NoEncryption(),
    ).decode("ascii")
    public_pem = (
        private_key.public_key()
        .public_bytes(
            encoding=serialization.Encoding.PEM,
            format=serialization.PublicFormat.SubjectPublicKeyInfo,
        )
        .decode("ascii")
    )
    return private_pem, public_pem


@pytest.fixture
def auth_settings(rsa_pem_pair: tuple[str, str]) -> Settings:
    """Supply test settings with ephemeral signing material."""
    private_pem, public_pem = rsa_pem_pair
    return Settings(
        environment="test",
        jwt_signing_key=private_pem,
        jwt_verification_key=public_pem,
        public_base_url="https://alumni.example.test/",
    )
