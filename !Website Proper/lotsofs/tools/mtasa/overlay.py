"""Write SVG overlays (matching map_2048.png, world -3000..3000) for classics maps.

Usage: python -I overlay.py <classics dir> <first> <last>
Writes <classics dir>/overlays/<N>.svg.
"""
import os, re, sys, csv, math
import xml.etree.ElementTree as ET
from xml.sax.saxutils import escape, quoteattr

CLASSICS, FIRST, LAST = sys.argv[1], int(sys.argv[2]), int(sys.argv[3])
OUTDIR = os.path.join(CLASSICS, 'overlays')
SIZE = 720          # output box, px
MARGIN = 28         # px kept free around the map's elements
MIN_SPAN = 80.0   # m; tiny maps are not zoomed in further than this
BACKDROP_PX = 2048  # map_2048.png covers world -3000..3000
DEFAULT_CP = '#0000FE'
PICKUP_COLOURS = {'repair': '#ff2a2a', 'nitro': '#2ad4ff', 'vehiclechange': '#b4ff2a'}
CP_R, FINISH_R, SP_R, PK, LINE = 5.0, 8.0, 3.0, 6.0, 2.0


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


def parse_map(path):
    m = load_xml(path)
    sps, cps, pks = [], [], []
    for el in m:
        a = el.attrib
        x, y, z = fnum(a.get('posX')), fnum(a.get('posY')), fnum(a.get('posZ'))
        if el.tag == 'spawnpoint':
            sps.append(dict(x=x, y=y, z=z, rot=fnum(a.get('rotation'))))
        elif el.tag == 'checkpoint':
            cps.append(dict(x=x, y=y, z=z, id=a.get('id'), nextid=a.get('nextid') or None,
                            color=a.get('color')))
        elif el.tag == 'racepickup':
            pks.append(dict(x=x, y=y, z=z, type=a.get('type') or ''))
    return sps, cps, pks


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
    for cp in cps:
        ci = find_last(cp['id'])
        if ci is not None:
            chains[ci].append(cp)
            if cp['nextid'] is not None:
                ni = find_first(cp['nextid'])
                if ni is not None:
                    if ni == ci:
                        return chains, True
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
    return chains, False


def make_px(elems):
    """World -> output px for a square crop that fits elems in SIZE minus MARGIN."""
    xs, ys = [e['x'] for e in elems], [e['y'] for e in elems]
    cx, cy = (min(xs) + max(xs)) / 2, (min(ys) + max(ys)) / 2
    span = max(max(xs) - min(xs), max(ys) - min(ys), MIN_SPAN)
    s = (SIZE - 2 * MARGIN) / span
    return (lambda x, y: (SIZE / 2 + (x - cx) * s, SIZE / 2 - (y - cy) * s)), s


def colour(c):
    if c and re.fullmatch(r'#[0-9a-fA-F]{6}([0-9a-fA-F]{2})?', c.strip()):
        return c.strip()[:7]
    return DEFAULT_CP


def outline(c):
    r, g, b = (int(c[i:i + 2], 16) for i in (1, 3, 5))
    return '#000' if 0.299 * r + 0.587 * g + 0.114 * b > 90 else '#fff'


def f(v):
    return ('%.2f' % v).rstrip('0').rstrip('.')


titles = {}
with open(os.path.join(CLASSICS, 'classics.csv'), encoding='utf-8-sig', newline='') as fh:
    for row in csv.DictReader(fh):
        titles[int(row['Number'])] = row['Title']

