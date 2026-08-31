// Pkript sample: #pkript(hello, World)

function plugin_test_convert(e) {
    const greeting = "Hello, ";
    let name = e.args[0];
    return "<p>" + greeting + html.escape(name) + "!</p>";
}

function plugin_hello_inline(e) {
    return "<span>Test, " + html.escape(e.args[0]) + "!</span>";
}
