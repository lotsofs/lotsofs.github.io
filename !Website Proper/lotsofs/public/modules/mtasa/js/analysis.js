"use strict";

/// The map's own files read and worked out in the browser, a port of the
/// owner's Python analysis (tools/mtasa/extract.py and analyze.py, described
/// in docs/mtasa-analysis.md): the elements the preview draws, and the
/// script's columns as the text analyze.py writes into classics.csv, vehicles
/// and zones by id (the page names them). JS's own sorting and rounding are
/// used (the owner's choice), so a tie in a count or a length ending in
/// exactly .5 can come out otherwise than the Python's. Nothing here touches
/// the page; js/preview.js fetches the files and shows the results.

/// The vehicle classes, as analyze.py groups the models.
const MTASA_VEHICLE_CLASSES = [
    ["plane", [460, 476, 511, 512, 513, 519, 520, 553, 577, 592, 593]],
    ["helicopter", [417, 425, 447, 469, 487, 488, 497, 548, 563]],
    ["RC", [441, 464, 465, 501, 564, 594]],
    ["boat", [430, 446, 452, 453, 454, 472, 473, 484, 493, 595, 539]],
    ["bicycle", [481, 509, 510]],
    ["motorbike", [448, 461, 462, 463, 468, 471, 521, 522, 523, 581, 586]],
    ["train", [449, 537, 538, 569, 570, 590]],
    ["trailer", [435, 450, 584, 591, 606, 607, 608, 610, 611]],
    ["monster truck", [444, 556, 557]],
    ["tank", [432]],
].map(([name, models]) => [name, new Set(models)]);

const MTASA_WORLD_EDGE = 3000;

function mtasaVehicleClass(model) {
    const found = MTASA_VEHICLE_CLASSES.find(([, models]) => models.has(model));
    return found ? found[0] : "car";
}

/// A number as the Python's float() reads it, or null.
function mtasaNumber(text) {
    if (text === null || text.trim() === "") {
        return null;
    }
    const value = Number(text.trim());
    return Number.isFinite(value) ? value : null;
}

/// A whole number as the Python's inum() reads it, from the start of the text.
function mtasaInteger(text) {
    const match = text === null ? null : /^\s*(-?\d+)/.exec(text);
    return match ? parseInt(match[1], 10) : null;
}

/// The files declare UTF-16 but are plain text, so the declaration goes before
/// parsing (PHP's parser refuses them as they are).
function mtasaParseMapXml(text, file) {
    const doc = new DOMParser().parseFromString(text.replace(/^﻿?\s*<\?xml[^>]*\?>/, ""), "application/xml");
    if (doc.getElementsByTagName("parsererror").length) {
        throw new Error(t("map.github.badXml", {file: file}));
    }
    return doc;
}

/// The .map files a meta.xml names.
function mtasaMapSources(metaDoc) {
    return Array.from(metaDoc.getElementsByTagName("map"), node => node.getAttribute("src")).filter(Boolean);
}

/// Everything a map's files hold, as extract.py reads them: meta's <info> and
/// settings, and each .map's elements, which are the root's own children.
/// A position that cannot be read is null. `maps` is [{src, text}].
function mtasaReadMapFiles(metaDoc, maps) {
    const meta = metaDoc.documentElement;
    const info = Array.from(meta.children).find(node => node.tagName === "info");
    const settings = new Map();
    Array.from(meta.getElementsByTagName("setting")).forEach(node => settings.set(node.getAttribute("name"), node.getAttribute("value")));

    const read = {
        info: info ? Object.fromEntries(Array.from(info.attributes, attribute => [attribute.name, attribute.value])) : {},
        settings: settings,
        spawns: [], checkpoints: [], objects: [], pickups: [], other: new Map(),
    };
    maps.forEach(({src, text}) => {
        Array.from(mtasaParseMapXml(text, src).documentElement.children).forEach(node => {
            const get = name => node.getAttribute(name);
            const place = {x: mtasaNumber(get("posX")), y: mtasaNumber(get("posY")), z: mtasaNumber(get("posZ"))};
            if (node.tagName === "spawnpoint") {
                read.spawns.push({...place, rotation: mtasaNumber(get("rotation")), vehicle: mtasaInteger(get("vehicle"))});
            }
            else if (node.tagName === "checkpoint") {
                read.checkpoints.push({
                    ...place, id: get("id"), next: get("nextid") || null, type: get("type"),
                    vehicle: mtasaInteger(get("vehicle")), size: mtasaNumber(get("size")), color: get("color"),
                });
            }
            else if (node.tagName === "object") {
                read.objects.push({...place, model: mtasaInteger(get("model")) || 0});
            }
            else if (node.tagName === "racepickup") {
                read.pickups.push({...place, type: get("type") || "", vehicle: mtasaInteger(get("vehicle"))});
            }
            else {
                read.other.set(node.tagName, (read.other.get(node.tagName) ?? 0) + 1);
            }
        });
    });
    return read;
}

