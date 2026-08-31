// Pkript sample: #pkript(list, りんご, みかん, class=fruit)
//
// Shows the v0.3 additions: loops, if, objects and e.opts / e.body.

function plugin_list_convert(e) {
    // 'key=value' arguments are split out into e.opts, and stay in e.args too
    const cls = e.opts["class"];

    let items = [];
    for (const arg of e.args) {
        if (arg == "" || arg.includes("=")) continue;
        items.push(arg);
    }

    // #pkript(list){{ ... }} adds one item per body line
    if (e.body != "") {
        for (const line of e.body.split("\n")) {
            if (line.trim() != "") items.push(line.trim());
        }
    }

    if (items.length == 0) return "<p>(empty)</p>";

    let out = cls == "" ? "<ul>" : "<ul class=\"" + htmlsc(cls) + "\">";
    for (const item of items) {
        out += "<li>" + htmlsc(item) + "</li>";
    }
    return out + "</ul>";
}

function plugin_list_inline(e) {
    return "<span>" + e.args.length + " items</span>";
}
