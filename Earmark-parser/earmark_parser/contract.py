from __future__ import annotations

from dataclasses import dataclass, field


@dataclass(frozen=True)
class ParsedTransaction:
    date: str
    payee: str
    amount_cents: int
    raw_text: str

    def to_dict(self) -> dict[str, str | int]:
        return {
            "date": self.date,
            "payee": self.payee,
            "amount_cents": self.amount_cents,
            "raw_text": self.raw_text,
        }


@dataclass(frozen=True)
class ParseResponse:
    transactions: list[ParsedTransaction] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)

    def to_dict(self) -> dict[str, str | list[dict[str, str | int]] | list[str]]:
        return {
            "status": "ok",
            "transactions": [transaction.to_dict() for transaction in self.transactions],
            "warnings": self.warnings,
        }
