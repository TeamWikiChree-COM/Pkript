# Pkript/文法

Pkript の言語仕様です。

## 変数

var, let, const が使えます。

```js
var a = 1;
let b = 2;
const c = 3;
```

- var, let: 再代入可能
- const: 再代入不可
- 未宣言代入: `let` や `const` を付けずに `$args = ...` のように代入した場合、現在のローカルスコープで自動定義されます。

### グローバル変数
関数の外側にも変数を書けます。エントリポイントが呼ばれる前に、書いた順で評価されます。

```js
const KEYWORDS = ["function", "return", "const"];
let count = 0;

function plugin_hello_convert(e) {
    count = count + 1;
    return KEYWORDS.includes(e.args[0]) ? "keyword" : "no";
}
```

- 前に書いた変数は使えますが、後ろに書いた変数は使えません。
- 初期値の中から関数を呼べます。関数は順序に関係なく見えています。
- 関数と変数は同じ名前空間です。同じ名前を付けるとエラーになります。
- let と var の書き換えが効くのは、その1回の呼び出しの中だけです。#pkript の呼び出しごとに初期値へ戻ります。

### セミコロン省略
改行が入っていれば、文末のセミコロン ; は省略できます。

```js
var a = 1
var b = 2
return a + b
```

## 関数

### 関数宣言
トップレベルに関数を定義します。

```js
function plugin_hello_convert(e) {
    return "Hello World!";
}
```

- plugin_◯◯_convert / inline / action がエントリポイントになります。
- それ以外の関数はスクリプト内の共通処理として呼び出せます。

### アロー関数
アロー関数式が使えます。

```js
const double = (x) => x * 2;
const greet = () => "Hello";
```

エントリポイントをアロー関数で書くこともできます。

```js
const plugin_hello_convert = (e) => {
    return "Hello, " + e.args[0] + "!";
};
```

定義された場所のスコープをキャプチャします（クロージャ）。

```js
function makeAdder(n) {
    return (x) => x + n;
}
```

## 引数の受け取り方

### 1. func_get_args
PHPプラグインと同じ感覚で引数を取得できます。
- func_get_args(): 引数配列を取得
- func_num_args(): 引数の個数を取得
- func_get_arg(n): n番目の引数を取得

```js
function plugin_hello_convert() {
    var args = func_get_args();
    return "引数: " + args[0];
}
```

### 2. e オブジェクト
エントリポイントの第1引数にコンテキストオブジェクトが渡されます。

| プロパティ | 型 | 内容 |
| --- | --- | --- |
| e.args | Array | 渡された引数の配列 |
| e.opts | Object | key=value 形式のオプション |
| e.body | String | 複数行記法の本文 |
| e.page | String | 呼び出し元ページ名 |
| e.name | String | スクリプト名 |
| e.type | String | "convert" / "inline" / "action" |
| e.vars | Object | POST/GET のフォーム値 |
| e.user | Object | 閲覧しているユーザー |
| e.method | String | "GET" または "POST" |

- e.opts: #pkript(list, foo, bar, class=fruit) と渡すと e.opts.class で "fruit" が取れます。
- e.body: #pkript(list){{ 本文 }} の複数行テキストが入ります。
- e.vars: フォームの送信値が入ります。パスワード等の機密キーは自動で除外されます。

#### e.user

いま見ている人が誰かを表すオブジェクトです。

| プロパティ | 型 | 内容 |
| --- | --- | --- |
| e.user.name | String | ログイン名。未ログインは空文字列 |
| e.user.fullname | String | 表示名。未ログインは空文字列 |
| e.user.groups | Array | 所属グループ。未ログインは空配列 |

```js
if (e.user.name == "") return "<p>ログインしてください</p>";
return "<p>" + htmlsc(e.user.fullname) + " さん</p>";
```

