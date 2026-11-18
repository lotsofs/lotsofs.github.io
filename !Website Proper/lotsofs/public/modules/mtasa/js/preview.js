"use strict";

/// The route preview as the reader asks for it (the settings beside it): the
/// page's own picture is the server's, and this draws it again, first from the
/// sheet's points (#previewData) and, on Increase detail, from the map's own
/// files on GitHub, fetched in the reader's browser, with its objects added and
/// more in every hover box. Nothing is sent to the server or kept on it; the
/// settings are kept in this browser. The drawing rules are preview.php's,
/// copied, at the default settings: a change to one goes in both.

const MTASA_SVG = "http://www.w3.org/2000/svg";
const MTASA_PREVIEW_SIZE = 720;
const MTASA_PREVIEW_MARGIN = 28;
const MTASA_PREVIEW_MIN_SPAN = 80;
const MTASA_PREVIEW_WORLD = 3000;
const MTASA_PREVIEW_SEA = "#3d6068";
const MTASA_PREVIEW_DEFAULT_COLOUR = "#0000FE";
/// Nitro red, repair lime, vehicle change cyan (the owner's choice, 2026-11-18); anything else red.
const MTASA_PREVIEW_PICKUP_COLOURS = {nitro: "#ff2a2a", repair: "#b4ff2a", vehiclechange: "#2ad4ff"};
/// How many of a stack's models its hover box names, the commonest first.
const MTASA_PREVIEW_STACK_MODELS = 5;
const MTASA_PREVIEW_SETTINGS_KEY = "mtasaPreviewSettings";
/// Rainbow runs from red, 0°, to the finish's #7F00FF, 270°, with a stop on
/// a line at most this many degrees apart.
const MTASA_PREVIEW_RAINBOW_END = 270;
const MTASA_PREVIEW_RAINBOW_STEP = 30;

/// A number to one decimal, without a trailing .0.
function mtasaPreviewNumber(value) {
    return String(Math.round(value * 10) / 10);
}

/// A fully saturated, full-value hue as #rrggbb, its channels rounded down,
/// so Rainbow's last hue, 270°, is the owner's #7F00FF rather than #8000FF.
function mtasaPreviewHue(hue) {
    const channel = offset => {
        const k = (offset + hue / 60) % 6;
        return Math.floor(255 * (1 - Math.max(0, Math.min(k, 4 - k, 1))));
    };
    return "#" + [5, 3, 1].map(offset => channel(offset).toString(16).padStart(2, "0")).join("");
}

/// Black on a light fill and white on a dark one, as preview.php.
function mtasaPreviewStroke(colour) {
    const [r, g, b] = [1, 3, 5].map(i => parseInt(colour.substr(i, 2), 16));
    return 0.299 * r + 0.587 * g + 0.114 * b > 90 ? "#000" : "#fff";
}

/// A file's bytes as GitHub serves them, kept for the zip download.
async function mtasaFetchBytes(url) {
    const response = await fetch(url, {credentials: "omit", referrerPolicy: "no-referrer"});
    if (!response.ok) {
        throw new Error(url.split("/").pop() + ": HTTP " + response.status);
    }
    return new Uint8Array(await response.arrayBuffer());
}

/// A map's own files, fetched from GitHub and read by js/analysis.js: what it
/// read, and the elements in the shapes the drawing takes. Each map names one
/// .map in its meta.xml (all 6,504, 2026-11-17), but every one named is read.
/// Elements whose position cannot be read are left out, as points.py did;
/// a spawnpoint with no rotation faces 0, and a checkpoint colour that is not
/// #rrggbb (or #rrggbbaa) is the default. `files` keeps every file's bytes, for
/// the zip download.
async function mtasaLoadMapFiles(base) {
    const decoder = new TextDecoder();
    const metaBytes = await mtasaFetchBytes(base + "meta.xml");
    const metaDoc = mtasaParseMapXml(decoder.decode(metaBytes), "meta.xml");
    const sources = mtasaMapSources(metaDoc);
    if (!sources.length) {
        throw new Error(t("map.github.noMap"));
    }
    const fetched = await Promise.all(sources.map(async src => ({src, bytes: await mtasaFetchBytes(base + src.split("/").map(encodeURIComponent).join("/"))})));
    const maps = fetched.map(({src, bytes}) => ({src, text: decoder.decode(bytes)}));
    const read = mtasaReadMapFiles(metaDoc, maps);
    const placed = point => point.x !== null && point.y !== null;
    const colourOf = raw => {
        const colour = (raw ?? "").trim();
        return /^#[0-9a-f]{6}([0-9a-f]{2})?$/i.test(colour) ? colour.substr(0, 7).toLowerCase() : MTASA_PREVIEW_DEFAULT_COLOUR;
    };
    return {
        read: read,
        files: [{name: "meta.xml", bytes: metaBytes}, ...fetched.map(({src, bytes}) => ({name: src, bytes}))],
        spawns: read.spawns.filter(placed).map(spawn => ({...spawn, rotation: spawn.rotation ?? 0})),
        checkpoints: mtasaDrawingOrder(read.checkpoints).filter(placed).map(checkpoint => ({...checkpoint, colour: colourOf(checkpoint.color), source: checkpoint})),
        pickups: read.pickups.filter(placed),
        objects: read.objects.filter(placed),
    };
}

