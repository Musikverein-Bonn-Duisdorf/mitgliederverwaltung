#!/usr/bin/env python3
"""Generate CHANGELOG.md from git commits with subject 'release VERSION'."""
from __future__ import annotations

import argparse
import re
import subprocess
import sys
from collections import Counter
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

MAX_FALLBACK_SUBJECTS = 12
MAX_PATH_NOTES = 3

NOISE_SUBJECT = re.compile(
    r"^(release\s+\S+|Sync release(\s+\S+)?|Merge (branch|pull request) |Co-authored-by:)",
    re.I,
)
NOISE_LINE = re.compile(
    r"^(Sync release(\s+\S+)?|release\s+\S+|Merge |Co-authored-by:)",
    re.I,
)
TICKET_SUBJECT = re.compile(r"^(ARCHIV|MIT|MELD)-\d+", re.I)

# Top-level / known areas → short German labels for path fallback
PATH_LABELS = (
    (re.compile(r"^help\.php$|^views/help|^CHANGELOG", re.I), "Hilfe / Changelog"),
    (re.compile(r"^common/nav|^styles/", re.I), "Navigation / Styles"),
    (re.compile(r"^config|^libs/colorschemes|^config-menu|^savePara", re.I), "Konfiguration / Farben"),
    (re.compile(r"^libs/SchemaManager|^updater|^dbintegrity|^config/schema|^install", re.I), "Updater / Schema"),
    (re.compile(r"^libs/sso|^login|^libs/IdentityUser|^libs/session", re.I), "SSO / Identity"),
    (re.compile(r"^members|^member\.php|^libs/Membership", re.I), "Mitglieder"),
    (re.compile(r"^sepa|^libs/Sepa", re.I), "SEPA"),
    (re.compile(r"^documents|^libs/Document", re.I), "Dokumente"),
    (re.compile(r"^backup|^libs/backup", re.I), "Backup"),
    (re.compile(r"^libs/log|^log\.php|^getLog|^getList|^libs/listChunk", re.I), "Log"),
    (re.compile(r"^libs/uiShell|^common/(header|footer|include)", re.I), "UI-Shell"),
    (re.compile(r"^makeVersion|^scripts/", re.I), "Build / Scripts"),
    (re.compile(r"^docs/", re.I), "Dokumentation"),
)


def run(args: list[str]) -> str:
    return subprocess.check_output(args, cwd=ROOT, text=True).strip()


def release_commits() -> list[tuple[str, str, str]]:
    out = run(["git", "log", "--grep=^release ", "--pretty=format:%H\t%s\t%ci"])
    rows = []
    for line in out.splitlines():
        if not line.strip():
            continue
        h, s, d = line.split("\t", 2)
        m = re.match(r"release\s+(\S+)", s)
        if not m:
            continue
        rows.append((h, m.group(1), d[:10]))
    return rows


def is_noise_line(ln: str) -> bool:
    if not ln or len(ln) < 8:
        return True
    if NOISE_LINE.match(ln):
        return True
    if re.fullmatch(r"[0-9a-f]{7,40}", ln):
        return True
    return False


def is_noise_subject(subj: str) -> bool:
    if not subj or len(subj) < 8:
        return True
    if NOISE_SUBJECT.match(subj):
        return True
    if re.fullmatch(r"[0-9a-f]{7,40}", subj):
        return True
    return False


def add_unique(bucket: list[str], seen: set[str], ln: str) -> None:
    if ln in seen or is_noise_line(ln):
        return
    if not re.search(r"ARCHIV-\d+", ln, re.I) and len(ln) < 12:
        return
    seen.add(ln)
    bucket.append(ln)


def path_area_label(path: str) -> str | None:
    base = path.strip().lstrip("./")
    for rx, label in PATH_LABELS:
        if rx.search(base):
            return label
    top = base.split("/", 1)[0]
    if top and top not in {".", "HASH", "VERSION", "common"}:
        if top.endswith(".php"):
            return top
        return top
    return None