- 3つのプロパティは**常に存在します**。この言語にはオプショナルチェーンが無く、存在しないプロパティを読むとエラーになるため、未ログインは null ではなく空文字列と空配列で表します。
- groups には $auth_groups で解決したグループのほか、**ユーザー名自身と valid-user** が入ります。したがってログイン中は空配列になりません。
- fullname が name と違う値になるのは、AUTH_TYPE_EXTERNAL / AUTH_TYPE_SAML で $ldap_user_account を有効にし、LDAPから表示名を引いている場合です。BASIC認証やフォーム認証では name と同じ値になります。
- **管理者かどうかは取得できません。** PukiWiki には管理者という身元が存在せず、pkwk_login() はそのリクエストで送られたパスワードを $adminpass と比較するだけで結果をどこにも残さないためです。権限で分岐するなら wiki.canWrite(page) か e.user.groups を使ってください。
- 閲覧者ごとに出力が変わるページになります。リバースプロキシやブラウザキャッシュを挟む構成では、ある人向けのHTMLが別の人に返る可能性があります。

## データ型

| 型 | 例 |
| --- | --- |
| String | "文字列", '文字列' |
| Number | 10, 3.14 |
| Boolean | true, false |
| Array | [1, 2, 3] |
| Object | { a: 1, b: "text" } |
| Null | null |
| RegExp | /[a-z]+/i |

### 数値リテラル

```text
255      0xff     0XFF        // 10進 / 16進
0b1010   0o17                 // 2進 / 8進
1e3      2e-3     1.5e2       // 指数
1_000_000          0x1_F      // 桁区切り
```

- _ は**数字と数字の間にだけ**書けます。1_ や _1、1__0 は書けません。
- . か指数の付いた数は、1.0 や 1e3 のように整数に着地しても Number（小数）のままです。
- 整数として表せない大きさになると小数になります。JavaScript が正確でなくなるのと同じ位置です。
- 数値の直後に識別子の文字が続くとエラーになります（123abc、0x）。

## テンプレートリテラル

バッククォートで囲むと、${} の中に式を書けます。改行もそのまま入ります。

```js
const cls = e.opts["class"];
const rows = e.args.map((a) => `<li>${htmlsc(a)}</li>`).join("");
return `<ul class="${cls}">
  ${rows}
</ul>`;
```

