"""Authentication HTTP contract tests that do not require SQL."""

from datetime import UTC, datetime

from fastapi.testclient import TestClient

from app.core.config import Settings
from app.core.security import TokenService
from app.main import create_app
from app.schemas.members import GetBirthdaysRequest


def test_registration_rejects_malformed_or_weak_public_inputs_before_sql() -> None:
    """Registration applies the bounded current-form contract without touching a database."""
    base = {
        "email": "member@example.com",
        "password": "Strong registration passphrase! 7",
        "first_name": "Synthetic",
        "last_name": "Member",
        "phone": "08000000001",
        "chapter_id": 1,
        "graduation_year": datetime.now(UTC).year,
        "city": "Synthetic City",
    }
    with TestClient(create_app(Settings(environment="test"))) as client:
        missing = client.post("/api/register", json={})
        weak = client.post("/api/register", json={**base, "password": "alllowercase"})
        future = client.post(
            "/api/register",
            json={**base, "graduation_year": datetime.now(UTC).year + 1},
        )
        invalid_avatar = client.post(
            "/api/register",
            data={key: str(value) for key, value in base.items()},
            files={"avatar": ("avatar.txt", b"not an image", "text/plain")},
        )

    for response in (missing, weak, future, invalid_avatar):
        assert response.status_code == 400
        assert response.json()["code"] == "registration_invalid_request"


def test_registration_openapi_excludes_client_privileges_and_describes_avatar() -> None:
    """Generated clients see only server-reviewed registration fields and response models."""
    operation = create_app(Settings(environment="test")).openapi()["paths"]["/api/register"]["post"]
    content = operation["requestBody"]["content"]
    assert set(content) == {
        "application/json",
        "application/x-www-form-urlencoded",
        "multipart/form-data",
    }
    json_properties = content["application/json"]["schema"]["properties"]
    assert not ({"user_role", "is_coordinator", "year", "token"} & json_properties.keys())
    assert content["multipart/form-data"]["schema"]["properties"]["avatar"] == {
        "type": "string",
        "format": "binary",
    }
    assert len(operation["responses"]["200"]["content"]["application/json"]["schema"]["anyOf"]) == 2


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


def test_alumni_stats_requires_bearer_and_publishes_get_and_post_contracts(
    auth_settings: Settings,
) -> None:
    """Aggregate stats never accept the legacy shared API token as authorization."""
    app = create_app(auth_settings)
    with TestClient(app) as client:
        unauthenticated_get = client.get("/api/get_alumni_stats", params={"token": "legacy"})
        unauthenticated_post = client.post(
            "/api/get_alumni_stats",
            json={"token": "legacy"},
        )

    assert unauthenticated_get.status_code == unauthenticated_post.status_code == 401
    assert unauthenticated_get.json()["error"]["message"] == "Authentication required"
    assert unauthenticated_post.json()["error"]["message"] == "Authentication required"
    path = app.openapi()["paths"]["/api/get_alumni_stats"]
    assert set(path) == {"get", "post"}
    assert "requestBody" not in path["get"]
    assert "requestBody" not in path["post"]
    assert "200" in path["get"]["responses"]
    assert "200" in path["post"]["responses"]


def test_birthdays_require_bearer_and_publish_query_only_get_and_post_contracts(
    auth_settings: Settings,
) -> None:
    """Birthday data rejects legacy tokens and documents the active bodyless POST caller."""
    app = create_app(auth_settings)
    access_token = (
        TokenService(auth_settings)
        .issue({"id": 7, "email": "member@example.com", "user_role": "alumni"})
        .access_token
    )
    headers = {"Authorization": f"Bearer {access_token}"}
    with TestClient(app) as client:
        unauthenticated_get = client.get(
            "/api/get_birthdays",
            params={"token": "legacy"},
        )
        unauthenticated_post = client.post(
            "/api/get_birthdays",
            json={"token": "legacy"},
        )
        invalid_scope = client.get(
            "/api/get_birthdays",
            headers=headers,
            params={"scope": "year"},
        )
        invalid_month = client.post(
            "/api/get_birthdays",
            headers=headers,
            params={"scope": "month", "month": "13"},
        )

    assert unauthenticated_get.status_code == unauthenticated_post.status_code == 401
    assert unauthenticated_get.json()["error"]["message"] == "Authentication required"
    assert unauthenticated_post.json()["error"]["message"] == "Authentication required"
    assert invalid_scope.status_code == invalid_month.status_code == 400
    assert invalid_scope.json()["code"] == "birthdays_invalid_request"
    assert invalid_month.json()["code"] == "birthdays_invalid_request"

    path = app.openapi()["paths"]["/api/get_birthdays"]
    assert set(path) == {"get", "post"}
    for operation in path.values():
        assert "requestBody" not in operation
        assert {parameter["name"] for parameter in operation["parameters"]} == {
            "scope",
            "days",
            "month",
            "limit",
            "include_self",
        }
        assert "200" in operation["responses"]


