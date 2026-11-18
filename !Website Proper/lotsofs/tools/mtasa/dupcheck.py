"""Tolerant duplicate scoring for the classics maps.

For every map, finds earlier maps that share elements and scores them with
position tolerance (elements count as the same within TOL metres; objects must
also share a model, pickups a type). Writes dupcands.json: for each map, its
best earlier candidates with per-kind similarities.

Usage: python -I dupcheck.py <features.json> <out.json>
"""
import sys, json, re, collections

FEATS, OUT = sys.argv[1:3]
TOL = 3.0
CELL = 4.0
COARSE = 25.0
TOP = 25

data = json.load(open(FEATS))
TOKRX = re.compile(r'^([scop])([^:,#]*?:)?(-?\d+),(-?\d+),(-?\d+)#\d+$')

elems = {}
for f in data['maps']:
    if not f.get('tokens'):
        continue
    out = []
    for t in f['tokens']:
        m = TOKRX.match(t)
        if not m:
            continue
        k = m.group(1) + (m.group(2) or '')
        out.append((k, int(m.group(3)), int(m.group(4)), int(m.group(5))))
    if len(out) >= 3:
        elems[f['num']] = out

grids = {}
for n, es in elems.items():
    g = collections.defaultdict(list)
    for k, x, y, z in es:
        g[(k, int(x // CELL), int(y // CELL))].append(z_xy := (x, y, z))
    grids[n] = g

def covered(es, g):
    """How many of es have a same-kind element in grid g within TOL."""
    hit = collections.Counter()
    for k, x, y, z in es:
        cx, cy = int(x // CELL), int(y // CELL)
        found = False
        for i in (-1, 0, 1):
            if found:
                break
            for j in (-1, 0, 1):
                for (px, py, pz) in g.get((k, cx + i, cy + j), ()):
                    if abs(px - x) <= TOL and abs(py - y) <= TOL and abs(pz - z) <= TOL:
                        found = True
                        break
                if found:
                    break
        if found:
            hit[k[0]] += 1
    return hit

coarse = {}
for n, es in elems.items():
    coarse[n] = {(k, round(x / COARSE), round(y / COARSE), round(z / COARSE)) for k, x, y, z in es}
df = collections.Counter()
for s in coarse.values():
    df.update(s)
index = collections.defaultdict(list)
for n in sorted(coarse):
    for t in coarse[n]:
        if df[t] <= 600:
            index[t].append(n)

kinds_count = {n: collections.Counter(k[0] for k, *_ in es) for n, es in elems.items()}

res = {}
for done, n in enumerate(sorted(elems)):
    cand = collections.Counter()
    for t in coarse[n]:
        for m in index.get(t, ()):
            if m < n:
                cand[m] += 1
            else:
                break
    out = []
    for m, shared in cand.most_common(TOP):
        if shared < 0.25 * min(len(coarse[n]), len(coarse[m])):
            continue
        ha = covered(elems[n], grids[m])
        hb = covered(elems[m], grids[n])
        ca, cb = kinds_count[n], kinds_count[m]
        tot = (sum(ha.values()) + sum(hb.values())) / (len(elems[n]) + len(elems[m]))
        per = {}
        for k in 'scop':
            if ca[k] or cb[k]:
                per[k] = (ha[k] + hb[k]) / (ca[k] + cb[k])
        out.append(dict(m=m, sim=round(tot, 4), per={k: round(v, 4) for k, v in per.items()}))
    out.sort(key=lambda d: (-d['sim'], d['m']))
    res[n] = out[:5]
    if done % 1000 == 0:
        print(done, file=sys.stderr)

json.dump(res, open(OUT, 'w'))