- ${} の中には式なら何でも書けます。関数呼び出しも、テンプレートの入れ子も通ります。
- 値は String() と同じ規則で文字列になります。null は空文字列です。
- エスケープは文字列と同じものが使えるほか、\` と \$ が増えます。
- 文字列の中の } は ${} を閉じません。

## JSX記法

HTMLを式としてそのまま書けます。文字列連結の代わりに使います。

```js
function plugin_hello_convert(e) {
    const name = e.args[0];
    return <p class="greeting">こんにちは、{name} さん</p>;
}
```

- {} の中の文字列は自動でエスケープされます。htmlsc() を重ねると二重エスケープになります。
- JSX要素どうしの埋め込みはエスケープされません。入れ子がそのまま組み立てられます。
- PKRIPT_JSX を 0 にすると無効になり、&lt; は比較演算子としてだけ働きます。

書ける形、エスケープの規則、サニタイザとの関係は [Pkript/文法/JSX](Pkript_%E6%96%87%E6%B3%95_JSX.md) にまとめています。

## 正規表現

/パターン/フラグ と書きます。値としての型は RegExp で、変数にも入れられます。

```js
const DATE = /(\d{4})-(\d{2})-(\d{2})/;
if (DATE.test(line)) {
    return line.replace(DATE, "$1年$2月$3日");
}
```

- フラグは g（全件）、i（大小無視）、m（複数行）、s（. が改行にも一致）が使えます。
- UTF-8としての解釈は常に有効です。
- PKRIPT_REGEX を 0 にすると無効になり、/ は除算としてだけ働きます。

書ける形、使えるメソッド、暴走を止める仕組みは [Pkript/文法/正規表現](Pkript_%E6%96%87%E6%B3%95_%E6%AD%A3%E8%A6%8F%E8%A1%A8%E7%8F%BE.md) にまとめています。

## 制御構文

### 条件分岐
```text
if (cond) {
    // ...
} else if (other) {
    // ...
} else {
    // ...
}
```

### ループ
```text
while (cond) {
    // ...
}
```

```text
do {
    // ...
} while (cond);
```

```js
for (let i = 0; i < n; i++) {
    // ...
}
```

```js
for (const item of array) {
    // ...
}
```

```js
for (const key in object) {
    // ...
}
```

- break と continue が使えます。
- for..of は Array と String を走査します。要素の**値**が入ります。
- for..in はキーが入ります。Array に使うと添字が**文字列**で入るので、配列は for..of を使ってください。
- ループ変数は繰り返しごとに新しく束縛されるため、const が使えます。
- do..while は条件より先に本体を実行するので、必ず1回は回ります。

### ラベル

ループに名前を付けると、break と continue でどのループを指すか書けます。

```js
outer: for (const row of rows) {
    for (const cell of row) {
        if (cell == "") continue outer;   // 次の row へ
        if (cell == "!") break outer;     // 二重ループごと抜ける
    }
}
```

- ラベルは break / continue の**同じ行に**書きます。改行は文の終わりなので、次の行の識別子はラベルではなく別の文になります。
- continue のラベルはループに付いている必要があります。ブロックに付けたラベルへは break だけが届きます。
- ラベル付きの break は switch を素通りします。switch が飲み込むのはラベルの無い break だけです。
- 存在しないラベルと二重定義のラベルは**解析時**にエラーになります。

```js
switch (e.type) {
    case "convert":
        return "ブロック";
    case "inline":
        return "インライン";
    default:
        return "その他";
}
```

- JavaScript と同じくフォールスルーします。break を書かないと次の case へ流れます。
- case の比較は === 相当です。"1" と 1 は一致しません。
- default はどこに置いても構いません。一致する case が無ければ default から末尾まで走ります。
- switch の中の break は switch だけを抜けます。continue は外側のループのものとして働きます。

    - -

## エラー処理 (try / catch)

```js
try {
    const data = JSON.parse(wiki.source("Settings"));
} catch (err) {
    return "<p>" + htmlsc(err.message) + "</p>";
}
```

- catch (err) の変数は省略できます（catch { ... }）。
- err は message を持つオブジェクトです。エラー文言と発生位置が入ります。
- return, break, continue は try を素通りします。
- finally と throw は使えません。

### リソース上限は catch できません
実行時間、ステップ数、メモリ、ループ回数、再帰の深さ、文字列の長さ、配列の要素数、ページの参照・書き込み回数、wiki.convert の回数。これらが上限に達したときのエラーは catch を素通りして実行を終わらせます。

暴走したループを try で包んで走り続けられると、上限そのものが意味を失うためです。

    - -

## モジュール読み込み (import)

他のスクリプトで定義された関数を読み込みます。

```js
import "util";

function plugin_hello_convert(e) {
    return util_wrap("p", e.args[0]);
}
```

- import はトップレベルでのみ使えます。
- 循環参照や重複読み込みは自動で1回に抑えられます。
- 読み込んだ側の関数名やグローバル変数が衝突するとエラーになります。後から上書きはしません。
- 読み込まれたスクリプトの plugin_◯◯_convert などは呼ばれません。
- 読み込む本数と入れ子の深さに上限があります（既定16本、深さ4）。
- 自分より信頼度の低いスクリプトは読み込めません。詳しくは [Pkript/設定](Pkript_%E8%A8%AD%E5%AE%9A.md) を参照してください。

## フォーム

フォーム要素を安全に出力できます。
form, input, textarea, select, option, optgroup, label, button, fieldset, legend

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

- form action は自Wikiへの相対パスに限定されます。
- password, file, image タイプの input は禁止されています。