def test_birthday_filters_preserve_legacy_defaults_clamps_and_boolean_parsing() -> None:
    """The compatibility model normalizes PHP-style query values before database access."""
    defaults = GetBirthdaysRequest.model_validate(
        {"scope": " ", "days": "bad", "month": "bad", "limit": "bad", "include_self": ""}
    )
    bounded = GetBirthdaysRequest.model_validate(
        {
            "scope": " UPCOMING ",
            "days": "999",
            "limit": "999",
            "include_self": "not-a-true-value",
        }
    )
    assert defaults.scope == "today"
    assert defaults.days == 30
    assert defaults.limit == 50
    assert defaults.include_self is True
    assert bounded.scope == "upcoming"
    assert bounded.days == 365
    assert bounded.limit == 200
    assert bounded.include_self is False


def test_chapter_contract_is_public_only_without_a_user_target(
    auth_settings: Settings,
) -> None:
    """User assignment lookups require Bearer auth while public list metadata does not."""
    app = create_app(auth_settings)
    with TestClient(app) as client:
        unauthenticated_get = client.get("/api/get_chapters", params={"user_id": 7})
        unauthenticated_post = client.post("/api/get_chapters", json={"user_id": 7})
        malformed_bearer = client.get(
            "/api/get_chapters",
            headers={"Authorization": "Bearer invalid"},
        )
        invalid_target = client.post("/api/get_chapters", json={"user_id": 0})

    assert unauthenticated_get.status_code == unauthenticated_post.status_code == 401
    assert unauthenticated_get.json()["error"]["message"] == "Authentication required"
    assert unauthenticated_post.json()["error"]["message"] == "Authentication required"
    assert malformed_bearer.status_code == 401
    assert malformed_bearer.json()["error"]["message"] == "Invalid access token"
    assert invalid_target.status_code == 400
    assert invalid_target.json()["code"] == "chapter_invalid_request"
    path = app.openapi()["paths"]["/api/get_chapters"]
    assert set(path) == {"get", "post"}
    assert path["get"]["parameters"][0]["name"] == "user_id"
    assert path["post"]["requestBody"]["required"] is False
    assert "200" in path["get"]["responses"]
    assert "200" in path["post"]["responses"]


def test_voucher_contracts_bound_public_data_and_protect_owned_workflows(
    auth_settings: Settings,
) -> None:
    """Voucher discovery is minimal/public while pending rows and decisions require Bearer."""
    access_token = (
        TokenService(auth_settings)
        .issue({"id": 7, "email": "voucher@example.com", "user_role": "alumni"})
        .access_token
    )
    app = create_app(auth_settings)
    with TestClient(app) as client:
        invalid_get = client.get("/api/get_vouchers", params={"graduation_year": "bad"})
        invalid_post = client.post("/api/get_vouchers", json={"graduation_year": 1965})
        pending = client.post("/api/voucher_pending")
        action = client.post(
            "/api/vouch_action",
            headers={"Authorization": f"Bearer {access_token}"},
            json={},
        )

    assert invalid_get.status_code == invalid_post.status_code == 400
    assert invalid_get.json()["code"] == "voucher_list_invalid_request"
    assert invalid_post.json()["code"] == "voucher_list_invalid_request"
    assert pending.status_code == 401
    assert pending.json()["error"]["message"] == "Authentication required"
    assert action.status_code == 400
    assert action.json()["code"] == "vouch_invalid_request"

    schema = app.openapi()
    assert set(schema["paths"]["/api/get_vouchers"]) == {"get", "post"}
    assert schema["paths"]["/api/get_vouchers"]["post"]["requestBody"]["required"] is False
    assert set(schema["paths"]["/api/voucher_pending"]) == {"get", "post"}
    assert set(schema["paths"]["/api/vouch_action"]) == {"post"}
    public_fields = schema["components"]["schemas"]["PublicVoucher"]["properties"]
    assert set(public_fields) == {
        "voucher_id",
        "fullname",
        "graduation_year",
        "chapter_id",
    }
    assert not ({"email", "phone", "department", "avatar", "user_role"} & public_fields.keys())


