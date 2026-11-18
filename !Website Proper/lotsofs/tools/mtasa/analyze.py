"""Turn features.json into the classics CSV.

Usage: python -I analyze.py <features.json> <out.csv> <dupcands.json>
"""
import sys, json, re, csv, math, html, collections

FEATS, OUT = sys.argv[1:3]
data = json.load(open(FEATS))
maps = data['maps']
others = data['others']

VEH = """Landstalker,Bravura,Buffalo,Linerunner,Perennial,Sentinel,Dumper,Firetruck,Trashmaster,Stretch,Manana,
Infernus,Voodoo,Pony,Mule,Cheetah,Ambulance,Leviathan,Moonbeam,Esperanto,Taxi,Washington,Bobcat,Mr Whoopee,
BF Injection,Hunter,Premier,Enforcer,Securicar,Banshee,Predator,Bus,Rhino,Barracks,Hotknife,Trailer 1,Previon,
Coach,Cabbie,Stallion,Rumpo,RC Bandit,Romero,Packer,Monster,Admiral,Squalo,Seasparrow,Pizzaboy,Tram,Trailer 2,
Turismo,Speeder,Reefer,Tropic,Flatbed,Yankee,Caddy,Solair,Berkley's RC Van,Skimmer,PCJ-600,Faggio,Freeway,
RC Baron,RC Raider,Glendale,Oceanic,Sanchez,Sparrow,Patriot,Quad,Coastguard,Dinghy,Hermes,Sabre,Rustler,ZR-350,
Walton,Regina,Comet,BMX,Burrito,Camper,Marquis,Baggage,Dozer,Maverick,News Chopper,Rancher,FBI Rancher,Virgo,
Greenwood,Jetmax,Hotring Racer,Sandking,Blista Compact,Police Maverick,Boxville,Benson,Mesa,RC Goblin,
Hotring Racer 3,Hotring Racer 2,Bloodring Banger,Rancher Lure,Super GT,Elegant,Journey,Bike,Mountain Bike,
Beagle,Cropduster,Stuntplane,Tanker,Roadtrain,Nebula,Majestic,Buccaneer,Shamal,Hydra,FCR-900,NRG-500,HPV1000,
Cement Truck,Towtruck,Fortune,Cadrona,FBI Truck,Willard,Forklift,Tractor,Combine,Feltzer,Remington,Slamvan,
Blade,Freight,Brownstreak,Vortex,Vincent,Bullet,Clover,Sadler,Firetruck LA,Hustler,Intruder,Primo,Cargobob,
Tampa,Sunrise,Merit,Utility Van,Nevada,Yosemite,Windsor,Monster A,Monster B,Uranus,Jester,Sultan,Stratum,
Elegy,Raindance,RC Tiger,Flash,Tahoma,Savanna,Bandito,Freight Flat,Streak Carriage,Kart,Mower,Dune,Sweeper,
Broadway,Tornado,AT-400,DFT-30,Huntley,Stafford,BF-400,Newsvan,Tug,Trailer 3,Emperor,Wayfarer,Euros,Hotdog,
Club,Freight Box,Trailer 3b,Andromada,Dodo,RC Cam,Launch,Police LS,Police SF,Police LV,Police Ranger,Picador,
S.W.A.T.,Alpha,Phoenix,Glendale Damaged,Sadler Damaged,Baggage Trailer A,Baggage Trailer B,Tug Stairs,
Boxville Black,Farm Trailer,Utility Trailer""".replace('\n', '').split(',')
assert len(VEH) == 212, len(VEH)
def vname(m):
    return VEH[m - 400] if m is not None and 400 <= m <= 611 else 'invalid(%s)' % m

PLANES = {460, 476, 511, 512, 513, 519, 520, 553, 577, 592, 593}
HELIS = {417, 425, 447, 469, 487, 488, 497, 548, 563}
RC = {441, 464, 465, 501, 564, 594}
BOATS = {430, 446, 452, 453, 454, 472, 473, 484, 493, 595}
HOVER = {539}
BIKES = {448, 461, 462, 463, 468, 471, 521, 522, 523, 581, 586}
BICYCLES = {481, 509, 510}
TRAINS = {449, 537, 538, 569, 570, 590}
TRAILERS = {435, 450, 584, 591, 606, 607, 608, 610, 611}
MONSTERS = {444, 556, 557}
ARMED = {425, 520, 476, 447, 432, 430, 464}
POLICE = {596, 597, 598, 599, 523, 427, 490, 528, 497, 601}
FLOATS = BOATS | HOVER | {460, 447, 417}  # boats, Vortex, Skimmer, Seasparrow, Leviathan float
AIR = PLANES | HELIS | {464, 465, 501}

