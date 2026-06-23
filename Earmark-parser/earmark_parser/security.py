from __future__ import annotations

from hmac import compare_digest


def is_valid_parser_secret(provided: str | None, expected: str) -> bool:
    if not provided:
        return False

    return compare_digest(provided, expected)
