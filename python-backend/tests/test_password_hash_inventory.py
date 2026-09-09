"""Value-free legacy password inventory tests."""

import pytest

from scripts.inventory_password_hashes import inventory_password_hashes


def test_inventory_classifies_hashes_without_returning_values() -> None:
    """Only aggregate formats and bcrypt costs leave the parser."""
    sql = """INSERT INTO `users` (`id`, `password`, `note`) VALUES
    (1, '$2y$08$aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'a,b'),
    (2, '0123456789abcdef0123456789abcdef01234567', 'semi;colon'),
    (3, '$argon2id$v=19$m=65536,t=3,p=4$synthetic$synthetic', 'escaped\\' quote');
    """
    report = inventory_password_hashes(sql)
    assert report == {
        "users_with_exported_hashes": 3,
        "categories": {"40-character-hex-legacy": 1, "argon2": 1, "bcrypt": 1},
        "bcrypt_cost_factors": {"08": 1},
        "contains_hash_values": False,
    }
    assert "aaaaaaaa" not in str(report)


def test_inventory_rejects_missing_or_malformed_user_data() -> None:
    """Unexpected dump shapes fail rather than producing misleading counts."""
    with pytest.raises(ValueError, match="No INSERT"):
        inventory_password_hashes("SELECT 1;")
    with pytest.raises(ValueError, match="no password"):
        inventory_password_hashes("INSERT INTO `users` (`id`) VALUES (1);")
    with pytest.raises(ValueError, match="Unterminated"):
        inventory_password_hashes("INSERT INTO `users` (`password`) VALUES ('unterminated);")