function mtasaSvgElement(name, attributes, tooltip) {
    const element = document.createElementNS(MTASA_SVG, name);
    Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, value));
    if (tooltip) {
        element.dataset.tooltip = tooltip;
    }
    return element;
}

/// The sheet's points (preview.php's mtasaPreviewData()) in the shapes the
/// GitHub load gives, without what the sheet does not have.
function mtasaSheetData(sheet) {
    return {
        start: sheet.start ? {x: sheet.start[0], y: sheet.start[1]} : null,
        radius: sheet.radius ?? null,
        spawns: [],
        checkpoints: sheet.checkpoints.map(([x, y]) => ({x, y, colour: MTASA_PREVIEW_DEFAULT_COLOUR})),
        pickups: [],
        objects: [],
        detailed: false,
    };
}

/// Where the route starts: the middle of the spawn grid, from every
/// spawnpoint once Increase detail has them, else the one point stored.
function mtasaRouteStart(data) {
    if (data.spawns.length) {
        return {
            x: data.spawns.reduce((sum, spawn) => sum + spawn.x, 0) / data.spawns.length,
            y: data.spawns.reduce((sum, spawn) => sum + spawn.y, 0) / data.spawns.length,
        };
    }
    return data.start ?? null;
}

/// A map with no checkpoints, a derby or freeroam, has its arena ringed (the
/// owner's wish): round the middle of the spawn grid, as far as the furthest
/// spawnpoint, points.py's stored radius. Only until Increase detail draws the
/// spawnpoints themselves, which show the arena better (the owner's call), and
/// never for a race.
function mtasaArenaRadius(data) {
    return data.checkpoints.length || data.spawns.length ? null : data.radius ?? null;
}

/// The route's colours under the settings, for the map and the height profile
/// alike: `colours[i]`, checkpoint i's own colour, all blue, or under Rainbow
/// (the owner's wish) a hue from red at the first to #7F00FF at the finish,
/// evenly by checkpoint; and `stopsInto(i)`, the colours the line from
/// checkpoint i - 1 to i runs through, one unless it fades (Map colours
/// (gradient) between two colours, Rainbow always).
function mtasaRouteColours(checkpoints, settings) {
    const lastIndex = Math.max(checkpoints.length - 1, 1);
    const hueOf = i => MTASA_PREVIEW_RAINBOW_END * i / lastIndex;
    const colours = checkpoints.map((checkpoint, i) => {
        if (settings.colours === "blue") {
            return MTASA_PREVIEW_DEFAULT_COLOUR;
        }
        return settings.colours === "rainbow" ? mtasaPreviewHue(hueOf(i)) : checkpoint.colour;
    });
    const stopsInto = i => {
        if (settings.colours === "rainbow") {
            const [from, to] = [hueOf(i - 1), hueOf(i)];
            const steps = Math.max(1, Math.ceil((to - from) / MTASA_PREVIEW_RAINBOW_STEP));
            return Array.from({length: steps + 1}, (_, k) => mtasaPreviewHue(from + (to - from) * k / steps));
        }
        if (settings.colours === "gradient" && colours[i - 1].toLowerCase() !== colours[i].toLowerCase()) {
            return [colours[i - 1], colours[i]];
        }
        return [colours[i]];
    };
    return {colours, stopsInto};
}

