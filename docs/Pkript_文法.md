#author("2026-08-31T11:31:29+09:00","","")

# Pkript/文法

Pkript の言語仕様リファレンス。

## 変数

var / let / const で宣言する。

```js
var a = 1;
let b = 2;
const c = 3;
```

- var / let: 再代入可能
- const: 再代入不可
- 未宣言代入: `let` や `const` を省略して `x = ...` と書くと、現在のローカルスコープに自動宣言される。

### グローバル変数
関数の外で宣言できる。エントリポイントが呼ばれる前に、記述順で評価される。

```js
const KEYWORDS = ["function", "return", "const"];
let count = 0;

function plugin_hello_convert(e) {
    count = count + 1;
    return KEYWORDS.includes(e.args[0]) ? "keyword" : "no";
}
```

- 前に書いた変数は参照できるが、後ろに書いた変数は参照できない。
- 初期値の式から関数を呼ぶことができる。関数は宣言順に関係なく参照可能。
- 関数と変数は同じ名前空間。同名はエラー。
- let / var の変更はそのリクエスト内のみ有効。呼び出しごとに初期値へ戻る。

### セミコロン省略
改行があれば文末のセミコロンを省略できる。

```js
var a = 1
var b = 2
return a + b
```

## 関数

### 関数宣言
トップレベルに定義する。

```js
function plugin_hello_convert(e) {
    return "Hello World!";
}
```

- plugin_◯◯_convert / inline / action がエントリポイント。
- それ以外の関数はスクリプト内のユーティリティとして呼び出せる。

### アロー関数

```js
const double = (x) => x * 2;
const greet = () => "Hello";
```

エントリポイントをアロー関数で定義できる。

```js
const plugin_hello_convert = (e) => {
    return "Hello, " + e.args[0] + "!";
};
```

定義された場所のスコープをキャプチャする（クロージャ）。

```js
function makeAdder(n) {
    return (x) => x + n;
}
```

## 引数の受け取り方

### 1. func_get_args
- func_get_args(): 引数配列を返す
- func_num_args(): 引数の個数を返す
- func_get_arg(n): n番目の引数を返す

```js
function plugin_hello_convert() {
    var args = func_get_args();
    return "引数: " + args[0];
}
```

### 2. e オブジェクト
エントリポイントの第1引数としてコンテキストオブジェクトが渡される。

| プロパティ | 型 | 内容 |
| --- | --- | --- |
| e.args | Array | 渡された引数の配列 |
| e.opts | Object | key=value 形式のオプション |
| e.body | String | 複数行記法の本文 |
| e.page | String | 呼び出し元ページ名 |
| e.name | String | スクリプト名 |
| e.type | String | "convert" / "inline" / "action" |
| e.vars | Object | POST/GET のフォーム値 |
| e.user | Object | 閲覧ユーザー情報（下記） |
| e.method | String | "GET" または "POST" |

- e.opts: #pkript(list, foo, bar, class=fruit) と渡すと e.opts.class で "fruit" が取れる。
- e.body: #pkript(list){{ 本文 }} の複数行テキストが入る。
- e.vars: フォームの送信値。パスワード等の機密キーは自動で除外される。

#### e.user

閲覧ユーザーを表すオブジェクト。

| プロパティ | 型 | 内容 |
| --- | --- | --- |
| e.user.name | String | ログイン名。未ログインは空文字列 |
| e.user.fullname | String | 表示名。未ログインは空文字列 |
| e.user.groups | Array | 所属グループ。未ログインは空配列 |

```js
if (e.user.name == "") return "<p>ログインしてください</p>";
return "<p>" + htmlsc(e.user.fullname) + " さん</p>";
```

- 3つのプロパティは常に存在する。存在しないプロパティへのアクセスはエラーになるため、未ログイン状態は null ではなく空文字列・空配列で表す。
- groups には $auth_groups で解決したグループのほか、ユーザー名自身と valid-user が含まれる。ログイン中は空配列にならない。
- fullname が name と異なる値になるのは、AUTH_TYPE_EXTERNAL / AUTH_TYPE_SAML で $ldap_user_account を有効にしLDAPから表示名を取得している場合。BASIC認証・フォーム認証では name と同じ値になる。
- 管理者判定は取得できない。 PukiWiki に管理者という概念はなく、pkwk_login() はパスワードの比較のみで結果を保持しない。権限で分岐する場合は wiki.canWrite(page) か e.user.groups を使う。
- 閲覧者ごとに出力が変わるページになる。リバースプロキシやブラウザキャッシュを挟む構成では注意が必要。

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

- _ は数字と数字の間にのみ書ける。1_ や _1、1__0 は不正。
- . または指数を含む数値は、整数値に着地しても Number（浮動小数点数）のまま。
- 整数として表現できない大きさになると浮動小数点数になる。精度はJavaScriptと同じ。
- 数値の直後に識別子文字が続く場合はエラー（例: 123abc、0x）。

## テンプレートリテラル

