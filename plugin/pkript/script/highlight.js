// Pkript: Syntax Highlighter
// Usage:
//   #pkript(highlight){{
//   function hello(name) {
//       console.log("Hello, " + name);
//   }
//   }}

function isAlpha(c) {
    return (c >= "a" && c <= "z") || (c >= "A" && c <= "Z") || c == "_" || c == "$";
}

function isDigit(c) {
    return c >= "0" && c <= "9";
}

function isAlphaNum(c) {
    return isAlpha(c) || isDigit(c);
}

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

// Character sets for the scanner. spanWhile/spanUntil take a whole run in one
// call; a character at a time costs 50-60 steps each.
const IDENT_CHARS = "a-zA-Z0-9_$";
const NUM_CHARS = "0-9.";
const SPACE_CHARS = " \t";
// Anything that can start a token of its own
const TOKEN_START = "/\"'`0-9a-zA-Z_$";

function highlightJs(code) {
    let out = "";
    let i = 0;
    const len = code.length;

    while (i < len) {
        const c = code.charAt(i);
        const c2 = i + 1 < len ? code.charAt(i + 1) : "";

        // Single-line comment: // ...
        if (c == "/" && c2 == "/") {
            const start = i;
            i = code.spanUntil(i, "\n");
            const comment = code.substring(start, i);
            out += "<span style=\"color: #308a27; font-style: italic;\">" + htmlsc(comment) + "</span>";
            continue;
        }

        // Multi-line comment: /* ... */
        if (c == "/" && c2 == "*") {
            const start = i;
            const end = code.indexOf("*/", i + 2);
            i = end < 0 ? len : end + 2;
            const comment = code.substring(start, i);
            out += "<span style=\"color: #308a27; font-style: italic;\">" + htmlsc(comment) + "</span>";
            continue;
        }

        // String literals: "...", '...', `...`
        if (c == "\"" || c == "'" || c == "`") {
            const quote = c;
            // A backtick string may span lines; the other two may not
            const stop = quote == "`" ? "\\" + quote : "\\" + quote + "\n";
            const start = i;
            i++;
            while (i < len) {
                i = code.spanUntil(i, stop);
                if (i >= len) {
                    break;
                }
                const sc = code.charAt(i);
                if (sc == "\\") {
                    i += 2;
                    continue;
                }
                if (sc == quote) {
                    i++;
                    break;
                }
                break;      // newline in a non-backtick string
            }
            const strVal = code.substring(start, i);
            out += "<span style=\"color: #032f62;\">" + htmlsc(strVal) + "</span>";
            continue;
        }

        // Number literals
        if (isDigit(c)) {
            const start = i;
            i = code.spanWhile(i, NUM_CHARS);
            const numVal = code.substring(start, i);
            out += "<span style=\"color: #005cc5;\">" + htmlsc(numVal) + "</span>";
            continue;
        }

        // Identifiers, Keywords, Builtins
        if (isAlpha(c)) {
            const start = i;
            i = code.spanWhile(i, IDENT_CHARS);
            const ident = code.substring(start, i);

            // Look ahead for function call: func(...)
            const lookAhead = code.spanWhile(i, SPACE_CHARS);
            const isCall = lookAhead < len && code.charAt(lookAhead) == "(";

            if (KEYWORDS.includes(ident)) {
                out += "<span style=\"color: #d73a49; font-weight: bold;\">" + htmlsc(ident) + "</span>";
            } else if (BUILTINS.includes(ident)) {
                out += "<span style=\"color: #6f42c1;\">" + htmlsc(ident) + "</span>";
            } else if (isCall) {
                out += "<span style=\"color: #005cc5;\">" + htmlsc(ident) + "</span>";
            } else {
                out += htmlsc(ident);
            }
            continue;
        }

        // Punctuation and whitespace, taken as a run rather than one at a time
        const plain = code.spanUntil(i + 1, TOKEN_START);
        out += htmlsc(code.substring(i, plain));
        i = plain;
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

    const highlighted = highlightJs(code);
    return "<pre style=\"background-color: #f6f8fa; color: #24292e; border: 1px solid #e1e4e8; font-family: monospace; font-size: 13px;\"><code>" + highlighted + "</code></pre>";
}
