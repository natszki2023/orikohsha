import re
from pathlib import Path

root = Path(r'c:\Users\bigbe\Documents\Github\orikohsha')
files = list(root.glob('*.html')) + list((root / 'journal').glob('*.html'))

for f in files:
    text = f.read_text(encoding='utf-8', errors='ignore')

    for href in re.findall(r'href=["\']([^"\']+)["\']', text):
        if href.startswith(('http://', 'https://', 'mailto:', 'tel:', '#', 'javascript:')):
            continue
        target = href.split('#', 1)[0]
        if target and not (f.parent / target).exists():
            print(f'BROKEN-HREF {f.relative_to(root)} -> {href}')

    for src in re.findall(r'src=["\']([^"\']+)["\']', text):
        if src.startswith(('http://', 'https://', 'data:', 'mailto:', 'tel:', '#', 'javascript:')):
            continue
        if not (f.parent / src).exists():
            print(f'BROKEN-SRC {f.relative_to(root)} -> {src}')

    for href in re.findall(r'href=["\']([^"\']+)["\']', text):
        if '#' in href and not href.startswith(('http://', 'https://', 'mailto:', 'tel:', '#', 'javascript:')):
            path, anchor = href.split('#', 1)
            if path and not (f.parent / path).exists():
                continue
            target_file = (f.parent / path).resolve() if path else f.resolve()
            if target_file.exists():
                target_text = target_file.read_text(encoding='utf-8', errors='ignore')
                if anchor not in re.findall(r'\bid=["\']([^"\']+)', target_text):
                    print(f'BROKEN-ANCHOR {f.relative_to(root)} -> {href}')