def vclass(m):
    if m in PLANES: return 'plane'
    if m in HELIS: return 'helicopter'
    if m in RC: return 'RC'
    if m in BOATS or m in HOVER: return 'boat'
    if m in BICYCLES: return 'bicycle'
    if m in BIKES: return 'motorbike'
    if m in TRAINS: return 'train'
    if m in TRAILERS: return 'trailer'
    if m in MONSTERS: return 'monster truck'
    if m == 432: return 'tank'
    return 'car'

ORD = {2: 'duplicate', 3: 'triplicate', 4: 'quadruplicate', 5: 'quintuplicate', 6: 'sextuplicate',
       7: 'septuplicate', 8: 'octuplicate', 9: 'nonuplicate', 10: 'decuplicate'}
def ordinal_word(k):
    if k in ORD:
        return ORD[k]
    suf = 'th' if 10 <= k % 100 <= 20 else {1: 'st', 2: 'nd', 3: 'rd'}.get(k % 10, 'th')
    return '%d%s copy' % (k, suf)

def clean(s):
    if s is None:
        return ''
    s = html.unescape(s)
    return re.sub(r'\s+', ' ', s).strip()

def dist(a, b):
    return math.sqrt((a[0]-b[0])**2 + (a[1]-b[1])**2 + (a[2]-b[2])**2)

# ================================================================ duplicates
by_num = {f['num']: f for f in maps}
tokmaps = {}
for f in maps:
    if f.get('tokens'):
        tokmaps[f['num']] = collections.Counter(f['tokens'])  # multiset-safe already
toksets = {n: set(t) for n, t in tokmaps.items()}
df = collections.Counter()
for s in toksets.values():
    df.update(s)
index = collections.defaultdict(list)
for n in sorted(toksets):
    for t in toksets[n]:
        if df[t] <= 400:
            index[t].append(n)

def jacc(a, b):
    return len(a & b) / len(a | b) if (a or b) else 0.0

def kind_sets(s):
    out = collections.defaultdict(set)
    for t in s:
        out[t[0]].add(t)
    return out

KIND = {'s': 'spawnpoints', 'c': 'checkpoints', 'o': 'objects', 'p': 'pickups'}

def best_match(n, s, pool_filter):
    cand = collections.Counter()
    for t in s:
        for m in index.get(t, ()):
            if pool_filter(m):
                cand[m] += 1
    best = None
    for m, shared in cand.most_common(40):
        o = toksets[m]
        if shared < 0.5 * min(len(s), len(o)):
            continue
        j = jacc(s, o)
        if best is None or j > best[1] or (j == best[1] and m < best[0]):
            best = (m, j)
    return best

# Tolerant candidate scores from dupcheck.py: per map, best earlier maps with overall and
# per-kind similarity (elements count as the same within 3 m; objects must share a model).
dupcands = {int(k): v for k, v in json.load(open(sys.argv[3])).items()} if len(sys.argv) > 3 else {}
CONF_RANK = {'high': 3, 'medium': 2, 'low': 1}
root = {}
group_rank = collections.Counter()
dup_info = {}

def meta_key(f):
    return (clean(f['info'].get('author')), clean(f['info'].get('description')))

def judge(n, c):
    """Return (confidence, rule) if candidate c counts as a duplicate of an earlier map, else None."""
    sim, per = c['sim'], c['per']
    fa, fb = by_num[n], by_num[c['m']]
    an, dn = meta_key(fa)
    am, dm = meta_key(fb)
    same_author = bool(an) and an == am
    same_desc = bool(dn) and dn == dm
    same_grid = per.get('s', 0) >= 0.9
    same_objs = per.get('o', 1.0 if 'o' not in per else 0) >= 0.9
    has_objs = 'o' in per
    if sim >= 0.9:
        return 'high', ''
    if sim >= 0.7:
        return 'medium', ''
    if same_grid and same_objs and has_objs:
        return ('medium' if (same_author or same_desc) else 'low'), 'same spawn grid and objects, other route'
    if sim >= 0.5:
        return 'low', ''
    if sim >= 0.3 and (same_author or same_desc or same_grid):
        why = [w for w, ok in (('same author', same_author), ('same description', same_desc), ('same spawn grid', same_grid)) if ok]
        return 'low', 'partial match with ' + ' and '.join(why)
    if same_grid and same_objs and (same_author or same_desc):
        return 'low', 'same spawn grid, same author/description, other route'
    return None