def test_public_geography_contracts_preserve_forms_without_coordinator_email(
    auth_settings: Settings,
) -> None:
    """City and welfare catalogues support GET/POST with explicit public projections."""
    app = create_app(auth_settings)
    schema = app.openapi()

    assert set(schema["paths"]["/api/get_cities"]) == {"get", "post"}
    assert set(schema["paths"]["/api/get_zones"]) == {"get", "post"}
    for route in ("/api/get_cities", "/api/get_zones"):
        assert "security" not in schema["paths"][route]["get"]
        assert "security" not in schema["paths"][route]["post"]

    city_fields = schema["components"]["schemas"]["PublicCity"]["properties"]
    assert set(city_fields) == {"city_id", "city", "chapter_id", "zone_id", "zone"}
    coordinator_fields = schema["components"]["schemas"]["PublicZoneCoordinator"]["properties"]
    assert set(coordinator_fields) == {
        "user_id",
        "name",
        "first_name",
        "last_name",
        "phone",
        "avatar",
    }
    assert {"email", "password", "user_role", "department"}.isdisjoint(coordinator_fields)
    zone_fields = schema["components"]["schemas"]["PublicZone"]["properties"]
    assert set(zone_fields) == {"zone_id", "zone", "chapter_id", "coordinator", "cities"}


def test_zone_membership_contracts_require_bearer_and_bound_member_fields(
    auth_settings: Settings,
) -> None:
    """Zone membership is protected and cannot reproduce the legacy broad PII record."""
    access_token = (
        TokenService(auth_settings)
        .issue({"id": 7, "email": "member@example.com", "user_role": "alumni"})
        .access_token
    )
    headers = {"Authorization": f"Bearer {access_token}"}
    app = create_app(auth_settings)
    with TestClient(app) as client:
        unauthenticated_get = client.get(
            "/api/get_users_by_zone",
            params={"zone_id": 1},
            headers={"X-API-Key": "legacy-shared-key"},
        )
        unauthenticated_post = client.post(
            "/api/get_users_by_zone",
            json={"zone_id": 1, "token": "legacy-shared-key"},
        )
        unauthenticated_my_zone = client.get(
            "/api/get_my_zone",
            headers={"X-API-Key": "legacy-shared-key"},
        )
        missing_selector = client.post("/api/get_users_by_zone", headers=headers, json={})
        invalid_page = client.get(
            "/api/get_users_by_zone",
            headers=headers,
            params={"zone_id": 1, "limit": 101},
        )

    assert unauthenticated_get.status_code == unauthenticated_post.status_code == 401
    assert unauthenticated_my_zone.status_code == 401
    assert unauthenticated_get.json()["error"]["message"] == "Authentication required"
    assert unauthenticated_post.json()["error"]["message"] == "Authentication required"
    assert unauthenticated_my_zone.json()["error"]["message"] == "Authentication required"
    assert missing_selector.status_code == invalid_page.status_code == 400
    assert missing_selector.json()["code"] == "zone_members_invalid_request"
    assert invalid_page.json()["code"] == "zone_members_invalid_request"

    schema = app.openapi()
    assert set(schema["paths"]["/api/get_users_by_zone"]) == {"get", "post"}
    assert set(schema["paths"]["/api/get_my_zone"]) == {"get", "post"}
    assert schema["paths"]["/api/get_users_by_zone"]["post"]["requestBody"]["required"] is True
    assert "requestBody" not in schema["paths"]["/api/get_my_zone"]["post"]

    member_fields = schema["components"]["schemas"]["ZoneMemberUser"]["properties"]
    assert set(member_fields) == {
        "user_id",
        "fullname",
        "first_name",
        "last_name",
        "graduation_year",
        "avatar",
        "phone",
        "city",
        "is_coordinator",
    }
    assert {
        "email",
        "password",
        "user_code",
        "residential_address",
        "birth_date",
        "department",
        "user_role",
        "active",
        "is_approved",
        "email_verified",
    }.isdisjoint(member_fields)
    member_zone_fields = schema["components"]["schemas"]["MemberZone"]["properties"]
    assert set(member_zone_fields) == {"zone_id", "zone", "coordinator"}