/// The race gamemode's checkpoint chains, as race_server.lua builds them
/// (extract.py's simulate_chains()): each checkpoint joins the chain whose
/// last one leads to it, or goes before the chain it leads to, and chains it
/// joins up merge. Only the first chain is raced. A nextid that leads back
/// into its own chain hangs the race while it loads; no classic map does.
function mtasaSimulateChains(checkpoints) {
    const chains = [];
    const findLast = id => chains.findIndex(chain => chain[chain.length - 1].next !== null && chain[chain.length - 1].next === id);
    const findFirst = id => chains.findIndex(chain => chain[0].id === id);
    for (const checkpoint of checkpoints) {
        let at = findLast(checkpoint.id);
        if (at !== -1) {
            chains[at].push(checkpoint);
            if (checkpoint.next !== null) {
                const next = findFirst(checkpoint.next);
                if (next !== -1) {
                    if (next === at) {
                        return {chains, hang: true};
                    }
                    chains[at].push(...chains[next]);
                    chains.splice(next, 1);
                }
            }
        }
        else if (checkpoint.next !== null) {
            at = findFirst(checkpoint.next);
            if (at !== -1) {
                chains[at].unshift(checkpoint);
                const before = findLast(checkpoint.id);
                if (before !== -1 && before !== at) {
                    chains[before].push(...chains[at]);
                    chains.splice(at, 1);
                }
            }
            else {
                chains.push([checkpoint]);
            }
        }
        else {
            chains.push([checkpoint]);
        }
    }
    return {chains, hang: false};
}

/// The checkpoints in the order the preview draws them, as points.py wrote
/// the Mapgen tab: every chain in turn, the raced one first, then any the
/// chains did not take.
function mtasaDrawingOrder(checkpoints) {
    const seen = new Set();
    const ordered = [];
    mtasaSimulateChains(checkpoints).chains.flat().concat(checkpoints).forEach(checkpoint => {
        if (!seen.has(checkpoint)) {
            seen.add(checkpoint);
            ordered.push(checkpoint);
        }
    });
    return ordered;
}

/// The GTA zone and county of a point by label (extract.py's zone_of()), from
/// the site's zones file (tools/mtasa/site_zones.py): the smallest zone whose
/// box holds it, its height allowed 5 m under and 50 m over the box, else the
/// smallest holding it at any height, else the county; OUTSIDE past the
/// world's edge and SA where no county holds it.
function mtasaZoneOf(x, y, z, zones) {
    if (Math.abs(x) > MTASA_WORLD_EDGE || Math.abs(y) > MTASA_WORLD_EDGE) {
        return ["OUTSIDE", "OUTSIDE"];
    }
    const county = zones.counties.find(([, x1, y1, x2, y2]) => x1 <= x && x <= x2 && y1 <= y && y <= y2);
    const city = county ? county[0] : "SA";
    const inside = ([, x1, y1, , x2, y2]) => x1 <= x && x <= x2 && y1 <= y && y <= y2;
    const zone = zones.zones.find(box => inside(box) && box[3] - 5 <= z && z <= box[6] + 50)
        ?? zones.zones.find(inside);
    return [zone ? zone[0] : city, city];
}

/// Counts in first-seen order, commonest first, as [key, count] pairs.
function mtasaCommonest(keys) {
    const counts = new Map();
    keys.forEach(key => counts.set(key, (counts.get(key) ?? 0) + 1));
    return Array.from(counts).sort((a, b) => b[1] - a[1]);
}