/// Draws the whole picture: the frame, then the layers bottom to top, each
/// sized by the settings. Returns how many objects and pickups fall outside.
function mtasaDrawPreview(svg, data, settings) {
    const half = MTASA_PREVIEW_SIZE / 2;
    const n = mtasaPreviewNumber;
    const image = svg.querySelector("#previewBackdrop image")?.getAttribute("href") ?? svg.dataset.image;
    svg.dataset.image = image;

    /// Track frames the spawnpoints and checkpoints, or everything when there
    /// is no track; All elements frames the pickups and objects too.
    const start = mtasaRouteStart(data);
    const radius = start ? mtasaArenaRadius(data) : null;
    /// The arena is framed whole, as preview.php frames it.
    const arenaBox = radius !== null ? [{x: start.x - radius, y: start.y - radius}, {x: start.x + radius, y: start.y + radius}] : [];
    const track = [...data.spawns, ...data.checkpoints, ...(data.spawns.length || !start ? [] : [start]), ...arenaBox];
    const everything = [...track, ...data.pickups, ...data.objects];
    const framed = settings.frame === "track" && track.length ? track : everything;
    const xs = framed.map(point => point.x);
    const ys = framed.map(point => point.y);
    const [minX, maxX, minY, maxY] = [Math.min(...xs), Math.max(...xs), Math.min(...ys), Math.max(...ys)];
    const cx = (minX + maxX) / 2;
    const cy = (minY + maxY) / 2;
    const scale = (MTASA_PREVIEW_SIZE - 2 * MTASA_PREVIEW_MARGIN) / Math.max(maxX - minX, maxY - minY, MTASA_PREVIEW_MIN_SPAN);
    const px = x => half + (x - cx) * scale;
    const py = y => half - (y - cy) * scale;
    const outside = point => {
        const [x, y] = [px(point.x), py(point.y)];
        return x < 0 || y < 0 || x > MTASA_PREVIEW_SIZE || y > MTASA_PREVIEW_SIZE;
    };
    const height = point => Number.isFinite(point.z) ? [t("map.github.height", {height: n(point.z)})] : [];
    const vehicle = mtasaVehicleName;
    const {colours, stopsInto} = mtasaRouteColours(data.checkpoints, settings);

    const backdrop = mtasaSvgElement("g", {id: "previewBackdrop"});
    backdrop.appendChild(mtasaSvgElement("rect", {width: MTASA_PREVIEW_SIZE, height: MTASA_PREVIEW_SIZE, fill: MTASA_PREVIEW_SEA}));
    const world = n(2 * MTASA_PREVIEW_WORLD * scale);
    backdrop.appendChild(mtasaSvgElement("image", {href: image, x: n(px(-MTASA_PREVIEW_WORLD)), y: n(py(MTASA_PREVIEW_WORLD)), width: world, height: world}));

    /// The arena of a map with no checkpoints, white so it reads on land and sea.
    const arena = mtasaSvgElement("g", {id: "previewArena", fill: "#ffffff", "fill-opacity": "0.1", stroke: "#ffffff", "stroke-opacity": "0.7", "stroke-width": "2"});
    if (radius !== null) {
        arena.appendChild(mtasaSvgElement("circle", {cx: n(px(start.x)), cy: n(py(start.y)), r: n(radius * scale)}));
    }

    /// Solid black with a thin white edge (the owner's call: yellow was tried,
    /// when the dots could not be enlarged; the slider can make them bigger now).
    /// Objects on one spot are one dot, bigger, saying how many: z(6704)
    /// stacks 2,462 of its 2,472 into one tower.
    const objects = mtasaSvgElement("g", {id: "previewObjects", fill: "#000000", stroke: "#ffffff", "stroke-width": "0.6"});
    if (settings.objects > 0) {
        const spots = new Map();
        data.objects.forEach(object => {
            const key = n(px(object.x)) + "," + n(py(object.y));
            spots.has(key) ? spots.get(key).push(object) : spots.set(key, [object]);
        });
        spots.forEach((stack, key) => {
            const [x, y] = key.split(",");
            let tooltip;
            if (stack.length === 1) {
                tooltip = [t("map.github.object", {model: stack[0].model}), ...height(stack[0])];
            }
            else {
                const models = new Map();
                stack.forEach(object => models.set(object.model, (models.get(object.model) ?? 0) + 1));
                const listed = Array.from(models).sort((a, b) => b[1] - a[1]).slice(0, MTASA_PREVIEW_STACK_MODELS)
                    .map(([model, times]) => times > 1 ? model + " ×" + times : model);
                const heights = stack.map(object => object.z);
                tooltip = [
                    t("map.github.stack", {count: stack.length}),
                    t(models.size > MTASA_PREVIEW_STACK_MODELS ? "map.github.stackModelsMore" : "map.github.stackModels", {models: listed.join(", "), more: models.size - MTASA_PREVIEW_STACK_MODELS}),
                    t("map.github.heights", {low: n(Math.min(...heights)), high: n(Math.max(...heights))}),
                ];
            }
            objects.appendChild(mtasaSvgElement("circle", {cx: x, cy: y, r: n(settings.objects + (stack.length === 1 ? 0 : 2))}, tooltip.join("\n")));
        });
    }

    /// Each link takes the colour of the checkpoint it leads to; the spawn line
    /// runs from the middle of the grid to checkpoint 1, in its colour. Under
    /// Map colours (gradient), a link between two colours fades from the one
    /// before, a setting since the owner prefers it without; under Rainbow
    /// every link fades along the hues between its two checkpoints, a stop at
    /// most 30° apart, since a straight fade between far hues goes muddy (red
    /// to green through brown). A fade is a gradient laid along the line itself
    /// (userSpaceOnUse): one sized to the line's box draws nothing on a level
    /// or upright line, whose box is flat.
    const route = mtasaSvgElement("g", {id: "previewRoute", "stroke-width": n(settings.lines), "stroke-linecap": "round", fill: "none"});
    const fades = mtasaSvgElement("defs", {});
    const link = (from, to, stops) => {
        const ends = {x1: n(px(from.x)), y1: n(py(from.y)), x2: n(px(to.x)), y2: n(py(to.y))};
        let stroke = stops[stops.length - 1];
        if (stops.length > 1) {
            const id = "previewFade" + fades.children.length;
            const fade = mtasaSvgElement("linearGradient", {id: id, gradientUnits: "userSpaceOnUse", ...ends});
            stops.forEach((colour, k) => fade.appendChild(mtasaSvgElement("stop", {offset: n(k / (stops.length - 1)), "stop-color": colour})));
            fades.appendChild(fade);
            stroke = "url(#" + id + ")";
        }
        route.appendChild(mtasaSvgElement("line", {...ends, stroke: stroke}));
    };
    if (settings.lines > 0) {
        if (start && data.checkpoints.length) {
            link(start, data.checkpoints[0], [colours[0]]);
        }
        data.checkpoints.forEach((checkpoint, i) => i > 0 && link(data.checkpoints[i - 1], checkpoint, stopsInto(i)));
    }
    if (fades.children.length) {
        route.prepend(fades);
    }

    const pickups = mtasaSvgElement("g", {id: "previewPickups", stroke: "#000", "stroke-width": "1"});
    const side = settings.pickups;
    if (side > 0) {
        data.pickups.forEach(pickup => {
            const fill = MTASA_PREVIEW_PICKUP_COLOURS[pickup.type] ?? MTASA_PREVIEW_PICKUP_COLOURS.nitro;
            const known = ["repair", "nitro", "vehiclechange"].includes(pickup.type);
            const lines = [known ? t("map.github.pickup." + pickup.type) : t("map.github.pickup.other", {type: pickup.type})];
            if (Number.isInteger(pickup.vehicle)) {
                lines.push(t("map.github.vehicleChange", {vehicle: vehicle(pickup.vehicle)}));
            }
            lines.push(...height(pickup));
            pickups.appendChild(mtasaSvgElement("rect", {x: n(px(pickup.x) - side / 2), y: n(py(pickup.y) - side / 2), width: n(side), height: n(side), fill: fill}, lines.join("\n")));
        });
    }

    const checkpoints = mtasaSvgElement("g", {id: "previewCheckpoints", "stroke-width": "1.2"});
    const count = data.checkpoints.length;
    if (settings.checkpoints > 0) {
        data.checkpoints.forEach((checkpoint, i) => {
            const lines = [t(i === count - 1 ? "map.github.finish" : "map.github.checkpoint", {number: i + 1, count: count})];
            if (checkpoint.type === "ring") {
                lines.push(t("map.github.ring"));
            }
            if (Number.isFinite(checkpoint.size)) {
                lines.push(t("map.github.size", {size: String(checkpoint.size)}));
            }
            if (Number.isInteger(checkpoint.vehicle)) {
                lines.push(t("map.github.vehicleChange", {vehicle: vehicle(checkpoint.vehicle)}));
            }
            lines.push(...height(checkpoint));
            const fill = colours[i];
            checkpoints.appendChild(mtasaSvgElement("circle", {
                cx: n(px(checkpoint.x)), cy: n(py(checkpoint.y)), r: n(settings.checkpoints + (i === count - 1 ? 3 : 0)),
                fill: fill, stroke: mtasaPreviewStroke(fill),
            }, lines.join("\n")));
        });
    }

    /// GTA turns anticlockwise from north and SVG clockwise, hence the minus.
    /// The arrow is drawn for a dot of radius 3 and scaled with it.
    const spawns = mtasaSvgElement("g", {id: "previewSpawnpoints", fill: "#fff", stroke: "#000", "stroke-width": "1", "stroke-linejoin": "round"});
    const grow = settings.spawnpoints / 3;
    if (grow > 0) {
        data.spawns.forEach((spawn, i) => {
            const lines = [t("map.github.spawnpoint", {number: i + 1, count: data.spawns.length})];
            if (Number.isInteger(spawn.vehicle)) {
                lines.push(t("map.github.vehicle", {vehicle: vehicle(spawn.vehicle)}));
            }
            lines.push(t("map.github.facing", {degrees: n(((spawn.rotation % 360) + 360) % 360)}), ...height(spawn));
            const arrow = mtasaSvgElement("g", {transform: "translate(" + n(px(spawn.x)) + " " + n(py(spawn.y)) + ") rotate(" + n(-spawn.rotation) + ")"}, lines.join("\n"));
            arrow.appendChild(mtasaSvgElement("path", {d: "M0," + n(-9.5 * grow) + " L" + n(3.6 * grow) + "," + n(-2.4 * grow) + " L" + n(-3.6 * grow) + "," + n(-2.4 * grow) + " Z"}));
            arrow.appendChild(mtasaSvgElement("circle", {r: n(settings.spawnpoints)}));
            spawns.appendChild(arrow);
        });
    }

    /// The route over the pickups (the owner's wish), under the checkpoints.
    svg.replaceChildren(backdrop, arena, objects, pickups, route, checkpoints, spawns);
    return {
        objects: data.objects.filter(outside).length,
        pickups: data.pickups.filter(outside).length,
    };
}

