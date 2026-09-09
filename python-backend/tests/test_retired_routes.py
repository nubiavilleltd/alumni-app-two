"""Security retirement contract tests for intentionally unmigrated routes."""

import pytest
from fastapi.testclient import TestClient

from app.api.retired import RETIRED_ENDPOINTS
from app.core.config import Settings
from app.main import create_app


@pytest.mark.parametrize("method", ["GET", "POST"])
@pytest.mark.parametrize("path", sorted(RETIRED_ENDPOINTS))
def test_retired_route_is_a_bounded_tombstone(path: str, method: str) -> None:
    """Retired routes execute no old side effect and provide migration guidance."""
    with TestClient(create_app(Settings(environment="test"))) as client:
        response = client.request(method, path)

    assert response.status_code == 410
    assert response.json() == {
        "status": 410,
        "message": RETIRED_ENDPOINTS[path],
        "code": "endpoint_replaced",
    }


def test_retired_routes_are_deprecated_and_have_unique_operation_ids() -> None:
    """Generated clients see every retirement without duplicate operation IDs."""
    schema = create_app(Settings(environment="test")).openapi()
    operation_ids: list[str] = []
    for path in RETIRED_ENDPOINTS:
        for method in ("get", "post"):
            operation = schema["paths"][path][method]
            assert operation["deprecated"] is True
            operation_ids.append(operation["operationId"])
    assert len(operation_ids) == len(set(operation_ids))