def test_setup_parameters_requires_bearer_and_a_bounded_name(
    auth_settings: Settings,
) -> None:
    """A shared legacy API key cannot read arbitrary configuration values."""
    access_token = (
        TokenService(auth_settings)
        .issue({"id": 7, "email": "member@example.com", "user_role": "alumni"})
        .access_token
    )
    app = create_app(auth_settings)
    with TestClient(app) as client:
        unauthenticated = client.post(
            "/api/get_setup_parameters",
            headers={"X-API-Key": "legacy-shared-value"},
            json={"action_type": "Currency"},
        )
        missing = client.post(
            "/api/get_setup_parameters",
            headers={"Authorization": f"Bearer {access_token}"},
            json={},
        )
        blank = client.post(
            "/api/get_setup_parameters",
            headers={"Authorization": f"Bearer {access_token}"},
            data={"action_type": "   "},
        )

    assert unauthenticated.status_code == 401
    assert unauthenticated.json()["error"]["message"] == "Authentication required"
    assert missing.status_code == blank.status_code == 400
    assert missing.json()["code"] == blank.json()["code"] == "setup_parameters_invalid_request"
    operation = app.openapi()["paths"]["/api/get_setup_parameters"]["post"]
    assert operation["requestBody"]["required"] is True
    assert set(operation["requestBody"]["content"]) == {
        "application/json",
        "application/x-www-form-urlencoded",
        "multipart/form-data",
    }
    assert "200" in operation["responses"]


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


def test_member_approval_requires_bearer_and_publishes_a_bounded_contract(
    auth_settings: Settings,
) -> None:
    """The approval route authenticates first and validates its dedicated request schema."""
    access_token = (
        TokenService(auth_settings)
        .issue({"id": 7, "email": "member@example.com", "user_role": "superadmin"})
        .access_token
    )
    app = create_app(auth_settings)
    with TestClient(app) as client:
        unauthenticated = client.post(
            "/api/approve_user",
            json={"user_id": 8, "action": "approve"},
        )
        malformed = client.post(
            "/api/approve_user",
            headers={"Authorization": f"Bearer {access_token}"},
            json={"user_id": 0, "action": "promote", "reject_reason": "x" * 1001},
        )

    assert unauthenticated.status_code == 401
    assert malformed.status_code == 400
    assert malformed.json() == {
        "status": 400,
        "message": "user_id and action (approve|reject) are required",
        "code": "approval_invalid_request",
    }
    operation = app.openapi()["paths"]["/api/approve_user"]["post"]
    assert operation["requestBody"]["required"] is True
    assert set(operation["requestBody"]["content"]) == {
        "application/json",
        "application/x-www-form-urlencoded",
        "multipart/form-data",
    }


def test_account_management_requires_bearer_and_validates_one_bounded_operation(
    auth_settings: Settings,
) -> None:
    """The migrated route authenticates first and exposes only reviewed operations."""
    access_token = (
        TokenService(auth_settings)
        .issue({"id": 7, "email": "member@example.com", "user_role": "admin"})
        .access_token
    )
    app = create_app(auth_settings)
    with TestClient(app) as client:
        unauthenticated = client.post(
            "/api/manage_user_account",
            json={"action": "deactivate"},
        )
        malformed = client.post(
            "/api/manage_user_account",
            headers={"Authorization": f"Bearer {access_token}"},
            json={"user_id": 0, "action": "promote"},
        )
        combined = client.post(
            "/api/manage_user_account",
            headers={"Authorization": f"Bearer {access_token}"},
            json={"user_id": 8, "action": "activate", "user_role": "content admin"},
        )
        unknown_role = client.post(
            "/api/manage_user_account",
            headers={"Authorization": f"Bearer {access_token}"},
            json={"user_id": 8, "user_role": "database owner"},
        )

    assert unauthenticated.status_code == 401
    assert malformed.status_code == 400
    assert malformed.json()["code"] == "account_invalid_request"
    assert combined.status_code == unknown_role.status_code == 400
    assert combined.json()["code"] == unknown_role.json()["code"] == "account_invalid_request"
    operation = app.openapi()["paths"]["/api/manage_user_account"]["post"]
    assert operation["requestBody"]["required"] is True
    assert set(operation["requestBody"]["content"]) == {
        "application/json",
        "application/x-www-form-urlencoded",
        "multipart/form-data",
    }
    request_schema = operation["requestBody"]["content"]["application/json"]["schema"]
    role_values = request_schema["properties"]["user_role"]["anyOf"][0]["enum"]
    assert role_values == [
        "alumni",
        "manager",
        "admin",
        "super admin",
        "approval admin",
        "content admin",
        "storekeeper admin",
        "event admin",
        "finance admin",
    ]