/// The settings as the form holds them, numbers for the sliders.
function mtasaPreviewSettingsOf(form) {
    const settings = {always: form.elements.always.checked, colours: form.elements.colours.value, frame: form.elements.frame.value};
    form.querySelectorAll("input[type=range]").forEach(slider => settings[slider.name] = parseFloat(slider.value));
    return settings;
}

/// Per-viewer conveniences in this browser, so a refused storage only means
/// the defaults.
function mtasaReadPreviewSettings() {
    try {
        return JSON.parse(localStorage.getItem(MTASA_PREVIEW_SETTINGS_KEY)) ?? {};
    }
    catch (error) {
        return {};
    }
}

function mtasaWritePreviewSettings(settings) {
    try {
        localStorage.setItem(MTASA_PREVIEW_SETTINGS_KEY, JSON.stringify(settings));
    }
    catch (error) {
        /// Nothing kept, nothing lost: the page still works.
    }
}

/// Puts stored settings into the form, each only where the form can hold it,
/// so a stale or tampered value leaves that setting at its default.
function mtasaApplyPreviewSettings(form, stored) {
    if (typeof stored.always === "boolean") {
        form.elements.always.checked = stored.always;
    }
    ["colours", "frame"].forEach(name => {
        const select = form.elements[name];
        if (Array.from(select.options).some(option => option.value === stored[name])) {
            select.value = stored[name];
        }
    });
    form.querySelectorAll("input[type=range]").forEach(slider => {
        const value = stored[slider.name];
        if (typeof value === "number" && value >= parseFloat(slider.min) && value <= parseFloat(slider.max)) {
            slider.value = value;
        }
        slider.nextElementSibling.textContent = slider.value;
    });
}

