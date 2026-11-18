"""Extract per-map features from the [classics] maps into features.json.

Usage: python -I extract.py <classics dir> <repo root> <gta dir> <out json>
"""
import os, re, sys, json, math, collections
import xml.etree.ElementTree as ET

CLASSICS, REPO, GTA, OUT = sys.argv[1:5]
HERE = os.path.dirname(os.path.abspath(__file__))


def load_xml(path):
    b = open(path, 'rb').read()
    if b[:2] in (b'\xff\xfe', b'\xfe\xff'):
        t = b.decode('utf-16')
    else:
        if b[:3] == b'\xef\xbb\xbf':
            b = b[3:]
        try:
            t = b.decode('utf-8')
        except UnicodeDecodeError:
            t = b.decode('latin-1')
    t = re.sub(r'^\s*<\?xml[^>]*\?>', '', t)
    return ET.fromstring(t)


def fnum(v):
    try:
        f = float(v)
        return f if math.isfinite(f) else None
    except (TypeError, ValueError):
        return None


def inum(v):
    if v is None:
        return None
    m = re.match(r'\s*(-?\d+)', v)
    return int(m.group(1)) if m else None


# ---------------------------------------------------------------- water.dat
water = []  # (kind, pts[(x,y,z)])
for line in open(os.path.join(GTA, 'data', 'water.dat')):
    p = line.split()
    if len(p) not in (22, 29):
        continue
    nv = 3 if len(p) == 22 else 4
    pts = [(float(p[i*7]), float(p[i*7+1]), float(p[i*7+2])) for i in range(nv)]
    water.append(pts)


def _in_tri(x, y, a, b, c):
    def s(p1, p2):
        return (x - p2[0]) * (p1[1] - p2[1]) - (p1[0] - p2[0]) * (y - p2[1])
    d1, d2, d3 = s(a, b), s(b, c), s(c, a)
    neg = d1 < 0 or d2 < 0 or d3 < 0
    pos = d1 > 0 or d2 > 0 or d3 > 0
    return not (neg and pos)


def water_level(x, y):
    """Water surface height at x,y, or None if no water there."""
    best = None
    for pts in water:
        if len(pts) == 4:
            xs = [q[0] for q in pts]; ys = [q[1] for q in pts]
            if min(xs) <= x <= max(xs) and min(ys) <= y <= max(ys):
                z = sum(q[2] for q in pts) / 4
            else:
                continue
        else:
            if not _in_tri(x, y, *pts):
                continue
            z = sum(q[2] for q in pts) / 3
        best = z if best is None else max(best, z)
    if best is None and (abs(x) > 2990 or abs(y) > 2990):
        best = 0.0  # open sea around the map
    return best


# ---------------------------------------------------------------- zones
zones = json.load(open(os.path.join(HERE, 'zones.json')))['info.zon']
COUNTIES = {'LA', 'RED', 'VE', 'SF', 'ROBAD', 'ROBAD1', 'BONE', 'WHET', 'FLINTC'}
county_z = [z for z in zones if z['code'] in COUNTIES]
sub_z = sorted([z for z in zones if z['code'] not in COUNTIES],
               key=lambda z: (z['box'][3]-z['box'][0])*(z['box'][4]-z['box'][1]))


# Zones are named by their game text label (one per zone name; the website
# names them from site_zones.py's file), with two of the site's own: OUTSIDE
# past the world's edge and SA where no county holds the point.
OUTSIDE, NOWHERE = 'OUTSIDE', 'SA'


def zone_of(x, y, z):
    if abs(x) > 3000 or abs(y) > 3000:
        return (OUTSIDE, OUTSIDE)
    city = next((c['label'] for c in county_z
                 if c['box'][0] <= x <= c['box'][3] and c['box'][1] <= y <= c['box'][4]), NOWHERE)
    sub = next((s['label'] for s in sub_z
                if s['box'][0] <= x <= s['box'][3] and s['box'][1] <= y <= s['box'][4]
                and s['box'][2] - 5 <= z <= s['box'][5] + 50), None)
    if sub is None:
        sub = next((s['label'] for s in sub_z
                    if s['box'][0] <= x <= s['box'][3] and s['box'][1] <= y <= s['box'][4]), city)
    return (sub, city)