def test_profile_visibility_routes_require_bearer_and_publish_bounded_contracts(
    auth_settings: Settings,
) -> None:
    """Visibility reads/writes authenticate first and reject invalid fields before SQL."""
    access_token = (
        TokenService(auth_settings)
        .issue({"id": 7, "email": "member@example.com", "user_role": "alumni"})
        .access_token
    )
    headers = {"Authorization": f"Bearer {access_token}"}
    app = create_app(auth_settings)
    with TestClient(app) as client:
        unauthenticated = client.post("/api/get_profile_visibility", json={})
        invalid_target = client.post(
            "/api/get_profile_visibility",
            headers=headers,
            json={"user_id": 0},
        )
        empty_update = client.post(
            "/api/update_profile_visibility",
            headers=headers,
            json={"unknown_visible": True},
        )
        invalid_value = client.post(
            "/api/update_profile_visibility",
            headers=headers,
            json={"phone_visible": "sometimes"},
        )

    assert unauthenticated.status_code == 401
    assert invalid_target.status_code == 400
    assert invalid_target.json()["code"] == "visibility_invalid_request"
    assert empty_update.status_code == invalid_value.status_code == 400
    assert empty_update.json()["code"] == "visibility_invalid_request"
    schema = app.openapi()
    get_operation = schema["paths"]["/api/get_profile_visibility"]["post"]
    update_operation = schema["paths"]["/api/update_profile_visibility"]["post"]
    assert get_operation["requestBody"]["required"] is False
    assert update_operation["requestBody"]["required"] is True
    assert "200" in get_operation["responses"]
    assert "200" in update_operation["responses"]


def test_member_listing_requires_bearer_and_publishes_bounded_filters(
    auth_settings: Settings,
) -> None:
    """The shared listing route rejects unsafe actions and unbounded pagination before SQL."""
    access_token = (
        TokenService(auth_settings)
        .issue({"id": 7, "email": "member@example.com", "user_role": "alumni"})
        .access_token
    )
    headers = {"Authorization": f"Bearer {access_token}"}
    app = create_app(auth_settings)
    with TestClient(app) as client:
        unauthenticated = client.post(
            "/api/get_users_by_action",
            json={"action_type": "approved"},
        )
        unsafe_action = client.post(
            "/api/get_users_by_action",
            headers=headers,
            json={"action_type": "anything else"},
        )
        oversized_page = client.post(
            "/api/get_users_by_action",
            headers=headers,
            json={"limit": 101},
        )

    assert unauthenticated.status_code == 401
    assert unsafe_action.status_code == oversized_page.status_code == 400
    assert unsafe_action.json() == {
        "status": 400,
        "message": "Invalid member-list filters",
        "code": "members_invalid_request",
    }
    operation = app.openapi()["paths"]["/api/get_users_by_action"]["post"]
    assert operation["requestBody"]["required"] is False
    assert set(operation["requestBody"]["content"]) == {
        "application/json",
        "application/x-www-form-urlencoded",
        "multipart/form-data",
    }
    assert "200" in operation["responses"]