/// The vehicle names, fields.php's one list, which the map page carries.
const mtasaVehicleNames = JSON.parse(document.getElementById("mtasaVehicleNames")?.textContent ?? "[]");

function mtasaVehicleName(model) {
    return mtasaVehicleNames[model - 400] ?? t("map.github.unknownVehicle", {model: model});
}

/// A field's value as the page shows it, as fields.php's mtasaFieldText() and
/// the view's ring checkpoints do: vehicles and zones by name, a length in
/// kilometres, ring checkpoints with their share. `zoneNames` is the zones
/// file's label → name.
function mtasaFieldDisplay(key, type, value, checkpoints, zoneNames) {
    if (value === null) {
        return "";
    }
    if (key === "ring_checkpoints") {
        return checkpoints > 0 ? value + " (" + Math.round(100 * value / checkpoints) + "%)" : String(value);
    }
    if (type === "bool") {
        return t(value === 1 ? "value.yes" : "value.no");
    }
    if (type === "km") {
        return (Number(value) / 1000).toFixed(3) + "km";
    }
    if (type === "vehicles") {
        const parts = String(value).split(", ").map(part => {
            const [model, count] = part.split(" x");
            return mtasaVehicleName(parseInt(model, 10)) + (count !== undefined ? " x" + count : "");
        });
        return parts.join(", ");
    }
    if (type === "zones") {
        return Array.from(new Set(String(value).split(", ").map(label => zoneNames[label] ?? label))).join(", ");
    }
    return String(value);
}

/// The height profile's frame, in output pixels like the picture above it,
/// and how many gridlines each axis aims for.
const MTASA_PROFILE = {width: 720, height: 200, left: 52, right: 14, top: 12, bottom: 28, xTicks: 6, yTicks: 4};
/// Past this many checkpoints the line carries no dots, which would only blot it.
const MTASA_PROFILE_DOTS = 120;

/// A round step (1, 2 or 5 times a power of ten) giving about `count` steps over `range`.
function mtasaNiceStep(range, count) {
    const rough = range / count;
    const power = Math.pow(10, Math.floor(Math.log10(rough)));
    return [1, 2, 5, 10].map(m => m * power).find(step => range / step <= count) ?? 10 * power;
}

