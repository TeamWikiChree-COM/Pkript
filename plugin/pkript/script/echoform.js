// Pkript sample: a form that posts back to its own action handler.
//
//   #pkript(echoform)
//
// The form posts to ?plugin=pkript with a hidden 'script' field naming this
// script, so plugin_echoform_action() runs and reads the fields from e.vars.
//
// Note there is no page writing here - Pkript cannot write pages. This shows
// the form round trip only.

function plugin_echoform_convert(e) {
    return "<form method=\"post\">"
        + "<input type=\"hidden\" name=\"plugin\" value=\"pkript\">"
        + "<input type=\"hidden\" name=\"script\" value=\"echoform\">"
        + "<input type=\"hidden\" name=\"refer\" value=\"" + htmlsc(e.page) + "\">"
        + "<label for=\"who\">お名前</label> "
        + "<input type=\"text\" name=\"who\" id=\"who\" size=\"16\"> "
        + "<input type=\"text\" name=\"say\" size=\"30\" placeholder=\"ひとこと\"> "
        + "<input type=\"submit\" value=\"送信\">"
        + "</form>";
}

function plugin_echoform_action(e) {
    if (e.method != "POST") {
        return "<p>フォームから送信してください。</p>";
    }

    const who = e.vars["who"].trim();
    const say = e.vars["say"].trim();

    if (say == "") {
        return "<p>ひとことが空です。</p>";
    }

    let out = "<p>";
    out += htmlsc(who == "" ? "名無し" : who) + " さん: " + htmlsc(say);
    out += "</p>";

    const refer = e.vars["refer"];
    if (refer != "" && wiki.exists(refer)) {
        out += "<p>" + wiki.link(refer, "戻る") + "</p>";
    }
    return out;
}