def test_event_routes_protect_writes_and_publish_only_safe_public_fields(
    auth_settings: Settings,
) -> None:
    """Event administration is Bearer-only and its public contract omits account fields."""
    access_token = (
        TokenService(auth_settings)
        .issue({"id": 7, "email": "events@example.com", "user_role": "event admin"})
        .access_token
    )
    app = create_app(auth_settings)
    with TestClient(app) as client:
        unauthenticated = client.post("/api/create_event", json={})
        malformed_public = client.post("/api/get_events", json={"year": "not-a-year"})
        malformed_write = client.post(
            "/api/create_event",
            headers={"Authorization": f"Bearer {access_token}"},
            json={},
        )

    assert unauthenticated.status_code == 401
    assert malformed_public.status_code == malformed_write.status_code == 400
    assert malformed_public.json()["code"] == "event_filters_invalid"
    assert malformed_write.json()["code"] == "event_invalid_request"

    schema = app.openapi()
    assert set(schema["paths"]["/api/get_events"]) == {"post"}
    assert set(schema["paths"]["/api/create_event"]) == {"post"}
    assert set(schema["paths"]["/api/manage_event"]) == {"post"}
    create_content = schema["paths"]["/api/create_event"]["post"]["requestBody"]["content"]
    assert set(create_content) == {
        "application/json",
        "application/x-www-form-urlencoded",
        "multipart/form-data",
    }
    create_fields = create_content["application/json"]["schema"]["properties"]
    assert {"created_by", "is_approved", "token"}.isdisjoint(create_fields)
    event_fields = schema["components"]["schemas"]["EventItem"]["properties"]
    assert {"email", "created_by", "user_role", "password"}.isdisjoint(event_fields)


def test_event_rsvp_routes_bind_members_to_their_own_identity_and_bound_attendee_data(
    auth_settings: Settings,
) -> None:
    """RSVPs never accept a client-selected account, while attendee PII stays protected."""
    access_token = (
        TokenService(auth_settings)
        .issue({"id": 7, "email": "member@example.com", "user_role": "alumni"})
        .access_token
    )
    headers = {"Authorization": f"Bearer {access_token}"}
    app = create_app(auth_settings)
    with TestClient(app) as client:
        unauthenticated = [
            client.post(path, json={})
            for path in (
                "/api/register_event",
                "/api/manage_event_rsvp",
                "/api/get_event_attendees",
            )
        ]
        invalid_registration = client.post(
            "/api/register_event", headers=headers, json={"event_id": 0, "user_id": 99}
        )
        invalid_update = client.post(
            "/api/manage_event_rsvp",
            headers=headers,
            json={"event_id": 1, "function_type": "update"},
        )
        invalid_attendees = client.post(
            "/api/get_event_attendees", headers=headers, json={"event_id": 1, "limit": 101}
        )

    assert [response.status_code for response in unauthenticated] == [401, 401, 401]
    assert (
        invalid_registration.status_code
        == invalid_update.status_code
        == invalid_attendees.status_code
        == 400
    )
    assert (
        invalid_registration.json()["code"]
        == invalid_update.json()["code"]
        == "event_rsvp_invalid_request"
    )
    assert invalid_attendees.json()["code"] == "event_attendees_invalid_request"

    schema = app.openapi()
    for path in ("/api/register_event", "/api/manage_event_rsvp", "/api/get_event_attendees"):
        assert set(schema["paths"][path]) == {"post"}
    registration_fields = schema["paths"]["/api/register_event"]["post"]["requestBody"]["content"][
        "application/json"
    ]["schema"]["properties"]
    update_fields = schema["paths"]["/api/manage_event_rsvp"]["post"]["requestBody"]["content"][
        "application/json"
    ]["schema"]["properties"]
    assert "user_id" not in registration_fields
    assert "user_id" not in update_fields
    attendee_fields = schema["components"]["schemas"]["EventAttendee"]["properties"]
    assert {"email", "phone", "avatar"}.issubset(attendee_fields)


