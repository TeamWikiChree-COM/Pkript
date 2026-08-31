// Pkript: Syntax Highlighter
// Usage:
//   #pkript(highlight){{
//   function hello(name) {
//       console.log("Hello, " + name);
//   }
//   }}

const C_COMMENT = "color: #308a27; font-style: italic;";
const C_KEYWORD = "color: #d73a49; font-weight: bold;";
const C_BUILTIN = "color: #6f42c1;";
const C_STRING = "color: #032f62;";
const C_NUMBER = "color: #005cc5;";

const KEYWORDS = [
    "function", "return", "let", "const", "var", "if", "else", "while",
    "for", "of", "in", "break", "continue", "switch", "case", "default",
    "new", "typeof", "instanceof", "try", "catch", "finally", "throw",
    "class", "extends", "import", "export", "from", "as", "async", "await",
    "yield", "this", "super", "delete", "void"
];

const BUILTINS = [
    "true", "false", "null", "undefined", "NaN", "Infinity",
    "String", "Number", "Boolean", "Array", "Object", "Math", "JSON",
    "console", "document", "window", "Promise", "Symbol", "Date"
];

// word -> style. One lookup replaces two linear includes() scans per identifier.
const WORD_STYLE = KEYWORDS.reduce((acc, k) => {
    acc[k] = C_KEYWORD;
    return acc;
}, BUILTINS.reduce((acc, b) => {
    acc[b] = C_BUILTIN;
    return acc;
}, {}));

// Character sets for the scanner. spanWhile/spanUntil take a whole run in one
// call; a character at a time costs 50-60 steps each.
const IDENT_CHARS = "a-zA-Z0-9_$";
const NUM_CHARS = "0-9.";
const SPACE_CHARS = " \t";
const DIGITS = "0123456789";
const ALPHA = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_$";
// Anything that can start a token of its own
const TOKEN_START = "/\"'`0-9a-zA-Z_$";

function wrap(style, text) {
    return `<span style="${style}">${htmlsc(text)}</span>`;
}

function highlightJs(code) {
    let out = "";
    let i = 0;
    const len = code.length;

    while (i < len) {
        const c = code.charAt(i);
        const c2 = code.charAt(i + 1);
        const start = i;

        // Comments: // ... and /* ... */
        if (c == "/" && (c2 == "/" || c2 == "*")) {
            if (c2 == "/") {
                i = code.spanUntil(i, "\n");
            } else {
                const end = code.indexOf("*/", i + 2);
                i = end < 0 ? len : end + 2;
            }
            out += wrap(C_COMMENT, code.substring(start, i));
            continue;
        }

        // String literals: "...", '...', `...`
        if (c == "\"" || c == "'" || c == "`") {
            // A backtick string may span lines; the other two may not
            const stop = c == "`" ? "\\" + c : "\\" + c + "\n";
            i++;
            while (i < len) {
                i = code.spanUntil(i, stop);
                if (i >= len) break;
                if (code.charAt(i) == "\\") {
                    i += 2;
                    continue;
                }
                if (code.charAt(i) == c) i++;
                break;      // closed, or newline in a non-backtick string
            }
            out += wrap(C_STRING, code.substring(start, i));
            continue;
        }

        // Number literals
        if (DIGITS.includes(c)) {
            i = code.spanWhile(i, NUM_CHARS);
            out += wrap(C_NUMBER, code.substring(start, i));
            continue;
        }

        // Identifiers, Keywords, Builtins
        if (ALPHA.includes(c)) {
            i = code.spanWhile(i, IDENT_CHARS);
            const ident = code.substring(start, i);
            let style = WORD_STYLE[ident];
            if (!style) {
                // Look ahead for a function call: func(...)
                const ahead = code.spanWhile(i, SPACE_CHARS);
                if (ahead < len && code.charAt(ahead) == "(") style = C_NUMBER;
            }
            out += style ? wrap(style, ident) : htmlsc(ident);
            continue;
        }

        // Punctuation and whitespace, taken as a run rather than one at a time
        i = code.spanUntil(i + 1, TOKEN_START);
        out += htmlsc(code.substring(start, i));
    }

    return out;
}

function getCode(e) {
    if (e.body != "") return e.body;
    if (e.args.length > 0 && e.args[0] != "") return e.args[0];
    return "";
}

function plugin_highlight_convert(e) {
    const code = getCode(e);
    if (code == "") return "<p>(no code)</p>";

    const style = "background-color: #f6f8fa; color: #24292e; border: 1px solid #e1e4e8; font-family: monospace; font-size: 13px;";
    return `<pre style="${style}"><code>${highlightJs(code)}</code></pre>`;
}