os.makedirs(OUTDIR, exist_ok=True)
written, missing, notes = [], [], []
for n in range(FIRST, LAST + 1):
    mp = os.path.join(CLASSICS, 'z(%d)' % n, '%d.map' % n)
    if not os.path.isfile(mp):
        missing.append(n)
        continue
    try:
        sps, cps, pks = parse_map(mp)
    except ET.ParseError as e:
        notes.append('%d: map does not parse (%s)' % (n, e))
        continue
    ok = lambda e: e['x'] is not None and e['y'] is not None
    for kind, lst in (('spawnpoint', sps), ('checkpoint', cps), ('pickup', pks)):
        bad = [e for e in lst if not ok(e)]
        if bad:
            notes.append('%d: %d %s(s) without a position skipped' % (n, len(bad), kind))
    sps, pks = [e for e in sps if ok(e)], [e for e in pks if ok(e)]
    cps_all = cps
    cps = [e for e in cps if ok(e)]
    byid = {c['id']: c for c in cps if c['id']}
    chains, hang = simulate_chains(cps_all)
    if hang:
        notes.append('%d: checkpoint chain loops onto itself (race would hang)' % n)
    route = chains[0] if chains else []
    first = route[0] if route and ok(route[0]) else None
    finish = route[-1] if route and ok(route[-1]) else None
    if len(chains) > 1:
        notes.append('%d: %d checkpoint chains; race uses only the first' % (n, len(chains)))

    if not (sps or cps or pks):
        notes.append('%d: nothing to draw' % n)
        continue
    px, scale = make_px(sps + cps + pks)
    bx, by = px(-3000, 3000)
    title = titles.get(n) or ''
    L = ['<?xml version="1.0" encoding="UTF-8"?>',
         '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" '
         'xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" '
         'width="%d" height="%d" viewBox="0 0 %d %d">' % (SIZE, SIZE, SIZE, SIZE),
         '<title>%s</title>' % escape('Classic %d' % n + (' – ' + title if title else '')),
         '<desc>%s m per px</desc>' % f(1 / scale),
         '<g id="backdrop" inkscape:groupmode="layer" inkscape:label="backdrop">'
         '<rect width="%d" height="%d" fill="#3d6068"/>' % (SIZE, SIZE) +
         '<image href="../map_2048.png" xlink:href="../map_2048.png" x="%s" y="%s" '
         'width="%s" height="%s"/></g>' % (f(bx), f(by), f(6000 * scale), f(6000 * scale))]

    L.append('<g id="route" inkscape:groupmode="layer" inkscape:label="route" '
             'stroke-width="%s" stroke-linecap="round" fill="none">' % f(LINE))
    if sps and first:
        ax = sum(s['x'] for s in sps) / len(sps)
        ay = sum(s['y'] for s in sps) / len(sps)
        (x1, y1), (x2, y2) = px(ax, ay), px(first['x'], first['y'])
        L.append('<line class="spawn-line" x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s"/>'
                 % (f(x1), f(y1), f(x2), f(y2), colour(first['color'])))
    for c in cps:
        if not c['nextid']:
            continue
        t = byid.get(c['nextid'])
        if t is None:
            notes.append('%d: %s points at unknown checkpoint %s' % (n, c['id'], c['nextid']))
            continue
        (x1, y1), (x2, y2) = px(c['x'], c['y']), px(t['x'], t['y'])
        L.append('<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s"/>'
                 % (f(x1), f(y1), f(x2), f(y2), colour(t['color'])))
    L.append('</g>')

    L.append('<g id="pickups" inkscape:groupmode="layer" inkscape:label="pickups" '
             'stroke="#000" stroke-width="1">')
    for p in pks:
        x, y = px(p['x'], p['y'])
        L.append('<rect class="%s" x="%s" y="%s" width="%s" height="%s" fill="%s"/>'
                 % (escape(p['type']), f(x - PK / 2), f(y - PK / 2), f(PK), f(PK),
                    PICKUP_COLOURS.get(p['type'], '#ff2a2a')))
    L.append('</g>')

    L.append('<g id="checkpoints" inkscape:groupmode="layer" inkscape:label="checkpoints" '
             'stroke-width="1.2">')
    for c in cps:
        x, y = px(c['x'], c['y'])
        col = colour(c['color'])
        fin = c is finish
        L.append('<circle%s cx="%s" cy="%s" r="%s" fill="%s" stroke="%s"><title>%s</title></circle>'
                 % (' class="finish"' if fin else '', f(x), f(y), f(FINISH_R if fin else CP_R),
                    col, outline(col), escape(c['id'] or '')))
    L.append('</g>')

    L.append('<g id="spawnpoints" inkscape:groupmode="layer" inkscape:label="spawnpoints" '
             'fill="#fff" stroke="#000" stroke-width="1" stroke-linejoin="round">')
    for s in sps:
        x, y = px(s['x'], s['y'])
        r = s['rot'] or 0.0
        # Drawn pointing north (screen up); GTA rotation is anticlockwise, SVG rotate is clockwise.
        L.append('<g transform="translate(%s %s) rotate(%s)"><path d="M0,-9.5 L3.6,-2.4 L-3.6,-2.4 Z"/>'
                 '<circle r="%s"/></g>' % (f(x), f(y), f(-r), f(SP_R)))
    L.append('</g>')
    L.append('</svg>')

    out = os.path.join(OUTDIR, '%d.svg' % n)
    with open(out, 'w', encoding='utf-8', newline='\n') as fh:
        fh.write('\n'.join(L) + '\n')
    ET.parse(out)
    written.append((n, len(sps), len(cps), len(pks)))

print('written', len(written))
for w in written:
    print('  %d: %d spawnpoints, %d checkpoints, %d pickups' % w)
print('missing', missing)
print('notes', *notes, sep='\n  ')