for n in sorted(toksets):
    s = toksets[n]
    if len(s) < 3:
        continue
    ka = kind_sets(s)
    best = None  # (confidence rank, sim, -m, cand, conf, rule)
    for c in dupcands.get(n, []):
        j = judge(n, c)
        if j:
            key = (CONF_RANK[j[0]], c['sim'], -c['m'])
            if best is None or key > best[0]:
                best = (key, c, j[0], j[1])
    # the earlier exact-position check, kept so nothing it found is lost
    b = best_match(n, s, lambda m, n=n: m < n)
    if b and b[1] >= 0.75 and best is None:
        c = dict(m=b[0], sim=b[1], per={k: jacc(ka[k], kind_sets(toksets[b[0]])[k]) for k in 'scop'
                                        if ka[k] or kind_sets(toksets[b[0]])[k]})
        best = ((0, b[1], -b[0]), c, 'high' if b[1] >= 0.9 else 'medium', '')
    if best is None:
        root[n] = n
        group_rank[n] = 1
        continue
    if True:
        _, c, conf, rule = best
        match, sim = c['m'], c['sim']
        b = (match, sim)
        r = root[match]
        root[n] = r
        group_rank[r] += 1
        diffs = [rule] if rule else []
        for k in 'scop':
            if k in c['per'] and c['per'][k] < 0.995:
                diffs.append('%s %d%% same' % (KIND[k], round(100 * c['per'][k])))
        fa, fb = by_num[n], by_num[b[0]]
        for lab, ga in (('author', lambda f: clean(f['info'].get('author'))),
                        ('description', lambda f: clean(f['info'].get('description'))),
                        ('settings', lambda f: json.dumps(f.get('settings'), sort_keys=True))):
            if ga(fa) != ga(fb):
                diffs.append(lab + ' differs')
        sa = collections.Counter(x['veh'] for x in fa['sps'])
        sb = collections.Counter(x['veh'] for x in fb['sps'])
        if sa != sb:
            diffs.append('spawn vehicles differ')
        dup_info[n] = dict(root=r, rank=group_rank[r], closest=b[0], sim=b[1], diffs=diffs, conf=conf)

group_members = collections.defaultdict(list)
for n, r in root.items():
    group_members[r].append(n)

# ================================================================ titles from other copies
title_hits = collections.defaultdict(list)
for o in others:
    s = set(o['tokens'])
    if len(s) < 3:
        continue
    b = best_match(None, s, lambda m: True)
    if not b or b[1] < 0.75:
        continue
    name = clean(o['info'].get('name'))
    if not name or re.fullmatch(r'\d+', name):
        continue
    title_hits[b[0]].append((b[1], name, o['path']))