/// The route's height profile (the owner's wish), under the picture: height
/// against the distance along the route, measured flat, from the middle of
/// the spawn grid through every raced checkpoint. One series, so a title and
/// no legend; hairline gridlines; a 2px line over a faint neutral fill, each
/// stretch in the colours of the map's line for it (the owner's wish), as the
/// Checkpoints setting has them, its dot in its checkpoint's, the start's
/// white as the spawn arrows are. `drawn` is the map's checkpoints, which
/// carry the read ones as `source`. Each point has a hover area reaching
/// halfway to its neighbours, which shows a crosshair, the point, and its
/// values in the page's hover box.
function mtasaDrawProfile(box, points, drawn, settings) {
    const P = MTASA_PROFILE;
    const n = mtasaPreviewNumber;
    const plotWidth = P.width - P.left - P.right;
    const plotHeight = P.height - P.top - P.bottom;
    const total = points[points.length - 1].distance;
    const heights = points.map(point => point.z);
    let [low, high] = [Math.min(...heights), Math.max(...heights)];
    if (high - low < 10) {
        [low, high] = [(low + high) / 2 - 5, (low + high) / 2 + 5];
    }
    const yStep = mtasaNiceStep(high - low, P.yTicks);
    const [yMin, yMax] = [Math.floor(low / yStep) * yStep, Math.ceil(high / yStep) * yStep];
    const xStep = mtasaNiceStep(Math.max(total, 1), P.xTicks);
    const px = distance => P.left + (total > 0 ? distance / total : 0) * plotWidth;
    const py = z => P.top + (1 - (z - yMin) / (yMax - yMin)) * plotHeight;
    const bottom = P.top + plotHeight;
    const decimals = step => Math.max(0, -Math.floor(Math.log10(step)));
    const km = metres => (metres / 1000).toFixed(Math.max(decimals(xStep / 1000), 0)) + " km";

    const svg = mtasaSvgElement("svg", {
        class: "mtasaProfileChart", xmlns: MTASA_SVG, width: P.width, height: P.height, viewBox: "0 0 " + P.width + " " + P.height, role: "img",
        "aria-label": t("map.profile.label", {low: n(Math.min(...heights)), high: n(Math.max(...heights)), length: (total / 1000).toFixed(3) + "km"}),
    });
    const grid = mtasaSvgElement("g", {class: "mtasaProfileGrid"});
    const labels = mtasaSvgElement("g", {class: "mtasaProfileLabels"});
    for (let z = yMin; z <= yMax + yStep / 2; z += yStep) {
        grid.appendChild(mtasaSvgElement("line", {x1: P.left, x2: P.width - P.right, y1: n(py(z)), y2: n(py(z))}));
        const label = mtasaSvgElement("text", {x: P.left - 6, y: n(py(z) + 4), "text-anchor": "end"});
        label.textContent = z.toFixed(decimals(yStep)) + " m";
        labels.appendChild(label);
    }
    for (let d = 0; d <= total + xStep / 2 && total > 0; d += xStep) {
        if (d > total) {
            break;
        }
        grid.appendChild(mtasaSvgElement("line", {x1: n(px(d)), x2: n(px(d)), y1: P.top, y2: bottom}));
        const label = mtasaSvgElement("text", {x: n(px(d)), y: P.height - 8, "text-anchor": d === 0 ? "start" : "middle"});
        label.textContent = km(d);
        labels.appendChild(label);
    }

    const outline = points.map((point, i) => (i ? "L" : "M") + n(px(point.distance)) + "," + n(py(point.z))).join(" ");
    const fill = outline + " L" + n(px(total)) + "," + bottom + " L" + n(px(0)) + "," + bottom + " Z";

    /// Where each point's checkpoint is among the map's, for its colours.
    const {colours, stopsInto} = mtasaRouteColours(drawn, settings);
    const at = new Map(drawn.map((checkpoint, i) => [checkpoint.source, i]));
    const indexOf = point => point.start ? null : at.get(point.checkpoint) ?? null;
    const dotColour = point => point.start || indexOf(point) === null ? "#ffffff" : colours[indexOf(point)];
    const line = mtasaSvgElement("g", {class: "mtasaProfileLine"});
    const fades = mtasaSvgElement("defs", {});
    points.forEach((point, i) => {
        if (!i) {
            return;
        }
        const index = indexOf(point);
        const before = indexOf(points[i - 1]);
        const stops = index === null ? ["#ffffff"] : (before === index - 1 ? stopsInto(index) : [colours[index]]);
        const ends = {x1: n(px(points[i - 1].distance)), y1: n(py(points[i - 1].z)), x2: n(px(point.distance)), y2: n(py(point.z))};
        let stroke = stops[stops.length - 1];
        if (stops.length > 1) {
            const id = "profileFade" + fades.children.length;
            const fade = mtasaSvgElement("linearGradient", {id: id, gradientUnits: "userSpaceOnUse", ...ends});
            stops.forEach((colour, k) => fade.appendChild(mtasaSvgElement("stop", {offset: n(k / (stops.length - 1)), "stop-color": colour})));
            fades.appendChild(fade);
            stroke = "url(#" + id + ")";
        }
        line.appendChild(mtasaSvgElement("line", {...ends, stroke: stroke}));
    });
    if (fades.children.length) {
        line.prepend(fades);
    }
    svg.append(grid, labels, mtasaSvgElement("path", {class: "mtasaProfileFill", d: fill}), line);
    if (points.length <= MTASA_PROFILE_DOTS) {
        const dots = mtasaSvgElement("g", {class: "mtasaProfileDots"});
        points.forEach(point => dots.appendChild(mtasaSvgElement("circle", {cx: n(px(point.distance)), cy: n(py(point.z)), r: point.start || point.number === point.count ? 5 : 4, fill: dotColour(point)})));
        svg.appendChild(dots);
    }

    const hits = mtasaSvgElement("g", {class: "mtasaProfileHits"});
    points.forEach((point, i) => {
        const x = px(point.distance);
        const from = i ? (px(points[i - 1].distance) + x) / 2 : P.left;
        const to = i < points.length - 1 ? (x + px(points[i + 1].distance)) / 2 : P.width - P.right;
        const name = point.start ? t("map.profile.start") : t(point.number === point.count ? "map.github.finish" : "map.github.checkpoint", {number: point.number, count: point.count});
        const hit = mtasaSvgElement("g", {class: "mtasaProfileHit"}, [name, t("map.profile.along", {distance: (point.distance / 1000).toFixed(3) + "km"}), t("map.github.height", {height: n(point.z)})].join("\n"));
        hit.append(
            mtasaSvgElement("rect", {x: n(from), y: P.top, width: n(Math.max(to - from, 1)), height: plotHeight}),
            mtasaSvgElement("line", {x1: n(x), x2: n(x), y1: P.top, y2: bottom}),
            mtasaSvgElement("circle", {cx: n(x), cy: n(py(point.z)), r: 5, fill: dotColour(point)}),
        );
        hits.appendChild(hit);
    });
    svg.appendChild(hits);

    const title = document.createElement("h3");
    title.textContent = t("map.profile.title");
    box.replaceChildren(title, svg);
    box.hidden = false;
}

/// How many maps the comparison fetches at once: a set holds 2 maps at the
/// median and 33 at most (2026-11-18), two small files each.
const MTASA_COMPARE_AT_ONCE = 4;