/// The script's Start, Route, Finish, Settings and vehicle class columns of a
/// map, as the text analyze.py writes them, keyed by the site's field keys;
/// an empty column is "". Also Zones on route, which nothing stores: every
/// zone the raced checkpoints pass, in order, or for a map with none, every
/// zone holding a race pickup.
function mtasaAnalyse(read, zones) {
    const values = {};
    const {spawns, checkpoints, objects, pickups, settings} = read;
    const known = point => point.x !== null && point.y !== null && point.z !== null;
    const zonesOf = points => Array.from(new Set(points.filter(known).map(point => mtasaZoneOf(point.x, point.y, point.z, zones)[0]))).join(", ");

    /// Settings, raw from meta: a respawn that is not set reads null, "none" false.
    const setting = name => settings.get(name) ?? null;
    const respawn = setting("#respawn");
    values.respawn_mode = respawn ?? "";
    values.respawn_enabled = respawn === null ? "null" : (respawn === "none" ? "false" : "true");
    values.respawn_time = setting("#respawntime") ?? "";
    values.duration = setting("#duration") ?? "";
    values.time_of_day = setting("#time") ?? "";
    values.weather = setting("#weather") ?? "";

    /// Content.
    values.spawnpoints = String(spawns.length);
    values.objects = String(objects.length);
    values.top_object_models = mtasaCommonest(objects.map(object => object.model)).slice(0, 5)
        .map(([model, count]) => model + " x" + count).join(", ");
    values.repair_pickups = String(pickups.filter(pickup => pickup.type === "repair").length);
    values.nitro_pickups = String(pickups.filter(pickup => pickup.type === "nitro").length);
    values.vehicle_change_pickups = String(pickups.filter(pickup => pickup.type === "vehiclechange").length);

    const starts = mtasaCommonest(spawns.map(spawn => spawn.vehicle).filter(model => model !== null));
    values.start_vehicles = starts.map(([model, count]) => count > 1 ? model + " x" + count : String(model)).join(", ");
    const changeModels = pickups.filter(pickup => pickup.type === "vehiclechange" && pickup.vehicle !== null).map(pickup => pickup.vehicle)
        .concat(checkpoints.filter(checkpoint => checkpoint.vehicle !== null).map(checkpoint => checkpoint.vehicle));
    const changes = mtasaCommonest(changeModels);
    values.vehicles_via_changes = changes.map(([model]) => String(model)).join(", ");
    const everyModel = new Set([...starts.map(([model]) => model), ...changes.map(([model]) => model)]);
    values.vehicle_classes = Array.from(new Set(Array.from(everyModel).filter(model => model >= 400 && model <= 611).map(mtasaVehicleClass))).sort().join(", ");

    /// Route: every checkpoint counts, but the length, the finish and the zones on route
    /// are the raced chain's, from the middle of the spawn grid.
    const {chains, hang} = mtasaSimulateChains(checkpoints);
    const used = chains.length && !hang ? chains[0] : [];
    values.checkpoints = String(checkpoints.length);
    values.checkpoints_used = String(hang ? 0 : used.length);
    values.ring_checkpoints = String(checkpoints.filter(checkpoint => checkpoint.type === "ring").length);
    values.checkpoint_vehicle_changes = String(checkpoints.filter(checkpoint => checkpoint.vehicle !== null).length);

    for (const key of ["returns_to_start", "route_length", "route_length_2d", "finish_zone", "start_zone"]) {
        values[key] = "";
    }
    const placed = spawns.filter(known);
    if (placed.length && used.length && !hang) {
        const middle = ["x", "y", "z"].map(axis => placed.reduce((sum, spawn) => sum + spawn[axis], 0) / placed.length);
        const route = used.filter(known);
        let length = 0;
        let flat = 0;
        let [x, y, z] = middle;
        route.forEach(checkpoint => {
            length += Math.hypot(checkpoint.x - x, checkpoint.y - y, checkpoint.z - z);
            flat += Math.hypot(checkpoint.x - x, checkpoint.y - y);
            ({x, y, z} = checkpoint);
        });
        /// Two broken maps measure 38 digits long, which String() would write
        /// in exponent form; BigInt writes the whole number, as the Python did.
        const whole = metres => Number.isSafeInteger(Math.round(metres)) ? String(Math.round(metres)) : BigInt(Math.round(metres)).toString();
        values.route_length = whole(length);
        values.route_length_2d = whole(flat);
        const finish = route[route.length - 1];
        values.returns_to_start = finish && Math.hypot(finish.x - middle[0], finish.y - middle[1]) < 75 ? "yes" : "no";

        const last = used[used.length - 1];
        values.finish_zone = known(last) ? mtasaZoneOf(last.x, last.y, last.z, zones)[0] : "";
    }
    /// Every zone the spawnpoints are in, commonest first (the owner's wish).
    values.start_zone = mtasaCommonest(placed.map(spawn => mtasaZoneOf(spawn.x, spawn.y, spawn.z, zones)[0])).map(([label]) => label).join(", ");
    values.zones_on_route = zonesOf(used.length && !hang ? used : pickups);

    return {values};
}

