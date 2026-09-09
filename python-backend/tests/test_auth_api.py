"""Authentication HTTP contract tests that do not require SQL."""

from fastapi.testclient import TestClient

from app.core.config import Settings
from app.core.security import TokenService
from app.main import create_app


def test_login_requires_credentials() -> None:
    """Missing or malformed bodies retain the legacy bounded 400 response."""
    with TestClient(create_app(Settings(environment="test"))) as client:
        missing = client.post("/api/login", json={})
        malformed = client.post(
            "/api/login",
            content="{broken",
            headers={"content-type": "application/json"},
        )
    expected = {"status": 400, "message": "Kindly provide your email and password"}
    assert missing.status_code == 400
    assert missing.json() == expected
    assert malformed.status_code == 400
    assert malformed.json() == expected


def test_login_rejects_invalid_email_before_database_access() -> None:
    """Email syntax keeps the established 422 compatibility signal."""
    with TestClient(create_app(Settings(environment="test"))) as client:
        response = client.post(
            "/api/login",
            data={"identity": "not-an-email", "password": "synthetic"},
        )
    assert response.status_code == 422
    assert response.json() == {"status": 422, "message": "Invalid email format"}


def test_refresh_requires_a_bounded_token() -> None:
    """Empty and undersized refresh credentials fail before SQL access."""
    with TestClient(create_app(Settings(environment="test"))) as client:
        response = client.post("/api/refresh_token", json={"refresh_token": "short"})
    assert response.status_code == 400
    assert response.json() == {"status": 400, "message": "refresh_token is required"}


def test_logout_without_token_is_idempotent() -> None:
    """A client may safely repeat logout even when it has already dropped its token."""
    with TestClient(create_app(Settings(environment="test"))) as client:
        response = client.post("/api/logout", json={})
    assert response.status_code == 200
    assert response.json() == {"status": 200, "message": "Logged out successfully"}


def test_login_openapi_describes_json_and_form_contracts() -> None:
    """Generated API documentation exposes both supported request encodings."""
    schema = create_app(Settings(environment="test")).openapi()
    content = schema["paths"]["/api/login"]["post"]["requestBody"]["content"]
    assert set(content) == {
        "application/json",
        "application/x-www-form-urlencoded",
        "multipart/form-data",
    }
    response_schema = schema["paths"]["/api/login"]["post"]["responses"]["200"]["content"][
        "application/json"
    ]["schema"]
    assert len(response_schema["anyOf"]) == 2


def test_password_recovery_validates_public_inputs_without_sql() -> None:
    """Recovery input errors remain bounded and never require database access."""
    with TestClient(create_app(Settings(environment="test"))) as client:
        missing = client.post("/api/forgot_password", json={})
        malformed = client.post("/api/forgot_password", json={"identity": "not-email"})
        missing_code = client.post("/api/reset_password", json={})
        missing_password = client.post("/api/reset_password", json={"code": "x" * 64})
        mismatch = client.post(
            "/api/reset_password",
            json={
                "code": "x" * 64,
                "new_password": "password-one",
                "new_password_confirm": "password-two",
            },
        )
        too_short = client.post(
            "/api/reset_password",
            json={
                "code": "x" * 64,
                "new_password": "short",
                "new_password_confirm": "short",
            },
        )
    assert missing.status_code == 400
    assert malformed.status_code == 422
    assert missing_code.json()["message"] == "Reset code is required"
    assert missing_password.json()["message"] == "New password is required"
    assert mismatch.json()["message"] == "Passwords do not match"
    assert too_short.json()["message"] == "Password must be at least 8 characters"


def test_change_password_requires_bearer_before_body_validation() -> None:
    """Protected password changes reject unauthenticated callers consistently."""
    with TestClient(create_app(Settings(environment="test"))) as client:
        response = client.post("/api/change_user_password", json={})
        malformed = client.post(
            "/api/change_user_password",
            json={},
            headers={"Authorization": "Basic not-bearer"},
        )
    assert response.status_code == malformed.status_code == 401
    assert response.json()["error"]["message"] == "Authentication required"


def test_auth_openapi_includes_recovery_and_change_contracts() -> None:
    """Every migrated authentication route publishes request and response schemas."""
    schema = create_app(Settings(environment="test")).openapi()
    for path in (
        "/api/forgot_password",
        "/api/reset_password",
        "/api/change_user_password",
    ):
        operation = schema["paths"][path]["post"]
        assert "requestBody" in operation
        assert "200" in operation["responses"]


def test_change_password_validates_token_and_body_before_sql(auth_settings: Settings) -> None:
    """A valid principal reaches bounded body checks without touching an unconfigured database."""
    access_token = (
        TokenService(auth_settings)
        .issue({"id": 7, "email": "member@example.com", "user_role": "alumni"})
        .access_token
    )
    headers = {"Authorization": f"Bearer {access_token}"}
    with TestClient(create_app(auth_settings)) as client:
        missing = client.post("/api/change_user_password", headers=headers, json={})
        mismatch = client.post(
            "/api/change_user_password",
            headers=headers,
            json={
                "old_password": "old passphrase",
                "new_password": "new passphrase one",
                "confirm_password": "new passphrase two",
            },
        )
        short = client.post(
            "/api/change_user_password",
            headers=headers,
            json={
                "old_password": "old passphrase",
                "new_password": "short",
                "confirm_password": "short",
            },
        )
    assert missing.status_code == 400
    assert mismatch.json()["message"] == "new_password and confirm_password do not match"
    assert short.json()["message"] == "New password must be at least 8 characters"


