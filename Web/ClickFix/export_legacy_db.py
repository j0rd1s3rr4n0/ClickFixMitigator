#!/usr/bin/env python3
import argparse
import sqlite3
from pathlib import Path
from typing import Iterable


def quote_ident(name: str) -> str:
    escaped = name.replace('"', '""')
    return f'"{escaped}"'


def fetch_schema(conn: sqlite3.Connection) -> list[tuple[str, str, str]]:
    rows = conn.execute(
        "SELECT type, name, sql FROM sqlite_master "
        "WHERE name NOT LIKE 'sqlite_%' AND sql IS NOT NULL "
        "ORDER BY CASE type "
        "WHEN 'table' THEN 0 "
        "WHEN 'index' THEN 1 "
        "WHEN 'trigger' THEN 2 "
        "WHEN 'view' THEN 3 "
        "ELSE 4 END, name"
    ).fetchall()
    return [(row[0], row[1], row[2]) for row in rows]


def create_schema(dst: sqlite3.Connection, schema: Iterable[tuple[str, str, str]]) -> None:
    tables = [row for row in schema if row[0] == "table"]
    others = [row for row in schema if row[0] != "table"]
    for _, _, sql in tables:
        dst.execute(sql)
    for _, _, sql in others:
        dst.execute(sql)
    dst.commit()


def list_tables(conn: sqlite3.Connection) -> list[str]:
    rows = conn.execute(
        "SELECT name FROM sqlite_master "
        "WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
    ).fetchall()
    return [row[0] for row in rows]


def table_columns(conn: sqlite3.Connection, table: str) -> tuple[list[str], list[str]]:
    info = conn.execute(f"PRAGMA table_info({quote_ident(table)})").fetchall()
    all_cols = [row[1] for row in info]
    pk_cols = [row[1] for row in info if row[5] > 0]
    return all_cols, pk_cols


def copy_table(
    src: sqlite3.Connection,
    dst: sqlite3.Connection,
    table: str,
    batch_size: int,
) -> int:
    all_cols, pk_cols = table_columns(src, table)
    insert_cols = [col for col in all_cols if col not in pk_cols]
    if not insert_cols:
        return 0
    col_list = ", ".join(quote_ident(col) for col in insert_cols)
    placeholders = ", ".join("?" for _ in insert_cols)
    select_sql = f"SELECT {col_list} FROM {quote_ident(table)}"
    insert_sql = f"INSERT INTO {quote_ident(table)} ({col_list}) VALUES ({placeholders})"
    cur = src.execute(select_sql)
    total = 0
    while True:
        rows = cur.fetchmany(batch_size)
        if not rows:
            break
        dst.executemany(insert_sql, rows)
        total += len(rows)
    return total


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Export legacy ClickFix SQLite into a new SQLite without copying IDs."
    )
    parser.add_argument(
        "--source",
        default=str(Path(__file__).parent / "data" / "clickfix.sqlite"),
        help="Path to legacy SQLite database",
    )
    parser.add_argument(
        "--output",
        default=str(Path(__file__).parent / "data" / "clickfix_export.sqlite"),
        help="Path to export SQLite database",
    )
    parser.add_argument(
        "--tables",
        nargs="*",
        help="Optional list of tables to export. Defaults to all tables.",
    )
    parser.add_argument(
        "--batch",
        type=int,
        default=500,
        help="Batch size for inserts",
    )
    args = parser.parse_args()

    source_path = Path(args.source)
    output_path = Path(args.output)

    if not source_path.exists():
        print(f"[ERROR] Source DB not found: {source_path}")
        return 1

    if output_path.exists():
        output_path.unlink()

    src = sqlite3.connect(str(source_path))
    dst = sqlite3.connect(str(output_path))
    try:
        src.execute("PRAGMA foreign_keys=OFF")
        dst.execute("PRAGMA foreign_keys=OFF")

        schema = fetch_schema(src)
        create_schema(dst, schema)

        tables = list_tables(src)
        if args.tables:
            requested = set(args.tables)
            tables = [table for table in tables if table in requested]

        for table in tables:
            inserted = copy_table(src, dst, table, args.batch)
            print(f"[OK] {table}: {inserted} rows")

        dst.commit()
        print(f"[DONE] Exported to {output_path}")
    finally:
        src.close()
        dst.close()

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