# ---------------------------------------------------------------- race chain simulation
def simulate_chains(cps):
    """Mirror race_server.lua's checkpoint chain building. Returns (chains, hang)."""
    chains = []
    def find_last(nid):
        for i, c in enumerate(chains):
            if c[-1]['nextid'] is not None and c[-1]['nextid'] == nid:
                return i
        return None
    def find_first(cid):
        for i, c in enumerate(chains):
            if c[0]['id'] == cid:
                return i
        return None
    hang = False
    for cp in cps:
        ci = find_last(cp['id'])
        if ci is not None:
            chains[ci].append(cp)
            if cp['nextid'] is not None:
                ni = find_first(cp['nextid'])
                if ni is not None:
                    if ni == ci:
                        hang = True  # table.merge(t, t) never terminates
                        return chains, hang
                    chains[ci].extend(chains[ni])
                    del chains[ni]
        elif cp['nextid'] is not None:
            ci = find_first(cp['nextid'])
            if ci is not None:
                chains[ci].insert(0, cp)
                pi = find_last(cp['id'])
                if pi is not None and pi != ci:
                    chains[pi].extend(chains[ci])
                    del chains[ci]
            else:
                chains.append([cp])
        else:
            chains.append([cp])
    return chains, hang


# ---------------------------------------------------------------- fingerprints
def tokens(sps, cps, objs, pks):
    c = collections.Counter()
    for s in sps:
        c['s%d,%d,%d' % (round(s['x']), round(s['y']), round(s['z']))] += 1
    for p in cps:
        c['c%d,%d,%d' % (round(p['x']), round(p['y']), round(p['z']))] += 1
    for o in objs:
        c['o%d:%d,%d,%d' % (o['model'], round(o['x']), round(o['y']), round(o['z']))] += 1
    for p in pks:
        c['p%s:%d,%d,%d' % (p['type'][:1], round(p['x']), round(p['y']), round(p['z']))] += 1
    out = []
    for k, n in c.items():
        out += ['%s#%d' % (k, i) for i in range(n)]
    return out


def parse_map(path):
    m = load_xml(path)
    sps, cps, objs, pks, other = [], [], [], [], collections.Counter()
    for el in m:
        a = el.attrib
        x, y, z = fnum(a.get('posX')), fnum(a.get('posY')), fnum(a.get('posZ'))
        if el.tag == 'spawnpoint':
            sps.append(dict(x=x, y=y, z=z, rot=fnum(a.get('rotation')), veh=inum(a.get('vehicle')),
                            vraw=a.get('vehicle')))
        elif el.tag == 'checkpoint':
            cps.append(dict(x=x, y=y, z=z, id=a.get('id'), nextid=a.get('nextid') or None,
                            type=a.get('type'), veh=inum(a.get('vehicle')), size=fnum(a.get('size')),
                            color=a.get('color')))
        elif el.tag == 'object':
            objs.append(dict(x=x, y=y, z=z, model=inum(a.get('model')) or 0))
        elif el.tag == 'racepickup':
            pks.append(dict(x=x, y=y, z=z, type=a.get('type') or '', veh=inum(a.get('vehicle')),
                            vraw=a.get('vehicle'), respawn=a.get('respawn')))
        else:
            other[el.tag] += 1
    return sps, cps, objs, pks, other


def parse_meta(path):
    m = load_xml(path)
    info = m.find('info')
    ia = dict(info.attrib) if info is not None else {}
    settings = {s.get('name'): s.get('value') for s in m.iter('setting')}
    srcs = [e.get('src') for e in m.iter('map')]
    return ia, settings, srcs


# ---------------------------------------------------------------- classics
nums = []
for d in os.listdir(CLASSICS):
    mm = re.fullmatch(r'z\((\d+)\)', d)
    if mm and os.path.isdir(os.path.join(CLASSICS, d)):
        nums.append(int(mm.group(1)))
maxn = max(nums)
present = set(nums)

