#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
generate_migrations_from_dbml.py

DBML から Laravel migration の Schema::create(...) 部分を自動生成し、
既存の migration ファイルを上書きします。

想定配置:
  lifeforce-system/
  ├ database/
  │  ├ schema/
  │  │  └ lifeforce_schema.dbml
  │  └ migrations/
  └ scripts/
     └ generate_migrations_from_dbml.py

実行:
  python scripts/generate_migrations_from_dbml.py

注意:
  - 実行前に database/migrations をバックアップしてください。
  - DBMLの Records は無視します。
  - Ref は外部キーとして反映します。
  - deleted_at カラムがDBMLにある場合は $table->softDeletes(); に変換します。
"""

from __future__ import annotations

import re
from dataclasses import dataclass, field
from pathlib import Path
from typing import Dict, List, Optional


PROJECT_ROOT = Path(__file__).resolve().parents[1]
DBML_PATH = PROJECT_ROOT / "database" / "schema" / "lifeforce_schema.dbml"
MIGRATIONS_DIR = PROJECT_ROOT / "database" / "migrations"


@dataclass
class Column:
    name: str
    dbml_type: str
    attrs: str = ""


@dataclass
class Table:
    name: str
    columns: List[Column] = field(default_factory=list)


@dataclass
class Ref:
    from_table: str
    from_column: str
    to_table: str
    to_column: str


def strip_comments(line: str) -> str:
    return line.split("//", 1)[0].strip()


def parse_dbml(dbml: str) -> tuple[Dict[str, Table], List[Ref]]:
    tables: Dict[str, Table] = {}
    refs: List[Ref] = []

    # Table blocks
    table_pattern = re.compile(r"Table\s+([A-Za-z0-9_]+)\s*\{(.*?)\}", re.S)
    for match in table_pattern.finditer(dbml):
        table_name = match.group(1).strip()
        body = match.group(2)
        table = Table(name=table_name)

        for raw_line in body.splitlines():
            line = strip_comments(raw_line)
            if not line:
                continue
            if line.startswith("Indexes"):
                continue
            if line.startswith("[") or line.startswith("}"):
                continue

            # Example:
            # email varchar [unique]
            # id bigint [pk]
            parts = line.split()
            if len(parts) < 2:
                continue

            col_name = parts[0].strip()
            col_type = parts[1].strip()
            attrs = " ".join(parts[2:]).strip()

            # Skip obviously invalid rows
            if col_name.lower() in {"note:", "project"}:
                continue

            table.columns.append(Column(col_name, col_type, attrs))

        tables[table_name] = table

    # Ref lines:
    # Ref: students.user_id > users.id
    # Ref: "users"."id" < "parents"."user_id"
    ref_line_pattern = re.compile(r"Ref:\s*(.+)")
    for raw_line in dbml.splitlines():
        line = strip_comments(raw_line)
        m = ref_line_pattern.match(line)
        if not m:
            continue

        expr = m.group(1).strip().replace('"', "")

        if ">" in expr:
            left, right = [x.strip() for x in expr.split(">", 1)]
            from_side, to_side = left, right
        elif "<" in expr:
            left, right = [x.strip() for x in expr.split("<", 1)]
            # A.id < B.a_id means B.a_id references A.id
            to_side, from_side = left, right
        else:
            continue

        if "." not in from_side or "." not in to_side:
            continue

        from_table, from_column = from_side.split(".", 1)
        to_table, to_column = to_side.split(".", 1)

        refs.append(
            Ref(
                from_table=from_table.strip(),
                from_column=from_column.strip(),
                to_table=to_table.strip(),
                to_column=to_column.strip(),
            )
        )

    return tables, refs


def is_nullable_column(col: Column) -> bool:
    # DBMLに明示nullable構文がないため、運用上 null が入りやすい名前は nullable にする
    nullable_names = {
        "email",
        "email_verified_at",
        "description",
        "note",
        "address",
        "address1",
        "address2",
        "postal_code",
        "phone_number",
        "closed_at",
        "ended_at",
        "withdrawn_at",
        "resignation_date",
        "target_date",
        "end_date",
        "cancelled_at",
        "completed_at",
        "approved_at",
        "rejected_at",
        "delivered_at",
        "paid_at",
        "failed_at",
        "refunded_at",
        "processed_at",
        "read_at",
        "sent_at",
        "logout_at",
        "failure_reason",
        "error_message",
        "online_url",
        "stripe_payment_intent_id",
        "stripe_refund_id",
        "handled_by",
        "approved_by",
        "checked_in_by",
        "recorded_by",
        "confirmed_by",
        "assigned_user_id",
        "classroom_id",
        "student_id",
        "area_id",
        "school_id",
        "badge_id",
        "title_id",
        "qualification_id",
        "calendar_event_id",
        "original_reservation_id",
        "learning_plan_milestone_id",
        "learning_plan_task_id",
        "related_id",
        "rank",
        "score",
        "win_count",
        "loss_count",
        "points",
        "open_time",
        "close_time",
        "picked_up_at",
        "handled_by",
        "updated_by",
    }

    if col.name in nullable_names:
        return True
    if col.name.endswith("_at") and col.name not in {"created_at", "updated_at"}:
        return True
    if col.name.startswith("end_") or col.name.startswith("closed_"):
        return True
    return False


def is_unique(col: Column) -> bool:
    return "unique" in col.attrs


def is_primary(col: Column) -> bool:
    attrs = col.attrs.lower()
    return "[pk]" in attrs or "[primary key]" in attrs or "primary key" in attrs


def laravel_column_line(col: Column, ref: Optional[Ref]) -> Optional[str]:
    name = col.name
    typ = col.dbml_type.lower()

    if name == "id" and is_primary(col):
        return "$table->id();"

    if name in {"created_at", "updated_at"}:
        return None

    if name == "deleted_at":
        return None

    nullable = is_nullable_column(col)
    unique = is_unique(col)

    # Foreign key columns
    if ref is not None:
        line = f"$table->foreignId('{name}')"
        if nullable:
            line += "->nullable()"
        line += f"->constrained('{ref.to_table}')"
        if nullable:
            line += "->nullOnDelete()"
        else:
            line += "->restrictOnDelete()"
        line += ";"
        return line

    # Normal columns
    if typ in {"varchar", "string"}:
        line = f"$table->string('{name}')"
    elif typ == "text":
        line = f"$table->text('{name}')"
    elif typ == "integer":
        line = f"$table->integer('{name}')"
    elif typ == "bigint":
        line = f"$table->unsignedBigInteger('{name}')"
    elif typ == "boolean":
        line = f"$table->boolean('{name}')"
    elif typ == "date":
        line = f"$table->date('{name}')"
    elif typ == "time":
        line = f"$table->time('{name}')"
    elif typ == "timestamp":
        line = f"$table->timestamp('{name}')"
    elif typ == "decimal":
        line = f"$table->decimal('{name}', 10, 2)"
    else:
        # Unknown type fallback
        line = f"$table->string('{name}')"

    if nullable:
        line += "->nullable()"

    if unique:
        line += "->unique()"

    # Common defaults
    if typ == "boolean" and not nullable:
        line += "->default(false)"
    if name == "is_active":
        line = f"$table->boolean('{name}')->default(true)"
    if name in {"sort_order", "display_order"}:
        line = f"$table->integer('{name}')->default(0)"

    line += ";"
    return line


def build_migration_php(table: Table, refs: List[Ref]) -> str:
    table_refs = {
        ref.from_column: ref
        for ref in refs
        if ref.from_table == table.name
    }

    lines: List[str] = []
    has_timestamps = any(c.name in {"created_at", "updated_at"} for c in table.columns)
    has_soft_deletes = any(c.name == "deleted_at" for c in table.columns)

    for col in table.columns:
        ref = table_refs.get(col.name)
        line = laravel_column_line(col, ref)
        if line:
            lines.append("            " + line)

    if has_timestamps:
        lines.append("            $table->timestamps();")

    if has_soft_deletes:
        lines.append("            $table->softDeletes();")

    body = "\n".join(lines)

    return f"""<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{{
    /**
     * Run the migrations.
     */
    public function up(): void
    {{
        Schema::create('{table.name}', function (Blueprint $table) {{
{body}
        }});
    }}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {{
        Schema::dropIfExists('{table.name}');
    }}
}};
"""


def find_migration_file(table_name: str) -> Optional[Path]:
    candidates = sorted(MIGRATIONS_DIR.glob(f"*create_{table_name}_table.php"))
    if candidates:
        return candidates[-1]
    return None


def main() -> None:
    if not DBML_PATH.exists():
        raise FileNotFoundError(f"DBML file not found: {DBML_PATH}")

    if not MIGRATIONS_DIR.exists():
        raise FileNotFoundError(f"Migrations directory not found: {MIGRATIONS_DIR}")

    dbml = DBML_PATH.read_text(encoding="utf-8")
    tables, refs = parse_dbml(dbml)

    print(f"DBML: {DBML_PATH}")
    print(f"Tables found: {len(tables)}")
    print(f"Refs found: {len(refs)}")
    print()

    updated = 0
    missing: List[str] = []

    for table_name, table in tables.items():
        migration_file = find_migration_file(table_name)

        if migration_file is None:
            missing.append(table_name)
            continue

        php = build_migration_php(table, refs)
        migration_file.write_text(php, encoding="utf-8")
        updated += 1
        print(f"UPDATED: {migration_file.name}")

    print()
    print(f"Updated migrations: {updated}")

    if missing:
        print()
        print("Migration file not found for these tables:")
        for name in missing:
            print(f"  - {name}")
        print()
        print("Create missing migrations, for example:")
        for name in missing:
            print(f"  php artisan make:migration create_{name}_table")


if __name__ == "__main__":
    main()
