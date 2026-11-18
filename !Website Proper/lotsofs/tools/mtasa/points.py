"""Write the website's points.csv: where each classics map starts and runs.

Usage: python -I points.py <classics dir> <out csv>
       (the site's: ../../app/modules/mtasa/import/points.csv)
Cells are JSON arrays, coordinates rounded to 0.1 m, x and y only:
  spawnpoints [[x, y]]: one point, the middle of the spawn grid, or [] for none;
              [[x, y, r]] on a map with no checkpoints, r the furthest
              spawnpoint from the middle, the radius of the arena the site draws
  checkpoints [[x, y], ...] in race order (first chain), then any other chains
Everything else (each spawnpoint, the colours, the pickups, the heights) the
site reads from the map's own files on GitHub, on Increase detail (the
owner's choice, 2026-11-18), so it is not written here.
"""
import os, re, sys, csv, json
ARGS = sys.argv[1:3]
sys.argv = sys.argv[:1] + ['', '0', '-1']  # overlay.py reads these at import
exec(open(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'overlay.py'),
          encoding='utf-8').read().split('titles = {}')[0])
CLASSICS, OUT = ARGS

def r2(v):
    v = round(v, 1)
    return int(v) if v == int(v) else v
rows, notes = [], []
folders = sorted((d for d in os.listdir(CLASSICS) if re.fullmatch(r'z\(\d+\)', d)),
                 key=lambda d: int(d[2:-1]))
for d in folders:
    n = int(d[2:-1])
    mp = os.path.join(CLASSICS, d, '%d.map' % n)
    sp = cp = []
    try:
        sps, cps, pks = parse_map(mp)
        ok = lambda e: e['x'] is not None and e['y'] is not None
        chains, hang = simulate_chains(cps)
        if hang:
            notes.append('%s: chain loops onto itself' % d)
        ordered, seen = [], set()
        for ch in chains:
            for c in ch:
                if id(c) not in seen:
                    seen.add(id(c)); ordered.append(c)
        ordered += [c for c in cps if id(c) not in seen]
        if len(chains) > 1:
            notes.append('%s: %d chains' % (d, len(chains)))
        placed = [s for s in sps if ok(s)]
        cp = [[r2(c['x']), r2(c['y'])] for c in ordered if ok(c)]
        sp = []
        if placed:
            mx = sum(s['x'] for s in placed) / len(placed)
            my = sum(s['y'] for s in placed) / len(placed)
            sp = [[r2(mx), r2(my)]]
            # A map with no checkpoints (a derby or freeroam) also gets its
            # arena's radius: the furthest spawnpoint from the middle.
            if not cp:
                sp[0].append(r2(max(((s['x'] - mx) ** 2 + (s['y'] - my) ** 2) ** 0.5 for s in placed)))
    except FileNotFoundError:
        notes.append('%s: no map file' % d)
    except Exception as e:
        notes.append('%s: %s' % (d, e))
    js = lambda v: json.dumps(v, separators=(',', ':'), ensure_ascii=False)
    rows.append([d, js(sp), js(cp)])

with open(OUT, 'w', encoding='utf-8', newline='') as fh:
    w = csv.writer(fh)
    w.writerow(['folder', 'spawnpoints', 'checkpoints'])
    w.writerows(rows)
print('rows', len(rows))
print(*notes, sep='\n')