/// The site's own differences for each confirmed copy in the set (the copies
/// table's `data-compare` cells), finished on Increase detail (the owner's
/// choice, 2026-11-18): the points are compared from both maps' files on
/// GitHub, which are fetched a few at a time, the current map's being already
/// in hand; the rest the stored analysis said. The cell then reads as the
/// server's used to, the points' words first, with the same hover box: the
/// rows as a small table, or "N and M are identical".
async function mtasaCompareCopies(currentId, currentRead) {
    const cells = Array.from(document.querySelectorAll(".mtasaOwnDifferences[data-compare]"));
    if (!cells.length) {
        return;
    }
    const root = mtasaPreviewFigure.dataset.githubRoot;
    const reads = new Map([[currentId, Promise.resolve(currentRead)]]);
    const waiting = [];
    let running = 0;
    const next = () => {
        while (running < MTASA_COMPARE_AT_ONCE && waiting.length) {
            running++;
            waiting.shift()();
        }
    };
    const readOf = id => {
        if (!reads.has(id)) {
            reads.set(id, new Promise((resolve, reject) => {
                waiting.push(() => mtasaLoadMapFiles(root + "z(" + id + ")/")
                    .then(files => resolve(files.read), reject)
                    .finally(() => {
                        running--;
                        next();
                    }));
                next();
            }));
        }
        return reads.get(id);
    };

    await Promise.all(cells.map(async cell => {
        const compare = JSON.parse(cell.dataset.compare);
        try {
            const [mine, theirs] = (await Promise.all([readOf(compare.this), readOf(compare.that)])).map(mtasaComparablePoints);
            const words = [];
            const rows = [];
            ["checkpoints", "spawnpoints", "pickups"].forEach(kind => {
                const same = mtasaComparePoints(mine[kind], theirs[kind], kind === "pickups");
                if (same !== null && same < 100) {
                    words.push(t("compare.pointsSame", {what: kind, percent: same}));
                    rows.push([t("compare.pointsLabel", {what: t("compare.label." + kind), percent: same}), String(mine[kind].length), String(theirs[kind].length)]);
                }
            });
            words.push(...compare.words);
            rows.push(...compare.rows);
            cell.textContent = words.length ? words.join("; ") : t("compare.identical");
            if (!rows.length) {
                cell.dataset.tooltip = t("compare.identicalMaps", {this: compare.this, that: compare.that});
                return;
            }
            const shown = value => value === "" ? t("map.unset") : (/^-?\d+(\.\d+)?$/.test(value) ? value : "\"" + value + "\"");
            cell.dataset.tooltip = rows.map(([what, a, b]) => t("compare.versus", {what: what, this: shown(a), that: shown(b)})).join("\n");
            cell.dataset.tooltipTable = JSON.stringify({
                head: ["", String(compare.this), String(compare.that)],
                none: t("map.unset"),
                rows: rows.map(([what, a, b]) => [what, a === "" ? null : a, b === "" ? null : b]),
            });
        }
        catch (error) {
            cell.textContent = t("map.github.failed", {error: error.message});
        }
    }));
}

/// Shows what js/analysis.js worked out from GitHub in place of the stored
/// values (the owner's "show and flag"): each row it has a value for, with a !
/// where the two differ that says what the site has stored. Nothing is saved,
/// and the list keeps the stored values. Route length 2D gets no !: its
/// stored value is worked out from points rounded to 0.1 m. Zones on route
/// has nothing stored to differ from.
function mtasaShowAnalysis(analysis, zoneNames) {
    const stored = JSON.parse(document.getElementById("mapStored").textContent);
    const total = mtasaStoredForm("int", analysis.values.checkpoints);
    const storedTotal = stored.checkpoints ? stored.checkpoints.value : null;
    Object.entries(analysis.values).forEach(([key, text]) => {
        const row = document.getElementById("field-" + key);
        const shown = row?.querySelector(".mtasaFieldShown");
        if (!shown || !stored[key]) {
            return;
        }
        const type = stored[key].type;
        shown.textContent = mtasaFieldDisplay(key, type, mtasaStoredForm(type, text), total, zoneNames);
        shown.parentElement.querySelector(".mtasaGithubDiffers")?.remove();
        if (key === "route_length_2d" || key === "zones_on_route" || mtasaSameAsStored(type, text, stored[key].value)) {
            return;
        }
        const before = mtasaFieldDisplay(key, type, stored[key].value, storedTotal, zoneNames);
        const mark = document.createElement("button");
        mark.type = "button";
        mark.className = "mtasaGuessMismatch mtasaGithubDiffers";
        mark.textContent = "!";
        mark.dataset.tooltip = t("map.github.differs", {value: before === "" ? t("map.unset") : before});
        mark.setAttribute("aria-label", mark.dataset.tooltip);
        shown.after(mark);
    });
}