def test_event_registration_form_routes_are_bearer_only_bounded_and_do_not_expose_identity_fields(
    auth_settings: Settings,
) -> None:
    """Registration forms reject malformed input before SQL and never trust body identities."""
    access_token = (
        TokenService(auth_settings)
        .issue({"id": 7, "email": "member@example.com", "user_role": "alumni"})
        .access_token
    )
    headers = {"Authorization": f"Bearer {access_token}"}
    paths = (
        "/api/create_event_registration_form",
        "/api/manage_event_registration_form",
        "/api/get_event_registration_forms",
        "/api/register_event_with_forms",
        "/api/get_event_registration_submissions",
        "/api/get_event_registration_submission_detail",
    )
    app = create_app(auth_settings)
    with TestClient(app) as client:
        unauthenticated = [client.post(path, json={}) for path in paths]
        invalid = [
            client.post("/api/create_event_registration_form", headers=headers, json={}),
            client.post(
                "/api/manage_event_registration_form",
                headers=headers,
                json={"action": "archive"},
            ),
            client.post("/api/get_event_registration_forms", headers=headers, json={"event_id": 0}),
            client.post("/api/register_event_with_forms", headers=headers, json={"event_id": 0}),
            client.post(
                "/api/get_event_registration_submissions",
                headers=headers,
                json={"event_id": 1, "per_page": 101},
            ),
            client.post(
                "/api/get_event_registration_submission_detail",
                headers=headers,
                json={"event_id": 1},
            ),
        ]

    assert [response.status_code for response in unauthenticated] == [401] * len(paths)
    assert [response.status_code for response in invalid] == [400] * len(invalid)
    assert invalid[3].json()["code"] == "event_form_answers_invalid"
    assert invalid[0].json()["code"] == invalid[1].json()["code"] == "event_form_invalid_request"

    schema = app.openapi()
    assert all(set(schema["paths"][path]) == {"post"} for path in paths)
    form_fields = schema["components"]["schemas"]["EventRegistrationForm"]["properties"]
    answer_schema = schema["paths"]["/api/register_event_with_forms"]["post"]["requestBody"][
        "content"
    ]["application/json"]["schema"]
    if "$ref" in answer_schema:
        answer_schema = schema["components"]["schemas"][answer_schema["$ref"].rsplit("/", 1)[-1]]
    assert {"created_by", "user_id", "email"}.isdisjoint(form_fields)
    assert "user_id" not in answer_schema["properties"]


def test_profile_update_requires_bearer_and_validates_fields_and_avatar(
    auth_settings: Settings,
) -> None:
    """Profile edits authenticate first and reject empty, unsafe, or spoofed input before SQL."""
    access_token = (
        TokenService(auth_settings)
        .issue({"id": 7, "email": "member@example.com", "user_role": "alumni"})
        .access_token
    )
    headers = {"Authorization": f"Bearer {access_token}"}
    app = create_app(auth_settings)
    with TestClient(app) as client:
        unauthenticated = client.post(
            "/api/update_profile",
            json={"first_name": "Member"},
        )
        empty = client.post(
            "/api/update_profile",
            headers=headers,
            json={"user_id": 7, "email": "cannot-change@example.com"},
        )
        invalid_year = client.post(
            "/api/update_profile",
            headers=headers,
            json={"graduation_year": 1800},
        )
        spoofed_avatar = client.post(
            "/api/update_profile",
            headers=headers,
            files={"avatar": ("avatar.png", b"not an image", "image/png")},
            data={"user_id": "7"},
        )

    assert unauthenticated.status_code == 401
    assert empty.status_code == 400
    assert empty.json()["code"] == "profile_update_empty"
    assert invalid_year.status_code == 400
    assert invalid_year.json()["code"] == "profile_update_invalid_request"
    assert spoofed_avatar.status_code == 400
    assert spoofed_avatar.json()["message"] == "Avatar file is not a valid image"
    operation = app.openapi()["paths"]["/api/update_profile"]["post"]
    assert operation["requestBody"]["required"] is True
    multipart = operation["requestBody"]["content"]["multipart/form-data"]["schema"]
    assert multipart["properties"]["avatar"] == {"type": "string", "format": "binary"}
    assert "200" in operation["responses"]