def notes_from_paths(newer: str, older: str | None) -> list[str]:
    rng = f"{older}..{newer}" if older else newer
    try:
        out = run(["git", "diff", "--name-only", rng])
    except subprocess.CalledProcessError:
        return []
    labels: Counter[str] = Counter()
    for path in out.splitlines():
        path = path.strip()
        if not path:
            continue
        if path in {"HASH", "VERSION", "common/version.php", "CHANGELOG.md"}:
            continue
        label = path_area_label(path)
        if label:
            labels[label] += 1
    if not labels:
        return []
    return [f"Änderungen in {name}" for name, _ in labels.most_common(MAX_PATH_NOTES)]


def collect_notes(newer: str, older: str | None) -> list[str]:
    rng = f"{older}..{newer}" if older else newer
    try:
        out = run(["git", "log", "--pretty=format:%s%n%b%n==END==", rng])
    except subprocess.CalledProcessError:
        return []

    ticket_subjects: list[str] = []
    ticket_merge: list[str] = []
    other_subjects: list[str] = []
    seen_ticket: set[str] = set()
    seen_merge: set[str] = set()
    seen_other: set[str] = set()

    for block in out.split("==END=="):
        block = block.strip()
        if not block:
            continue
        lines = [ln.strip() for ln in block.splitlines() if ln.strip()]
        if not lines:
            continue
        subj = lines[0]
        body = lines[1:]

        if re.match(r"^release\s+\S+", subj, re.I):
            continue

        if re.match(r"^Merge (branch|pull request) ", subj, re.I):
            for ln in body:
                if re.search(r"ARCHIV-\d+", ln, re.I):
                    add_unique(ticket_merge, seen_merge, ln)
            continue

        if TICKET_SUBJECT.match(subj):
            add_unique(ticket_subjects, seen_ticket, subj)
            if re.fullmatch(r"ARCHIV-\d+\s*:?\s*", subj, re.I) and body:
                for ln in body:
                    if not is_noise_line(ln) and not ln.startswith("Co-authored-by:"):
                        add_unique(ticket_subjects, seen_ticket, f"{subj.rstrip(': ')}: {ln}")
                        break
            continue

        if not is_noise_subject(subj):
            add_unique(other_subjects, seen_other, subj)

    notes: list[str] = list(ticket_subjects)
    seen = set(seen_ticket)
    for ln in ticket_merge:
        add_unique(notes, seen, ln)

    if notes:
        return notes

    if other_subjects:
        return other_subjects[:MAX_FALLBACK_SUBJECTS]

    return notes_from_paths(newer, older)


def write_changelog(pending_version: str | None = None) -> Path:
    out_path = ROOT / "CHANGELOG.md"
    parts = [
        "# Changelog",
        "",
        "Automatisch aus Git-Release-Commits erzeugt.",
        "",
    ]
    if pending_version:
        last = release_commits()
        older = last[0][0] if last else None
        notes = collect_notes("HEAD", older)
        parts.append(f"## {pending_version} ({date.today().isoformat()})")
        parts.append("")
        if notes:
            parts.extend(f"- {n}" for n in notes)
        else:
            parts.append(f"- Release {pending_version}")
        parts.append("")

    releases = release_commits()
    for i, (h, version, day) in enumerate(releases):
        older = releases[i + 1][0] if i + 1 < len(releases) else None
        notes = collect_notes(h, older)
        parts.append(f"## {version} ({day})")
        parts.append("")
        if notes:
            parts.extend(f"- {n}" for n in notes)
        else:
            parts.append("- (keine weiteren Notizen)")
        parts.append("")

    out_path.write_text("\n".join(parts), encoding="utf-8")
    return out_path


def main() -> int:
    p = argparse.ArgumentParser()
    p.add_argument("--pending", metavar="VERSION", help="Prepend pending release (for makeVersion)")
    args = p.parse_args()
    path = write_changelog(args.pending)
    print(f"CHANGELOG written: {path}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