# ================================================================ categories
KW = [
    ('Cops Robbers', r"\bcops?\b|\bpolice\b|robbers?|\bsuspects?\b|\bcriminals?\b|lawbreak|\bthie(f|ves)\b|\bbandits?\b|\bswat\b|\bfbi\b|fugitive|pursuit|\bcopcars?\b"),
    ('Hunt the Hunter', r"\bstq\b|stop the q|\bqueer\b|\bhunt(s|ed|ing)?\b(?! ?war)|\bchase\b|must (kill|stop|catch|destroy)|kill (the|him|all the)|escape the|evade|\bprey\b|\btarget\b|\boj\b|\bmice\b|cat (vs|and) mouse|\bkong\b"),
    ('Destruction Derby', r"\bderby\b|\bdd\b|\bsumo\b|last (man|one|car) (standing|alive)|destruction|\bdemolition\b|\bpush\b.*\boff\b|knock (them|others|everyone)|\bram\b|\bramming\b"),
    ('Vehicle Weapon Combat', r"\bdm\b|death ?match|\bwar\b|battle|combat|dogfight|\bfight\b|\btdm\b|shoot ?out|\bshoot\b|air ?strike|\bkill\b|\bblow\b"),
    ('Teamwork', r"team ?work|\bco-?op\b|work together|help (each|your)|\bhelper\b|\bteammates?\b"),
    ('Teams', r"\bteam game\b|\bteams?\b"),
    ('Versus Race', r"\bvs\.?\b|\bversus\b|\b\d ?v ?\d\b"),
    ('Freeroam', r"free ?roam|stunt ?park|\bstunt(s|ing)?\b|\bfree ?ride\b|playground|\bexplore\b|\bhang ?out\b|just drive|skate ?park"),
    ('Misc', r"\bgolf\b|football|soccer|bowling|\bhide\b|\bseek\b|basketball|\bpool\b|\bmaze\b|parkour|\bpuzzle\b|\btag\b|\bctf\b|capture the|\bbomb\b|\bzombie|\bdance\b|minigame|\bquiz\b|\bpinball\b|\bboxing\b|\bjoust|\bexecut"),
    ('Race', r"\brace\b|\braces\b|\bracing\b|\blaps?\b|\bcircuit\b|\bcirquit\b|\bcheckpoints?\b|\bsprint\b|\btrack\b|\bcourse\b|\brally\b|\bdrag\b|\bdrift"),
]

def composition(sps):
    vs = [s['veh'] for s in sps if s['veh'] is not None]
    c = collections.Counter(vs)
    n = len(vs)
    roles = collections.Counter()
    for v in vs:
        roles['armed' if v in ARMED else 'police' if v in POLICE else 'other'] += 1
    return c, n, roles