バッククォートで囲み、${} の中に式を書く。改行もそのまま文字列に含まれる。

```js
const cls = e.opts["class"];
const rows = e.args.map((a) => `<li>${htmlsc(a)}</li>`).join("");
return `<ul class="${cls}">
  ${rows}
</ul>`;
```

- ${} には任意の式を書ける。関数呼び出しも入れ子のテンプレートリテラルも可。
- 値は String() と同じ規則で文字列化される。null は空文字列になる。
- エスケープシーケンスは通常の文字列リテラルと同じ。追加で \` と \$ が使える。
- 文字列リテラル内の } は ${} を閉じない。

## JSX記法

HTML要素を式として記述できる。

```js
function plugin_hello_convert(e) {
    const name = e.args[0];
    return <p class="greeting">こんにちは、{name} さん</p>;
}
```

- {} 内の文字列は自動でHTMLエスケープされる。htmlsc() を重ねると二重エスケープになる。
- JSX要素どうしの埋め込みはエスケープされない。入れ子がそのまま展開される。

書ける形、エスケープの規則、サニタイザとの関係は [Pkript/文法/JSX](Pkript_%E6%96%87%E6%B3%95_JSX.md)。有効・無効の切り替えは [Pkript/設定](Pkript_%E8%A8%AD%E5%AE%9A.md)。

## 正規表現

/パターン/フラグ と書く。型は RegExp で、変数に代入できる。

```js
const DATE = /(\d{4})-(\d{2})-(\d{2})/;
if (DATE.test(line)) {
    return line.replace(DATE, "$1年$2月$3日");
}
```

- フラグ: g（全件）、i（大小無視）、m（複数行）、s（. が改行にも一致）
- UTF-8解釈は常に有効。

書ける形、使えるメソッド、バックトラック制限は [Pkript/文法/正規表現](Pkript_%E6%96%87%E6%B3%95_%E6%AD%A3%E8%A6%8F%E8%A1%A8%E7%8F%BE.md)。有効・無効の切り替えは [Pkript/設定](Pkript_%E8%A8%AD%E5%AE%9A.md)。

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

- break / continue が使える。
- for..of は Array と String を走査する。要素の値が入る。
- for..in はキーが入る。Array に使うと添字が文字列で入るため、配列には for..of を使うこと。
- ループ変数は繰り返しごとに新しく束縛されるため const を使える。
- do..while は本体を先に実行するため必ず1回以上実行される。

### ラベル

break / continue の対象ループを名前で指定できる。

```js
outer: for (const row of rows) {
    for (const cell of row) {
        if (cell == "") continue outer;   // 次の row へ
        if (cell == "!") break outer;     // 二重ループごと抜ける
    }
}
```

- ラベルは break / continue と同じ行に書く。改行は文の終わりとして扱われる。
- continue のラベルはループに付いている必要がある。ブロックへは break のみ届く。
- ラベル付きの break は switch を素通りする（switch が飲み込むのはラベルなしの break のみ）。
- 存在しないラベルと二重定義のラベルは解析時にエラー。

### switch

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

- フォールスルーする。break を書かなければ次の case へ流れる。
- case の比較は === 相当。"1" と 1 は一致しない。
- default はどこに置いてもよい。一致する case がない場合に実行される。
- switch 内の break は switch のみを抜ける。continue は外側のループのものとして働く。

    - -

## エラー処理 (try / catch)

```js
try {
    const data = JSON.parse(wiki.source("Settings"));
} catch (err) {
    return "<p>" + htmlsc(err.message) + "</p>";
}
```

- catch (err) の変数は省略できる（catch { ... }）。
- err.message にエラー文言と発生位置が入る。
- return / break / continue は try を素通りする。
- finally と throw は使えない。

### リソース上限は catch できない
実行時間・ステップ数・メモリ・ループ回数・再帰深度・文字列長・配列要素数・ページ参照/書き込み回数・wiki.convert 回数がそれぞれの上限に達した場合、エラーは catch を素通りして実行を終了する。

    - -

## モジュール読み込み (import)

他のスクリプトで定義された関数をトップレベルスコープに読み込む。

```js
import "util";

function plugin_hello_convert(e) {
    return util_wrap("p", e.args[0]);
}
```

- import はトップレベルにのみ書ける。
- 循環参照・重複読み込みは自動で1回に抑制される。
- 読み込み先の関数名・グローバル変数が現在のスクリプトと衝突した場合はエラー。上書きはしない。
- 読み込まれたスクリプトの plugin_◯◯_convert などは呼ばれない。
- 読み込み本数と入れ子深度に上限がある（既定: 16本、深さ4）。
- 自スクリプトより信頼度の低いスクリプトは読み込めない。詳細は [Pkript/設定](Pkript_%E8%A8%AD%E5%AE%9A.md)。

## フォーム

以下のフォーム要素をHTMLとして出力できる。
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

- form action は自Wikiへの相対パスに限定される。
- input の type が password / file / image のものは禁止される。
