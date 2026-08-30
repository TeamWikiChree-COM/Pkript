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

function getKeywords() {
    return [
        "function", "return", "let", "const", "var", "if", "else", "while",
        "for", "of", "in", "break", "continue", "switch", "case", "default",
        "new", "typeof", "instanceof", "try", "catch", "finally", "throw",
        "class", "extends", "import", "export", "from", "as", "async", "await",
        "yield", "this", "super", "delete", "void"
    ];
}

function getBuiltins() {
    return [
        "true", "false", "null", "undefined", "NaN", "Infinity",
        "String", "Number", "Boolean", "Array", "Object", "Math", "JSON",
        "console", "document", "window", "Promise", "Symbol", "Date"
    ];
}

function highlightJs(code) {
    const keywords = getKeywords();
    const builtins = getBuiltins();

    let out = "";
    let i = 0;
    const len = code.length;

    while (i < len) {
        const c = code.charAt(i);
        const c2 = i + 1 < len ? code.charAt(i + 1) : "";

        // Single-line comment: // ...
        if (c == "/" && c2 == "/") {
            let start = i;
            while (i < len && code.charAt(i) != "\n") {
                i++;
            }
            const comment = code.substring(start, i);
            out += "<span style=\"color: #308a27; font-style: italic;\">" + htmlsc(comment) + "</span>";
            continue;
        }

        // Multi-line comment: /* ... */
        if (c == "/" && c2 == "*") {
            let start = i;
            i += 2;
            while (i < len) {
                if (code.charAt(i) == "*" && i + 1 < len && code.charAt(i + 1) == "/") {
                    i += 2;
                    break;
                }
                i++;
            }
            const comment = code.substring(start, i);
            out += "<span style=\"color: #308a27; font-style: italic;\">" + htmlsc(comment) + "</span>";
            continue;
        }

        // String literals: "...", '...', `...`
        if (c == "\"" || c == "'" || c == "`") {
            const quote = c;
            let start = i;
            i++;
            while (i < len) {
                const sc = code.charAt(i);
                if (sc == "\\") {
                    i += 2;
                    continue;
                }
                if (sc == quote) {
                    i++;
                    break;
                }
                if (quote != "`" && sc == "\n") {
                    break;
                }
                i++;
            }
            const strVal = code.substring(start, i);
            out += "<span style=\"color: #032f62;\">" + htmlsc(strVal) + "</span>";
            continue;
        }

        // Number literals
        if (isDigit(c)) {
            let start = i;
            while (i < len) {
                const nc = code.charAt(i);
                if (isDigit(nc) || nc == ".") {
                    i++;
                } else {
                    break;
                }
            }
            const numVal = code.substring(start, i);
            out += "<span style=\"color: #005cc5;\">" + htmlsc(numVal) + "</span>";
            continue;
        }

        // Identifiers, Keywords, Builtins
        if (isAlpha(c)) {
            let start = i;
            while (i < len && isAlphaNum(code.charAt(i))) {
                i++;
            }
            const ident = code.substring(start, i);

            // Look ahead for function call: func(...)
            let isCall = false;
            let lookAhead = i;
            while (lookAhead < len && (code.charAt(lookAhead) == " " || code.charAt(lookAhead) == "\t")) {
                lookAhead++;
            }
            if (lookAhead < len && code.charAt(lookAhead) == "(") {
                isCall = true;
            }

            if (keywords.includes(ident)) {
                out += "<span style=\"color: #d73a49; font-weight: bold;\">" + htmlsc(ident) + "</span>";
            } else if (builtins.includes(ident)) {
                out += "<span style=\"color: #6f42c1;\">" + htmlsc(ident) + "</span>";
            } else if (isCall) {
                out += "<span style=\"color: #005cc5;\">" + htmlsc(ident) + "</span>";
            } else {
                out += htmlsc(ident);
            }
            continue;
        }

        // Punctuation and whitespace
        out += htmlsc(c);
        i++;
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
