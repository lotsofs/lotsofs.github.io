"""Write the website's slim zones file from zones.json (made by gxt.py).

Usage: python -I site_zones.py <zones.json> <public zones.json> <app zone_names.json>

Run from tools/mtasa: python -I site_zones.py zones.json ../../public/modules/mtasa/data/zones.json ../../app/modules/mtasa/data/zone_names.json

The site (public/modules/mtasa/js/analysis.js, and fields.php for names)
needs each zone's label, box and name, so this keeps info.zon alone and
drops what is never read: map.zon (unnamed, unused), type and level (the same
everywhere), code (it only told the counties from the sub-zones, which are two
lists here) and the counties' z. A zone is known by its game text label (one
per name; Linden Station alone has two), which classics.csv stores and the
site names through `names`, with the two ids extract.py adds: OUTSIDE past the
world's edge and SA where no county holds the point. The sub-zones are sorted
smallest area first, as zone_of() in extract.py searches them.
"""
import json, sys

SRC, OUT, NAMES = sys.argv[1:4]
COUNTIES = {'LA', 'RED', 'VE', 'SF', 'ROBAD', 'ROBAD1', 'BONE', 'WHET', 'FLINTC'}
zones = json.load(open(SRC))['info.zon']
counties = [[z['label']] + [z['box'][i] for i in (0, 1, 3, 4)] for z in zones if z['code'] in COUNTIES]
subs = sorted((z for z in zones if z['code'] not in COUNTIES),
              key=lambda z: (z['box'][3] - z['box'][0]) * (z['box'][4] - z['box'][1]))
names = {z['label']: z['name'] for z in zones}
names.update({'OUTSIDE': 'Outside map', 'SA': 'San Andreas'})
names = dict(sorted(names.items()))
slim = {'counties': counties, 'zones': [[z['label']] + z['box'] for z in subs], 'names': names}
# The boxes and names for the browser; the names alone for PHP too, which
# cannot reach the web root by path on the host.
for path, data in ((OUT, slim), (NAMES, names)):
    with open(path, 'w', encoding='utf-8', newline='\n') as fh:
        fh.write(json.dumps(data, separators=(',', ':'), ensure_ascii=False))
        fh.write('\n')
print('counties', len(counties), '| zones', len(subs), '| names', len(names))