/// The Downloads row's zip, once Increase detail has fetched the files (the
/// owner's choice: everything from GitHub waits behind that one consent): a
/// button that zips them under the map's folder, `z(84)/meta.xml` and
/// `z(84)/84.map`, so the zip unpacks straight into a server's resources, with
/// no second request.
function mtasaOfferZip(folder, files) {
    const shown = document.querySelector("#mtasaDownloadFiles .mtasaFieldShown");
    if (!shown) {
        return;
    }
    const button = document.createElement("button");
    button.type = "button";
    button.className = "mtasaZipDownload";
    button.textContent = t("map.downloads.zip", {folder: folder});
    button.addEventListener("click", () => {
        const url = URL.createObjectURL(mtasaZip(files.map(file => ({name: folder + "/" + file.name, bytes: file.bytes}))));
        const link = document.createElement("a");
        link.href = url;
        link.download = folder + ".zip";
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
    shown.replaceChildren(button);
}

const mtasaPreviewFigure = document.getElementById("preview");
const mtasaPreviewForm = document.querySelector(".mtasaPreviewSettings");
const mtasaPreviewDataScript = document.getElementById("previewData");

if (mtasaPreviewFigure && mtasaPreviewForm && mtasaPreviewDataScript) {
    const svg = mtasaPreviewFigure.querySelector("svg.mtasaPreview");
    const button = mtasaPreviewForm.querySelector(".mtasaPreviewLoad");
    const note = mtasaPreviewForm.querySelector(".mtasaPreviewNote");
    const folder = "z(" + mtasaMapId + ")";
    const sheet = mtasaSheetData(JSON.parse(mtasaPreviewDataScript.textContent));
    const counted = (key, count) => t(key + (count === 1 ? ".one" : ".many"), {count: count});
    let data = sheet;
    /// The height profile's points, once Increase detail has given heights.
    let profile = null;
    let frame = 0;

    /// The note says only what the frame leaves out (the owner's wish), redone
    /// on every draw, since the frame decides it.
    const draw = () => {
        /// A slider whose layer has nothing to draw is greyed out (the owner's
        /// wish): spawnpoints, pickups and objects until Increase detail, and
        /// after it on a map that has none.
        [["spawnpoints", data.spawns], ["pickups", data.pickups], ["objects", data.objects]].forEach(([name, list]) => {
            mtasaPreviewForm.elements[name].disabled = !list.length;
        });
        const settings = mtasaPreviewSettingsOf(mtasaPreviewForm);
        /// The map's colours come from GitHub: until then they are all blue.
        document.getElementById("mtasaColoursNote").hidden = data.detailed || !["map", "gradient"].includes(settings.colours);
        const outside = mtasaDrawPreview(svg, data, settings);
        if (profile) {
            mtasaDrawProfile(document.getElementById("mtasaProfile"), profile, data.checkpoints, settings);
        }
        const lines = [];
        if (outside.objects) {
            lines.push(counted("map.github.outside", outside.objects));
        }
        if (outside.pickups) {
            lines.push(counted("map.preview.pickupsOutside", outside.pickups));
        }
        note.textContent = lines.join(" ");
    };
    /// A slider fires on every step it passes, and a big map takes a few
    /// milliseconds to draw, so at most once a frame.
    const redraw = () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(draw);
    };

    const load = async () => {
        button.disabled = true;
        button.textContent = t("map.github.loading");
        try {
            const [github, zones] = await Promise.all([
                mtasaLoadMapFiles(mtasaPreviewFigure.dataset.github),
                fetch(mtasaPreviewFigure.dataset.zones).then(response => response.ok ? response.json() : null),
            ]);
            data = {...github, detailed: true};
            svg.setAttribute("aria-label", t("map.github.label", {folder: folder}));
            draw();
            if (zones) {
                mtasaShowAnalysis(mtasaAnalyse(github.read, zones), zones.names);
            }
            profile = mtasaRouteProfile(github.read);
            if (profile) {
                draw();
            }
            /// Not awaited: each cell says for itself if its maps fail to load.
            mtasaCompareCopies(mtasaMapId, github.read);
            mtasaOfferZip(folder, github.files);
            /// The button goes from under the cursor, and with it any hover box.
            hideMtasaTooltip();
            button.hidden = true;
        }
        catch (error) {
            note.textContent = t("map.github.failed", {error: error.message});
            button.textContent = t("map.preview.load");
            button.disabled = false;
        }
    };

    mtasaApplyPreviewSettings(mtasaPreviewForm, mtasaReadPreviewSettings());
    mtasaPreviewForm.hidden = false;
    draw();

    mtasaPreviewForm.addEventListener("input", event => {
        if (event.target.type === "range") {
            event.target.nextElementSibling.textContent = event.target.value;
        }
        mtasaWritePreviewSettings(mtasaPreviewSettingsOf(mtasaPreviewForm));
        if (event.target.name === "always") {
            if (event.target.checked && !data.detailed && !button.disabled) {
                load();
            }
            return;
        }
        redraw();
    });
    /// Enter in the form would submit it, reloading the page.
    mtasaPreviewForm.addEventListener("submit", event => event.preventDefault());
    button.addEventListener("click", load);
    mtasaPreviewForm.querySelector(".mtasaPreviewReset").addEventListener("click", () => {
        /// Reset is for the picture; always is a choice about GitHub, kept.
        const always = mtasaPreviewForm.elements.always.checked;
        mtasaPreviewForm.reset();
        mtasaPreviewForm.elements.always.checked = always;
        mtasaApplyPreviewSettings(mtasaPreviewForm, {});
        mtasaWritePreviewSettings(mtasaPreviewSettingsOf(mtasaPreviewForm));
        redraw();
    });

    if (mtasaPreviewForm.elements.always.checked) {
        load();
    }
}