def categorize(f, cps_n, sps, desc, respawn_mode, all_vehicles):
    text = desc.lower()
    hits = [cat for cat, rx in KW if re.search(rx, text)]
    c, n, roles = composition(sps)
    armed_share = roles['armed'] / n if n else 0
    police_share = roles['police'] / n if n else 0
    any_armed = bool(all_vehicles & ARMED)
    top = c.most_common()
    top_share = top[0][1] / n if n else 0
    # one-vs-many: a small minority of vehicles against a large majority of one model
    minority = n - top[0][1] if top else 0
    one_vs_many = n >= 6 and 1 <= minority <= max(2, n // 8) and top_share >= 0.75
    minority_models = {m for m, k in top[1:]}
    armed_minority = one_vs_many and (bool(minority_models & ARMED) != (top[0][0] in ARMED))
    reasons = []
    if hits:
        reasons.append('description mentions: ' + ', '.join(h.lower() for h in hits))
    def pick(cat, conf, why):
        reasons.append(why)
        return cat, conf, '; '.join(reasons)

    # strong keyword-driven picks
    if 'Cops Robbers' in hits and (police_share >= 0.25 or re.search(r'robber|police.*(suspect|criminal)|cops?\b.*(suspect|criminal|bandit|thie)', text)):
        return pick('Cops Robbers', 'high' if police_share >= 0.25 else 'medium', 'police vehicles %d%% of spawns' % round(100 * police_share))
    if 'Hunt the Hunter' in hits and ('stq' in text or one_vs_many or re.search(r'hunt|chase|\boj\b|mice|kong|evade|escape', text)):
        return pick('Hunt the Hunter', 'high' if one_vs_many else 'medium', 'one-vs-many spawn mix' if one_vs_many else 'hunter/target wording')
    if armed_minority and cps_n <= 3:
        return pick('Hunt the Hunter', 'medium' if hits else 'low', 'one armed side vs many unarmed (or the reverse)')
    if 'Teamwork' in hits:
        return pick('Teamwork', 'medium', 'cooperation wording')
    if cps_n == 0 and 'Destruction Derby' in hits:
        return pick('Destruction Derby', 'high', 'no checkpoints')
    if 'Cops Robbers' in hits and police_share >= 0.15:
        return pick('Cops Robbers', 'medium', 'police vehicles %d%% of spawns' % round(100 * police_share))
    if 'Misc' in hits and not (cps_n >= 3 and 'Race' in hits):
        return pick('Misc', 'medium' if cps_n < 5 else 'low', 'minigame wording')
    if cps_n == 0:
        if 'Freeroam' in hits and respawn_mode in ('timelimit', 'waves'):
            return pick('Freeroam', 'high', 'no checkpoints, respawn on')
        if any_armed and (armed_share >= 0.4 or 'Vehicle Weapon Combat' in hits or 'Teams' in hits):
            return pick('Vehicle Weapon Combat', 'high' if armed_share >= 0.4 and 'Vehicle Weapon Combat' in hits else 'medium',
                        'no checkpoints, armed vehicles %d%% of spawns' % round(100 * armed_share))
        if 'Vehicle Weapon Combat' in hits:
            return pick('Destruction Derby', 'medium', 'no checkpoints, combat wording but no armed vehicles (ramming)')
        if one_vs_many:
            return pick('Hunt the Hunter', 'low', 'no checkpoints, one-vs-many spawn mix')
        if 'Freeroam' in hits:
            return pick('Freeroam', 'medium', 'no checkpoints, freeroam wording')
        if respawn_mode in ('timelimit', 'waves'):
            return pick('Freeroam', 'low', 'no checkpoints, respawn on (race plays it as freeroam)')
        return pick('Destruction Derby', 'medium' if not hits else 'low', 'no checkpoints, no respawn (race plays it as destruction derby)')
    # has checkpoints
    if 'Hunt the Hunter' in hits:
        return pick('Hunt the Hunter', 'low', 'hunter/target wording with checkpoints')
    big_groups = [m for m, k in top if k >= max(2, 0.15 * n)]
    mixed_roles = n >= 4 and 0.1 <= armed_share <= 0.9
    if 'Versus Race' in hits or 'Teams' in hits:
        return pick('Versus Race', 'medium' if (mixed_roles or len(big_groups) >= 2) else 'low', 'checkpoints with two-sided wording')
    if mixed_roles and cps_n <= 25:
        return pick('Versus Race', 'low', 'checkpoints, armed vehicles %d%% of spawns' % round(100 * armed_share))
    if one_vs_many and cps_n <= 3:
        return pick('Hunt the Hunter', 'low', 'few checkpoints, one-vs-many spawn mix')
    if cps_n <= 2 and any_armed and ('Vehicle Weapon Combat' in hits or armed_share >= 0.4):
        return pick('Vehicle Weapon Combat', 'low', '%d checkpoint(s), combat wording/vehicles' % cps_n)
    if 'Destruction Derby' in hits and cps_n <= 2:
        return pick('Destruction Derby', 'low', '%d checkpoint(s), derby wording' % cps_n)
    if 'Freeroam' in hits and cps_n <= 2:
        return pick('Freeroam', 'low', '%d checkpoint(s), freeroam wording' % cps_n)
    conf = 'high' if ('Race' in hits or cps_n >= 5) and armed_share < 0.1 else 'medium'
    return pick('Race', conf, '%d checkpoints' % cps_n)

# ================================================================ rows
COLS = ['Number', 'Folder', 'Title', 'Title source', 'Description', 'Author', 'Version',
        'Category guess', 'Category confidence', 'Category reason',
        'Duplicate', 'Duplicate confidence', 'Duplicate of (closest)', 'Duplicate similarity', 'Duplicate differences', 'Copies of this map',
        'Errors', 'Warnings',
        'Respawn enabled', 'Respawn mode', 'Respawn time (s)', 'Duration (s)', 'Time of day', 'Weather',
        'Race mode', 'Checkpoints', 'Checkpoints used by race', 'Returns to start', 'Route length (m)',
        'Ring checkpoints', 'Checkpoint vehicle changes', 'Spawnpoints', 'Objects', 'Top object models',
        'Repair pickups', 'Nitro pickups', 'Vehicle change pickups',
        'Start vehicles', 'Vehicles via changes', 'Vehicle classes',
        'Start zone', 'Finish zone', 'Notes']

rows = []
for f in maps:
    n = f['num']
    r = {c: '' for c in COLS}
    r['Number'] = n
    r['Folder'] = 'z(%d)' % n
    if f.get('missing_folder'):
        r['Errors'] = 'folder missing'
        rows.append(r)
        continue
    errors, warns, notes = list(f['errors']), [], list(f['notes'])
    info = f.get('info', {})
    st = f.get('settings', {}) or {}
    r['Description'] = clean(info.get('description'))
    r['Author'] = clean(info.get('author'))
    r['Version'] = clean(info.get('version'))
    extra_info = [k for k in info if k not in ('type', 'gamemodes', 'name', 'author', 'description', 'version')]
    if extra_info:
        warns.append('meta <info> has stray attributes (%s) - map data pasted into meta' % ', '.join(extra_info))
    nm = clean(info.get('name'))
    if nm and nm != str(n):
        r['Title'], r['Title source'] = nm, 'meta.xml'
    hits = sorted(title_hits.get(n, []), key=lambda h: (-h[0], h[2]))
    if hits and not r['Title']:
        names = []
        for sim, name, path in hits:
            if name not in names:
                names.append(name)
        r['Title'] = names[0]
        r['Title source'] = '; '.join('%s (%d%% match)' % (p.replace('server/mods/deathmatch/resources/', ''), round(100 * s)) for s, _, p in hits)
        if len(names) > 1:
            notes.append('other copies are also titled: ' + ' / '.join(names[1:]))

    rm = st.get('#respawn')
    r['Respawn mode'] = rm or ''
    r['Respawn enabled'] = 'null' if rm is None else ('false' if rm == 'none' else 'true')
    r['Respawn time (s)'] = st.get('#respawntime', '')
    r['Duration (s)'] = st.get('#duration', '')
    r['Time of day'] = st.get('#time', '')
    r['Weather'] = st.get('#weather', '')
    if rm == 'waves':
        notes.append('respawn mode "waves" is from old MTA:Race; MTA:SA race treats it as respawn on')

    if 'sps' not in f:
        r['Errors'] = '; '.join(errors)
        rows.append(r)
        continue
    sps, cps, pks, used = f['sps'], f['cps'], f['pks'], f['used_cps']
    r['Spawnpoints'] = len(sps)
    r['Checkpoints'] = len(cps)
    r['Objects'] = f['n_objects']
    r['Top object models'] = ', '.join('%d x%d' % (m, k) for m, k in f['obj_models'])
    pc = collections.Counter(p['type'] for p in pks)
    r['Repair pickups'] = pc['repair']
    r['Nitro pickups'] = pc['nitro']
    r['Vehicle change pickups'] = pc['vehiclechange']
    if f['other_tags']:
        notes.append('unknown map elements: ' + ', '.join('%s x%d' % kv for kv in f['other_tags'].items()))

    if not (sps or cps or pks or f['n_objects']):
        errors.append('map file is empty')
    elif not sps:
        errors.append('no spawnpoints')

    # checkpoint chain
    if f['chain_hang']:
        errors.append('checkpoint nextid loops back onto its own chain: race hangs while loading')
    if f['dup_cp_ids']:
        warns.append('duplicate checkpoint ids: ' + ', '.join(f['dup_cp_ids'][:5]))
    if f['dangling_nextids']:
        warns.append('checkpoint nextid points to missing id: ' + ', '.join(f['dangling_nextids'][:5]))
    ch = f['chain_lengths']
    if len(ch) > 1 and not f['chain_hang']:
        warns.append('checkpoints form %d separate chains; race only uses the first (%d of %d checkpoints)'
                     % (len(ch), ch[0], len(cps)))
    r['Checkpoints used by race'] = len(used) if not f['chain_hang'] else 0
    r['Ring checkpoints'] = sum(1 for c in cps if c['type'] == 'ring')
    cpv = [c['veh'] for c in cps if c['veh'] is not None]
    r['Checkpoint vehicle changes'] = len(cpv)

    # race mode
    cps_live = len(used) if not f['chain_hang'] else 0
    eff = rm or 'none'  # race default respawn mode is none
    if cps_live:
        r['Race mode'] = 'Sprint'
    elif eff == 'none':
        r['Race mode'] = 'Destruction derby'
    elif eff == 'timelimit':
        r['Race mode'] = 'Freeroam'
    else:
        r['Race mode'] = 'none (no mode fits)'
        warns.append('no checkpoints and respawn "%s": no MTA:SA race mode applies' % eff)

    # vehicles
    bad_sp_veh = [s['vraw'] for s in sps if s['veh'] is None or not 400 <= s['veh'] <= 611]
    if bad_sp_veh:
        errors.append('%d spawnpoint(s) with invalid vehicle (%s)' % (len(bad_sp_veh), ', '.join(sorted({str(v) for v in bad_sp_veh})[:3])))
    vc = collections.Counter(s['veh'] for s in sps if s['veh'] is not None)
    r['Start vehicles'] = ', '.join('%d x%d' % (m, k) if k > 1 else '%d' % m for m, k in vc.most_common())
    change = collections.Counter([p['veh'] for p in pks if p['type'] == 'vehiclechange' and p['veh'] is not None] + cpv)
    r['Vehicles via changes'] = ', '.join('%d' % m for m, _ in change.most_common())
    bad_pk = [p for p in pks if p['type'] == 'vehiclechange' and (p['veh'] is None or not 400 <= p['veh'] <= 611)]
    if bad_pk:
        warns.append('%d vehicle change pickup(s) with missing/invalid vehicle' % len(bad_pk))
    allv = set(vc) | set(change)
    classes = collections.Counter(vclass(m) for m in vc.elements())
    r['Vehicle classes'] = ', '.join(sorted({vclass(m) for m in allv if 400 <= m <= 611}))

    # spawn checks
    inwater = under = sea = sky = boat_land = badpos = 0
    for s, chk in zip(sps, f['sp_checks']):
        if chk == 'badpos':
            badpos += 1
            continue
        wl, sup, v = chk['wl'], chk['sup'], s['veh']
        if wl is not None and s['z'] < wl - 2 and not sup:
            under += 1
        elif wl is not None and s['z'] < wl + 1.2 and not sup and v not in FLOATS and v not in AIR:
            inwater += 1
        if (abs(s['x']) > 3000 or abs(s['y']) > 3000) and not sup and v not in AIR and not (v in FLOATS and abs(s['z']) < 3):
            sea += 1
        if abs(s['x']) <= 3000 and abs(s['y']) <= 3000 and s['z'] > 600 and not sup and v not in AIR:
            sky += 1
        if v in BOATS and (wl is None or s['z'] > wl + 4) and not sup:
            boat_land += 1
    if badpos:
        errors.append('%d spawnpoint(s) with unreadable position' % badpos)
    if under:
        warns.append('%d spawnpoint(s) under the water surface with no objects nearby' % under)
    if inwater:
        warns.append('%d spawnpoint(s) at water level with no objects nearby (land vehicle spawns in water)' % inwater)
    if sea:
        warns.append('%d spawnpoint(s) beyond the map edge with no objects nearby (floating over open sea)' % sea)
    if sky:
        warns.append('%d spawnpoint(s) above 600 m with no objects nearby (floating in the air?)' % sky)
    if boat_land:
        warns.append('%d boat spawnpoint(s) not on water' % boat_land)
    pts = [(s['x'], s['y'], s['z']) for s in sps if None not in (s['x'], s['y'], s['z'])]
    close = 0
    for i in range(len(pts)):
        for j in range(i + 1, len(pts)):
            if dist(pts[i], pts[j]) < 1.0:
                close += 1
    if close:
        warns.append('%d pair(s) of spawnpoints less than 1 m apart' % close)
    if sps and len(sps) < 4:
        notes.append('only %d spawnpoint(s)' % len(sps))
    far = [p for p in pts if abs(p[0]) > 20000 or abs(p[1]) > 20000 or abs(p[2]) > 20000]
    if far:
        warns.append('%d spawnpoint(s) extremely far out (>20 km)' % len(far))

    # checkpoint position checks
    landonly = allv and not (allv & (FLOATS | AIR))
    if used and not f['chain_hang']:
        cpw = sum(1 for c, k in zip(used, f['cp_checks'])
                  if k != 'badpos' and k['wl'] is not None and c['z'] < k['wl'] - 3)
        if cpw and landonly:
            warns.append('%d checkpoint(s) more than 3 m under water in a land-vehicle map' % cpw)
        if any(k == 'badpos' for k in f['cp_checks']):
            errors.append('checkpoint(s) with unreadable position')

    # route
    if pts and used and not f['chain_hang']:
        cx = sum(p[0] for p in pts) / len(pts); cy = sum(p[1] for p in pts) / len(pts); cz = sum(p[2] for p in pts) / len(pts)
        seq = [(c['x'], c['y'], c['z']) for c in used if None not in (c['x'], c['y'], c['z'])]
        L, prev = 0.0, (cx, cy, cz)
        for p in seq:
            L += dist(prev, p); prev = p
        r['Route length (m)'] = round(L)
        r['Returns to start'] = 'yes' if seq and math.hypot(seq[-1][0]-cx, seq[-1][1]-cy) < 75 else 'no'
        laps = 1
        m = len(seq)
        for p in range(1, m // 2 + 1):
            if m % p == 0 and all(dist(seq[i], seq[i + p]) < 5 for i in range(m - p)):
                laps = m // p
                break
        r['Laps'] = laps
        fz = f['cp_checks'][-1]
        r['Finish zone'] = fz['zone'][0] if fz != 'badpos' else ''
    zs = [k for k in f['sp_checks'] if k != 'badpos']
    if zs:
        r['Start zone'] = ', '.join(z for z, _ in collections.Counter(k['zone'][0] for k in zs).most_common())
        if pts and sum(p[2] for p in pts) / len(pts) > 700 and abs(pts[0][0]) <= 3000:
            notes.append('starts at interior height (z > 700)')

    # description-only hints
    d = r['Description'].lower()
    if re.search(r'\b(beta|test|wip|unfinished|not finished|work in progress)\b', d):
        notes.append('description says test/beta/unfinished')
    if re.search(r'\b(\d+)\s*players?\b', d):
        notes.append('description names a player count: ' + re.search(r'\b(\d+)\s*players?\b', d).group(0))
    lapm = re.search(r'\b(\d+)\s*-?\s*laps?\b', d)
    if lapm and r.get('Laps', '') not in ('', int(lapm.group(1))):
        notes.append('description says %s, checkpoints give %s lap(s)' % (lapm.group(0), r.get('Laps', '')))

    # duplicates
    if n in dup_info:
        di = dup_info[n]
        r['Duplicate'] = '%s of %d' % (ordinal_word(di['rank']), di['root'])
        r['Duplicate of (closest)'] = di['closest']
        r['Duplicate similarity'] = '%d%%' % round(100 * di['sim'])
        r['Duplicate confidence'] = di['conf']
        r['Duplicate differences'] = '; '.join(di['diffs']) if di['diffs'] else 'identical content'
        if di['root'] != di['closest'] and False:
            pass
    if root.get(n) == n and len(group_members[n]) > 1:
        r['Copies of this map'] = ', '.join(str(m) for m in sorted(group_members[n]) if m != n)

    hard = any(e for e in errors)
    if hard:
        r['Race mode'] = ''
    if not hard and sps:
        cat, conf, why = categorize(f, cps_live, sps, r['Description'], rm or 'none', allv)
        r['Category guess'], r['Category confidence'], r['Category reason'] = cat, conf, why

    r['Errors'] = '; '.join(errors)
    r['Warnings'] = '; '.join(warns)
    r['Notes'] = '; '.join(notes)
    rows.append(r)

# duplicates without a title of their own inherit the first titled member of their group
byn = {r['Number']: r for r in rows}
for r in rows:
    n = r['Number']
    if r['Title'] or n not in root:
        continue
    for m in sorted(group_members[root[n]]):
        if m != n and byn[m]['Title'] and not byn[m]['Title source'].startswith('inherited'):
            r['Title'] = byn[m]['Title']
            r['Title source'] = 'inherited from copy %d' % m
            break

with open(OUT, 'w', newline='', encoding='utf-8-sig') as fh:
    w = csv.DictWriter(fh, fieldnames=COLS, extrasaction='ignore')
    w.writeheader()
    w.writerows(rows)

# summary for the console
print('rows', len(rows))
print('categories', collections.Counter(r['Category guess'] for r in rows).most_common())
print('confidence', collections.Counter(r['Category confidence'] for r in rows).most_common())
print('dups', sum(1 for r in rows if r['Duplicate']), 'groups', sum(1 for r in rows if r['Copies of this map']))
print('titled', sum(1 for r in rows if r['Title']))
ec = collections.Counter()
for r in rows:
    for e in filter(None, r['Errors'].split('; ')):
        ec[re.sub(r'\d+', 'N', e.split(' (')[0])] += 1
print('errors', ec.most_common())
wc = collections.Counter()
for r in rows:
    for e in filter(None, r['Warnings'].split('; ')):
        wc[re.sub(r'\d+', 'N', e.split(':')[0].split(' (')[0])] += 1
print('warnings', wc.most_common())
