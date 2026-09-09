"""Tests for the non-secret PHP endpoint inventory."""

import json
from pathlib import Path

from scripts.inventory_php_endpoints import build_inventory, extract_methods, main


def test_extract_methods_handles_php_visibility_and_signals(tmp_path: Path) -> None:
    """Implicit-public methods are routes while underscored and protected methods are not."""
    source = tmp_path / "Example.php"
    source.write_text(
        """<?php
class Example extends CI_Controller {
    function index() {
        $email = $this->input->post('email');
        $this->db->insert('users', array('email' => $email));
        $this->ion_auth->logged_in();
        $this->response(array(), 201);
    }
    public function show() {
        $id = $this->input->get('id');
        $this->user_model->find($id);
    }
    function _helper() {}
    protected function hidden() {}
    private function secret() {}
}
""",
        encoding="utf-8",
    )
    records = extract_methods(source, display_path="Example.php")
    assert [record.method for record in records] == ["index", "show", "_helper"]
    assert records[0].declared_visibility == "implicit-public"
    assert records[0].inferred_request_mode == "POST"
    assert records[0].input_fields == ("email",)
    assert records[0].database_tables == ("users",)
    assert records[0].auth_signals == ("ion_auth",)
    assert records[0].response_statuses == (201,)
    assert records[1].model_calls == ("user_model.find",)
    assert records[2].codeigniter_routable is False
    assert records[2].disposition == "internal-only"


def test_workspace_inventory_matches_verified_method_counts() -> None:
    """The deterministic extractor must retain the manually reconciled source counts."""
    workspace_root = Path(__file__).resolve().parents[2]
    php_root = workspace_root / "Backend" / "alumniappV2 - PHP"
    inventory = build_inventory(php_root)
    assert inventory["controller_files"] == 17
    assert inventory["controller_callable_methods"] == 445
    assert inventory["controller_routable_candidates"] == 439
    assert inventory["root_artifact_callable_methods"] == 103
    assert inventory["root_artifact_route_status"] == (
        "outside-controller-directory-and-directly-guarded"
    )
    log_references = inventory["observed_controller_log_references"]
    assert isinstance(log_references, dict)
    assert log_references["Api.php"] > 0
    records = inventory["records"]
    assert isinstance(records, list)
    assert len(records) == 548
    assert records[-1]["disposition"] == "retire-noncontroller-artifact"
    primary_replacements = {
        record["method"]: record["disposition"]
        for record in records
        if record["source"].endswith("application/controllers/Api.php")
        and record["method"] in {"verify_otp", "resend_otp"}
    }
    assert primary_replacements == {
        "verify_otp": "replaced-by-consolidated-route",
        "resend_otp": "replaced-by-consolidated-route",
    }


def test_cli_writes_parseable_inventory(tmp_path: Path) -> None:
    """The command writes stable JSON to an explicit destination."""
    workspace_root = Path(__file__).resolve().parents[2]
    output = tmp_path / "inventory.json"
    result = main(
        [
            "--php-root",
            str(workspace_root / "Backend" / "alumniappV2 - PHP"),
            "--output",
            str(output),
        ]
    )
    assert result == 0
    parsed = json.loads(output.read_text(encoding="utf-8"))
    assert parsed["controller_callable_methods"] == 445
    assert parsed["records"][0]["body_sha256"]
