# Pkript/文法/フォーム

## リファレンス

### 出力できる要素

form, input, textarea, select, option, optgroup, label, button, fieldset, legend

- form の action は自Wikiへの相対パスに限定される
- input の type が password / file / image のものは禁止される

## 使用法

### フォームを描画して送信を処理する

```js
function plugin_form_convert(e) {
    return "<form method=\"post\">"
        + "<input type=\"hidden\" name=\"plugin\" value=\"pkript\">"
        + "<input type=\"hidden\" name=\"script\" value=\"form\">"
        + "<input type=\"text\" name=\"name\">"
        + "<input type=\"submit\" value=\"送信\">"
        + "</form>";
}

function plugin_form_action(e) {
    if (e.method != "POST") return "<p>POSTで送信してください</p>";
    return "<p>こんにちは、" + htmlsc(e.vars["name"]) + " さん</p>";
}
```