/// How near two points must be, in metres, to count as the same point: the
/// value that matches the script's own percentages best.
const MTASA_COMPARE_TOLERANCE = 4;

/// How much of two lists of points is the same, as a whole percentage: every
/// point on either side with a point on the other within the tolerance (a
/// pickup only one of its own type, `typed`, its third value), over both lists
/// together. That is the script's own measure as near as it can be read back:
/// fitted against the 5,246 shares the script gave in the real sheet, it lands
/// within a point of 95% of them (2026-11-17). Null when neither has any. It
/// ran in PHP over the stored points until those were cut to the middle of the
/// grid and the checkpoints (2026-11-18); it now runs on both maps' files.
function mtasaComparePoints(a, b, typed) {
    const total = a.length + b.length;
    if (!total) {
        return null;
    }
    const near = (points, others) => {
        const cell = point => [Math.floor(point[0] / MTASA_COMPARE_TOLERANCE), Math.floor(point[1] / MTASA_COMPARE_TOLERANCE)];
        const grid = new Map();
        others.forEach(other => {
            const key = cell(other).join(",");
            grid.has(key) ? grid.get(key).push(other) : grid.set(key, [other]);
        });
        return points.filter(point => {
            const [cx, cy] = cell(point);
            for (const dx of [-1, 0, 1]) {
                for (const dy of [-1, 0, 1]) {
                    for (const other of grid.get((cx + dx) + "," + (cy + dy)) ?? []) {
                        if ((!typed || point[2] === other[2]) && Math.hypot(point[0] - other[0], point[1] - other[1]) <= MTASA_COMPARE_TOLERANCE) {
                            return true;
                        }
                    }
                }
            }
            return false;
        }).length;
    };
    return Math.floor(100 * (near(a, b) + near(b, a)) / total);
}

/// A map's points for the comparison: spawnpoints and checkpoints as [x, y],
/// pickups as [x, y, type], whatever lacks a position left out.
function mtasaComparablePoints(read) {
    const placed = point => point.x !== null && point.y !== null;
    return {
        checkpoints: read.checkpoints.filter(placed).map(point => [point.x, point.y]),
        spawnpoints: read.spawns.filter(placed).map(point => [point.x, point.y]),
        pickups: read.pickups.filter(placed).map(point => [point.x, point.y, point.type]),
    };
}

/// The route's height profile (the owner's wish): the middle of the spawn grid,
/// then every raced checkpoint, each with its height and how far along the
/// route it is, measured flat, as Route length 2D is. Checkpoints whose
/// position cannot be read are left out. Null for a map with no spawnpoints or
/// no raced checkpoints, which has no route to profile.
function mtasaRouteProfile(read) {
    const known = point => point.x !== null && point.y !== null && point.z !== null;
    const placed = read.spawns.filter(known);
    const {chains, hang} = mtasaSimulateChains(read.checkpoints);
    const used = chains.length && !hang ? chains[0].filter(known) : [];
    if (!placed.length || !used.length) {
        return null;
    }
    const [x, y, z] = ["x", "y", "z"].map(axis => placed.reduce((sum, spawn) => sum + spawn[axis], 0) / placed.length);
    const points = [{start: true, distance: 0, x, y, z}];
    used.forEach((checkpoint, i) => {
        const before = points[points.length - 1];
        points.push({number: i + 1, count: used.length, checkpoint: checkpoint, distance: before.distance + Math.hypot(checkpoint.x - before.x, checkpoint.y - before.y), x: checkpoint.x, y: checkpoint.y, z: checkpoint.z});
    });
    return points;
}

/// A value as the site stores it, from the text the Python or this file
/// writes, as fields.php's mtasaFieldValue() reads it: null for nothing, a
/// number, 1/0 for a yes or no, or the text.
function mtasaStoredForm(type, text) {
    const value = String(text ?? "").trim();
    if (value === "") {
        return null;
    }
    if ((type === "int" || type === "km" || type === "map") && /^-?\d+$/.test(value)) {
        return value.replace(/^-/, "").length <= 18 ? Number(value) : value;
    }
    if (type === "bool") {
        const lower = value.toLowerCase();
        return ["true", "yes", "1"].includes(lower) ? 1 : (["false", "no", "0"].includes(lower) ? 0 : null);
    }
    return value;
}

/// Whether what GitHub gives for a field is what the site has stored, both
/// read as the import stores them.
function mtasaSameAsStored(type, github, stored) {
    return String(mtasaStoredForm(type, github)) === String(mtasaStoredForm(type, stored === null ? "" : String(stored)));
}
