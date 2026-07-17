"""
Create a clean copy of AlbaSambosa for academic submission.
Skips: .git, node_modules, vendor, and all gitignored/AI-related paths.
"""
import os
import shutil
import fnmatch
from pathlib import Path

SRC = Path(r"C:\laragon\www\albasambosa")
DST = Path(r"C:\laragon\www\albasambosa-clean")

# Patterns to skip (dirs/files)
SKIP_DIRS = {
    '.git', 'node_modules', 'vendor', '.claude', '.agents',
    '.playwright-mcp', '.superpowers', '.impeccable', '.codex',
    'app/graphify-out', 'public/build', 'public/storage',
    'storage/logs', 'storage/framework/cache',
    'storage/framework/sessions', 'storage/framework/views',
    '.github/hooks', '.github/skills', '__pycache__',
    '.phpunit.cache',
}

SKIP_FILES = {
    '.mcp.json', 'CLAUDE.md', 'TASK.md', 'TAHAPAN-DEVELOPMENT.md',
    'skills-lock.json', '.env', '.env.backup', '.phpunit.result.cache',
    '.gitattributes',
}

def should_skip_dir(rel_path: str) -> bool:
    parts = Path(rel_path).parts
    # Check each prefix
    for i in range(1, len(parts) + 1):
        prefix = str(Path(*parts[:i])).replace('\\', '/')
        if prefix in SKIP_DIRS:
            return True
    return False

def should_skip_file(rel_path: str) -> bool:
    name = Path(rel_path).name
    if name in SKIP_FILES:
        return True
    if name.endswith('.log'):
        return True
    return False

def main():
    if DST.exists():
        print(f"Removing existing {DST}...")
        shutil.rmtree(DST)

    DST.mkdir(parents=True)

    copied = 0
    skipped_dirs = 0
    skipped_files = 0

    for root, dirs, files in os.walk(SRC):
        # Filter dirs in-place
        dirs[:] = [d for d in dirs if d not in {'.git', 'node_modules', 'vendor', '__pycache__'}]

        rel_root = os.path.relpath(root, SRC)
        if rel_root == '.':
            rel_root = ''

        # Skip entire directory trees
        if rel_root and should_skip_dir(rel_root):
            dirs[:] = []  # Don't recurse
            skipped_dirs += 1
            continue

        # Calculate dest dir
        if rel_root:
            dest_dir = DST / rel_root
        else:
            dest_dir = DST
        os.makedirs(dest_dir, exist_ok=True)

        for fname in files:
            rel_path = os.path.join(rel_root, fname).replace('\\', '/') if rel_root else fname
            rel_path = rel_path.lstrip('./')

            if should_skip_file(rel_path):
                skipped_files += 1
                continue

            src_file = Path(root) / fname
            dst_file = dest_dir / fname
            shutil.copy2(src_file, dst_file)
            copied += 1

    print(f"Done! Copied: {copied}, Skipped dirs: {skipped_dirs}, Skipped files: {skipped_files}")
    print(f"Output: {DST}")

if __name__ == '__main__':
    main()
