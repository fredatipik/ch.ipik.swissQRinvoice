#!/usr/bin/env python3
"""
Ajoute ['domain' => 'ch.ipik.swissQRinvoice'] aux appels ts() des fichiers PHP.

Usage :  python3 add_ts_domain.py           (depuis la racine de l'extension)
         python3 add_ts_domain.py --dry-run (n'écrit rien, montre ce qui changerait)

Ne touche pas aux templates .tpl : le {crmScope extensionKey='...'} y joue
déjà ce rôle.
"""
import re
import sys
from pathlib import Path

DOMAIN = "ch.ipik.swissQRinvoice"
DOMAIN_KV = "'domain' => '%s'" % DOMAIN
DRY = "--dry-run" in sys.argv


def find_call_end(src, open_paren):
    """Index de la parenthèse fermante correspondant à src[open_paren] == '('.
    Respecte les chaînes simples/doubles et leurs échappements."""
    depth = 0
    i = open_paren
    quote = None
    while i < len(src):
        c = src[i]
        if quote:
            if c == "\\":
                i += 2
                continue
            if c == quote:
                quote = None
        elif c in "'\"":
            quote = c
        elif c in "([{":
            depth += 1
        elif c in ")]}":
            depth -= 1
            if depth == 0:
                return i
        i += 1
    return -1


def split_top_level(args):
    """Découpe une liste d'arguments sur les virgules de premier niveau."""
    parts, depth, quote, cur = [], 0, None, ""
    i = 0
    while i < len(args):
        c = args[i]
        if quote:
            cur += c
            if c == "\\":
                cur += args[i + 1] if i + 1 < len(args) else ""
                i += 2
                continue
            if c == quote:
                quote = None
        elif c in "'\"":
            quote = c
            cur += c
        elif c in "([{":
            depth += 1
            cur += c
        elif c in ")]}":
            depth -= 1
            cur += c
        elif c == "," and depth == 0:
            parts.append(cur)
            cur = ""
        else:
            cur += c
        i += 1
    parts.append(cur)
    return parts


def process(src, path):
    """Retourne (nouveau_source, nb_modifs, liste_a_revoir)."""
    out, pos, changed, manual = [], 0, 0, []
    # ts( non précédé d'un caractère de mot, pour éviter fooTs(
    for m in re.finditer(r"(?<![\w$>])ts\s*\(", src):
        start = m.start()
        if start < pos:
            continue
        op = src.index("(", m.start())
        cl = find_call_end(src, op)
        if cl == -1:
            continue
        args = src[op + 1:cl]
        parts = split_top_level(args)

        if DOMAIN in args:
            continue  # déjà fait

        if len(parts) == 1:
            new_args = args + ", [" + DOMAIN_KV + "]"
        elif len(parts) == 2 and parts[1].strip().startswith("["):
            second = parts[1]
            br = second.index("[")
            inner = second[br + 1:second.rindex("]")]
            sep = "" if not inner.strip() else ", "
            new_second = second[:br + 1] + DOMAIN_KV + sep + inner + "]"
            new_args = parts[0] + "," + new_second
        else:
            manual.append((path, src[:start].count("\n") + 1, src[start:cl + 1][:70]))
            continue

        out.append(src[pos:op + 1])
        out.append(new_args)
        pos = cl
        changed += 1
    out.append(src[pos:])
    return "".join(out), changed, manual


def main():
    total, all_manual = 0, []
    for p in sorted(Path(".").rglob("*.php")):
        if "vendor" in p.parts:
            continue
        src = p.read_text(encoding="utf-8")
        new, n, manual = process(src, str(p))
        all_manual += manual
        if n:
            total += n
            print("  %-55s %d appel(s)" % (str(p), n))
            if not DRY:
                p.write_text(new, encoding="utf-8")

    print("\n%d appel(s) ts() %s." % (total, "à modifier" if DRY else "modifié(s)"))
    if all_manual:
        print("\nÀ reprendre à la main (2e argument non littéral) :")
        for path, line, snippet in all_manual:
            print("  %s:%d  %s" % (path, line, snippet.replace("\n", " ")))
    if not DRY:
        print("\nRelis le diff avant de committer :  git diff")


if __name__ == "__main__":
    main()