feats = []
for n in range(1, maxn + 1):
    f = dict(num=n, errors=[], warnings=[], notes=[])
    feats.append(f)
    folder = os.path.join(CLASSICS, 'z(%d)' % n)
    if n not in present:
        f['missing_folder'] = True
        continue
    files = sorted(os.listdir(folder))
    f['files'] = files
    mp = os.path.join(folder, 'meta.xml')
    if not os.path.exists(mp):
        f['errors'].append('meta.xml missing')
        continue
    try:
        ia, settings, srcs = parse_meta(mp)
    except Exception as e:
        f['errors'].append('meta.xml unreadable (%s)' % e)
        continue
    f['info'] = ia
    f['settings'] = settings
    f['srcs'] = srcs
    extra_files = [x for x in files if x != 'meta.xml' and x not in srcs]
    if extra_files:
        f['notes'].append('extra files not referenced by meta: ' + ', '.join(extra_files))
    if not srcs:
        f['errors'].append('meta.xml lists no map file')
        continue
    sps, cps, objs, pks = [], [], [], []
    other = collections.Counter()
    for s in srcs:
        p = os.path.join(folder, s)
        if not os.path.exists(p):
            f['errors'].append('map file missing (%s)' % s)
            continue
        try:
            a, b, c, d, o = parse_map(p)
        except Exception as e:
            f['errors'].append('map file unreadable (%s: %s)' % (s, e))
            continue
        sps += a; cps += b; objs += c; pks += d; other += o
    f['map_ok'] = not f['errors']
    f['sps'] = sps; f['cps'] = cps; f['pks'] = pks
    f['n_objects'] = len(objs)
    f['other_tags'] = dict(other)
    f['tokens'] = tokens(sps, cps, objs, pks)
    f['obj_models'] = collections.Counter(o['model'] for o in objs).most_common(5)

    # object grid for support checks
    grid = collections.defaultdict(list)
    for o in objs:
        if o['x'] is None or o['y'] is None or o['z'] is None:
            continue
        grid[(int(o['x'] // 120), int(o['y'] // 120))].append(o)

    def near_objects(x, y, z, rh=120, below=80, above=30):
        gx, gy = int(x // 120), int(y // 120)
        cnt = 0
        for i in (-1, 0, 1):
            for j in (-1, 0, 1):
                for o in grid.get((gx + i, gy + j), ()):
                    if (o['x']-x)**2 + (o['y']-y)**2 <= rh*rh and z - below <= o['z'] <= z + above:
                        cnt += 1
        return cnt

    # chains
    seen_ids = collections.Counter(c['id'] for c in cps)
    f['dup_cp_ids'] = [k for k, v in seen_ids.items() if v > 1]
    ids = set(seen_ids)
    f['dangling_nextids'] = sorted({c['nextid'] for c in cps if c['nextid'] and c['nextid'] not in ids})
    chains, hang = simulate_chains(cps)
    f['chain_hang'] = hang
    f['chain_lengths'] = [len(c) for c in chains]
    used = chains[0] if chains and not hang else []
    f['used_cps'] = [dict(x=c['x'], y=c['y'], z=c['z'], type=c['type'], veh=c['veh']) for c in used]

    # per-spawn checks
    sp_checks = []
    for s in sps:
        if None in (s['x'], s['y'], s['z']):
            sp_checks.append('badpos'); continue
        wl = water_level(s['x'], s['y'])
        sup = near_objects(s['x'], s['y'], s['z'])
        sp_checks.append(dict(wl=wl, sup=sup, zone=zone_of(s['x'], s['y'], s['z'])))
    f['sp_checks'] = sp_checks
    cp_checks = []
    for c in used:
        if None in (c['x'], c['y'], c['z']):
            cp_checks.append('badpos'); continue
        wl = water_level(c['x'], c['y'])
        cp_checks.append(dict(wl=wl, zone=zone_of(c['x'], c['y'], c['z'])))
    f['cp_checks'] = cp_checks
    f['obj_bbox'] = ([min(o['x'] for o in objs), min(o['y'] for o in objs), min(o['z'] for o in objs),
                      max(o['x'] for o in objs), max(o['y'] for o in objs), max(o['z'] for o in objs)]
                     if objs and all(o['x'] is not None for o in objs) else None)
    if n % 500 == 0:
        print('classics', n, file=sys.stderr)

# ---------------------------------------------------------------- other copies in the repo (titles)
others = []
classics_abs = os.path.abspath(CLASSICS)
skip_parts = ('[AncientMaps]', 'resource-cache', '.git')
for dp, dns, fns in os.walk(REPO):
    dns[:] = [d for d in dns if d not in skip_parts and os.path.abspath(os.path.join(dp, d)) != classics_abs]
    if 'meta.xml' not in fns:
        continue
    try:
        ia, settings, srcs = parse_meta(os.path.join(dp, 'meta.xml'))
    except Exception:
        continue
    if not srcs:
        continue
    sps, cps, objs, pks = [], [], [], []
    for s in srcs:
        p = os.path.join(dp, s)
        if not s.endswith('.map') or not os.path.exists(p):
            continue
        try:
            a, b, c, d, o = parse_map(p)
        except Exception:
            continue
        sps += a; cps += b; objs += c; pks += d
    if not (sps or cps or objs or pks):
        continue
    others.append(dict(path=os.path.relpath(dp, REPO).replace('\\', '/'), info=ia, settings=settings,
                       tokens=tokens(sps, cps, objs, pks)))
print('other copies', len(others), file=sys.stderr)

json.dump(dict(maps=feats, others=others), open(OUT, 'w'))