def test_access_dependency_rejects_invalid_token_and_missing_server_key(
    auth_settings: Settings,
) -> None:
    """Malformed tokens return 401 while server key failures return 503."""
    with TestClient(create_app(auth_settings)) as client:
        invalid = client.post(
            "/api/change_user_password",
            headers={"Authorization": "Bearer malformed.token.value"},
            json={},
        )
    assert invalid.status_code == 401
    assert invalid.json()["error"]["message"] == "Invalid access token"

    issued = (
        TokenService(auth_settings).issue({"id": 7, "email": "member@example.com"}).access_token
    )
    missing_key_settings = Settings(
        environment="test",
        jwt_signing_key=auth_settings.jwt_signing_key_value(),
    )
    with TestClient(create_app(missing_key_settings)) as client:
        unavailable = client.post(
            "/api/change_user_password",
            headers={"Authorization": f"Bearer {issued}"},
            json={},
        )
    assert unavailable.status_code == 503
    assert unavailable.json()["error"]["message"] == "Authentication service is unavailable"


def test_email_verification_routes_validate_before_sql() -> None:
    """Missing or malformed verification fields produce bounded 400 responses."""
    with TestClient(create_app(Settings(environment="test"))) as client:
        missing_resend = client.post("/api/resend_verify_email", json={})
        missing_verify = client.post("/api/verify_email", json={})
        malformed_verify = client.post(
            "/api/verify_email",
            json={"user_id": 1, "verify_code": "not-six-digits"},
        )
    assert missing_resend.status_code == 400
    assert missing_resend.json()["message"] == "user_id is required"
    assert missing_verify.status_code == malformed_verify.status_code == 400
    assert missing_verify.json()["message"] == "user_id and verify_code are required"


def test_email_verification_openapi_contracts_are_published() -> None:
    """Both consolidated email-verification routes have explicit schemas."""
    schema = create_app(Settings(environment="test")).openapi()
    for path in ("/api/resend_verify_email", "/api/verify_email"):
        operation = schema["paths"][path]["post"]
        assert "requestBody" in operation
        assert "200" in operation["responses"]


def test_legacy_otp_routes_are_explicitly_retired_for_all_previous_methods() -> None:
    """Unsafe duplicate OTP flows point clients to the consolidated replacements."""
    with TestClient(create_app(Settings(environment="test"))) as client:
        responses = {
            (method, path): client.request(method, path)
            for method in ("GET", "POST")
            for path in ("/api/verify_otp", "/api/resend_otp")
        }

    for (method, path), response in responses.items():
        assert method in {"GET", "POST"}
        assert response.status_code == 410
        assert response.json()["code"] == "endpoint_replaced"
        replacement = (
            "/api/verify_email" if path == "/api/verify_otp" else "/api/resend_verify_email"
        )
        assert replacement in response.json()["message"]


def test_legacy_otp_tombstones_are_deprecated_in_openapi() -> None:
    """Generated clients can distinguish retired routes from active capabilities."""
    schema = create_app(Settings(environment="test")).openapi()
    for path in ("/api/verify_otp", "/api/resend_otp"):
        for method in ("get", "post"):
            assert schema["paths"][path][method]["deprecated"] is True


def test_access_code_verification_requires_bearer_before_body_validation() -> None:
    """The legacy access-code comparison is no longer reachable by user ID alone."""
    with TestClient(create_app(Settings(environment="test"))) as client:
        response = client.post(
            "/api/verify_user_access_code",
            json={"user_id": 7, "access_code": "synthetic"},
        )
    assert response.status_code == 401
    assert response.json()["error"]["message"] == "Authentication required"


def test_access_code_contract_validates_after_valid_bearer(auth_settings: Settings) -> None:
    """A valid principal reaches bounded input validation without database access."""
    access_token = (
        TokenService(auth_settings)
        .issue({"id": 7, "email": "member@example.com", "user_role": "alumni"})
        .access_token
    )
    with TestClient(create_app(auth_settings)) as client:
        response = client.post(
            "/api/verify_user_access_code",
            headers={"Authorization": f"Bearer {access_token}"},
            json={},
        )
    assert response.status_code == 400
    assert response.json()["message"] == "access_code is required"
    assert (
        "requestBody"
        in create_app(auth_settings).openapi()["paths"]["/api/verify_user_access_code"]["post"]
    )


def test_member_profile_requires_bearer_before_target_selection() -> None:
    """A caller cannot retrieve profile data by supplying only a user ID."""
    with TestClient(create_app(Settings(environment="test"))) as client:
        response = client.post("/api/get_user_profile", json={"user_id": 7})
    assert response.status_code == 401
    assert response.json()["error"]["message"] == "Authentication required"


def test_member_profile_contract_rejects_invalid_target_without_database(
    auth_settings: Settings,
) -> None:
    """A valid principal reaches bounded target validation before SQL access."""
    access_token = (
        TokenService(auth_settings)
        .issue({"id": 7, "email": "member@example.com", "user_role": "alumni"})
        .access_token
    )
    app = create_app(auth_settings)
    with TestClient(app) as client:
        response = client.post(
            "/api/get_user_profile",
            headers={"Authorization": f"Bearer {access_token}"},
            json={"user_id": 0},
        )
    assert response.status_code == 400
    assert response.json()["message"] == "user_id must be a positive integer"
    assert (
        app.openapi()["paths"]["/api/get_user_profile"]["post"]["requestBody"]["required"] is False
    )