def test_geography_import_authenticates_before_parsing_and_documents_one_file(
    auth_settings: Settings,
) -> None:
    """Unauthenticated bytes do no parser work; authenticated invalid formats fail before SQL."""
    settings = auth_settings
    access_token = (
        TokenService(settings)
        .issue({"id": 7, "email": "member@example.com", "user_role": "admin"})
        .access_token
    )
    with TestClient(create_app(settings)) as client:
        unauthenticated = client.post(
            "/api/upload_zones_cities",
            headers={"X-API-Key": "legacy-key-has-no-authority"},
            files={"file": ("locations.xlsx", b"not-a-workbook", "application/octet-stream")},
        )
        legacy_xls = client.post(
            "/api/upload_zones_cities",
            headers={"Authorization": f"Bearer {access_token}"},
            files={"file": ("locations.xls", b"legacy", "application/vnd.ms-excel")},
        )
        extra_field = client.post(
            "/api/upload_zones_cities",
            headers={"Authorization": f"Bearer {access_token}"},
            files={"file": ("locations.csv", b"zone,city\nNorth,One", "text/csv")},
            data={"role": "admin"},
        )
        invalid_xlsx = client.post(
            "/api/upload_zones_cities",
            headers={"Authorization": f"Bearer {access_token}"},
            files={"file": ("locations.xlsx", b"not-a-workbook", "application/octet-stream")},
        )

    assert unauthenticated.status_code == 401
    assert legacy_xls.status_code == 415
    assert legacy_xls.json()["code"] == "geography_import_legacy_xls_unsupported"
    assert extra_field.status_code == 400
    assert extra_field.json()["code"] == "geography_import_invalid_request"
    assert invalid_xlsx.status_code == 400
    assert invalid_xlsx.json() == {
        "status": 400,
        "message": "The uploaded XLSX file is invalid",
        "code": "geography_import_invalid_file",
    }
    operation = create_app(settings).openapi()["paths"]["/api/upload_zones_cities"]["post"]
    assert set(operation["requestBody"]["content"]) == {"multipart/form-data"}
    schema = operation["requestBody"]["content"]["multipart/form-data"]["schema"]
    assert schema == {
        "type": "object",
        "additionalProperties": False,
        "required": ["file"],
        "properties": {"file": {"type": "string", "format": "binary"}},
    }
    assert "200" in operation["responses"]


def test_alumni_import_authenticates_before_parsing_and_excludes_legacy_secrets(
    auth_settings: Settings,
) -> None:
    """A reusable application key cannot import; the documented contract has no shared secret."""
    access_token = (
        TokenService(auth_settings)
        .issue({"id": 7, "email": "member@example.com", "user_role": "admin"})
        .access_token
    )
    bearer = {"Authorization": f"Bearer {access_token}"}
    with TestClient(create_app(auth_settings)) as client:
        unauthenticated = client.post(
            "/api/import_alumni",
            headers={"X-API-Key": "legacy-key-has-no-authority"},
            content=b"not-json-or-a-workbook",
        )
        legacy_xls = client.post(
            "/api/import_alumni",
            headers=bearer,
            data={"chapter_id": "1"},
            files={"file": ("roster.xls", b"legacy", "application/vnd.ms-excel")},
        )
        extra_field = client.post(
            "/api/import_alumni",
            headers=bearer,
            data={"chapter_id": "1", "default_password": "SharedPassword1!"},
            files={"file": ("roster.csv", b"email\n", "text/csv")},
        )
        invalid_json = client.post(
            "/api/import_alumni",
            headers={**bearer, "Content-Type": "application/json"},
            content=b"{broken",
        )
        unsupported = client.post(
            "/api/import_alumni",
            headers={**bearer, "Content-Type": "text/plain"},
            content=b"roster",
        )

    assert unauthenticated.status_code == 401
    assert legacy_xls.status_code == 415
    assert legacy_xls.json()["code"] == "alumni_import_legacy_xls_unsupported"
    assert extra_field.status_code == 400
    assert extra_field.json()["code"] == "alumni_import_invalid_request"
    assert invalid_json.status_code == 400
    assert invalid_json.json()["code"] == "alumni_import_invalid_request"
    assert unsupported.status_code == 415

    operation = create_app(auth_settings).openapi()["paths"]["/api/import_alumni"]["post"]
    content = operation["requestBody"]["content"]
    assert set(content) == {"application/json", "multipart/form-data"}
    serialized = str(operation).casefold()
    assert "default_password" not in serialized
    assert "access_code" not in serialized
    assert "useraccesscode" not in serialized
    assert content["multipart/form-data"]["schema"] == {
        "type": "object",
        "additionalProperties": False,
        "required": ["chapter_id", "file"],
        "properties": {
            "chapter_id": {"type": "integer", "minimum": 1},
            "file": {"type": "string", "format": "binary"},
        },
    }
