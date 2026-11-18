"""Decode GTA:SA zone names into zones.json (needed by extract.py).

Reads text/american.gxt (MAIN table) and data/info.zon + data/map.zon from the GTA folder.
Zone labels are matched to GXT keys by CRC32 of the upper-cased label.

Usage: python -I gxt.py <gta dir> <out zones.json>
"""
import struct, zlib, json, os, sys, collections

G, OUT = sys.argv[1:3]
b = open(os.path.join(G, 'text', 'american.gxt'), 'rb').read()
ver, bits = struct.unpack_from('<HH', b, 0)
assert b[4:8] == b'TABL'
n = struct.unpack_from('<I', b, 8)[0] // 12
tabs = [(b[12 + i * 12:20 + i * 12].rstrip(b'\0').decode(), struct.unpack_from('<I', b, 20 + i * 12)[0]) for i in range(n)]
name, off = tabs[0]
assert b[off:off + 4] == b'TKEY'
ks = struct.unpack_from('<I', b, off + 4)[0]
ent = [struct.unpack_from('<II', b, off + 8 + i * 8) for i in range(ks // 8)]
dat = off + 8 + ks
assert b[dat:dat + 4] == b'TDAT'
d0 = dat + 8
strs = {}
for o, h in ent:
    e = b.index(b'\0', d0 + o)
    strs[h] = b[d0 + o:e].decode('latin-1')

zones = {}
for f in ('info.zon', 'map.zon'):
    for line in open(os.path.join(G, 'data', f)):
        p = [x.strip() for x in line.split(',')]
        if len(p) < 10:
            continue
        lab = p[9]
        h = zlib.crc32(lab.upper().encode())
        nm = strs.get(h) or strs.get(h ^ 0xFFFFFFFF)
        zones.setdefault(f, []).append(dict(code=p[0], type=int(p[1]), box=[float(x) for x in p[2:8]],
                                            level=int(p[8]), label=lab, name=nm))
print('gxt strings', len(strs), '| info.zon zones', len(zones['info.zon']),
      '| unnamed', sum(1 for z in zones['info.zon'] if not z['name']))
json.dump(zones, open(OUT, 'w'), indent=0)
