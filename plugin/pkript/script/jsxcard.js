// Pkript sample: #pkript(jsxcard, 見出し, class=note){{ 本文 }}

const COLORS = { note: "#4a6", warn: "#c62", info: "#468" };

function pickColor(kind) {
    return Object.has(COLORS, kind) ? COLORS[kind] : COLORS["info"];
}

function plugin_jsxcard_convert(e) {
    const title = e.args[0] || "(無題)";
    const kind = e.opts["class"] || "info";
    const color = pickColor(kind);
    const lines = e.body == "" ? [] : e.body.split("\n");

    return <div class={"card " + kind} style={"border-left: 4px solid " + color}>
        <p style={"color: " + color}><b>{title}</b></p>
        {lines.map((line) => <p>{line}</p>).join("")}
    </div>;
}
