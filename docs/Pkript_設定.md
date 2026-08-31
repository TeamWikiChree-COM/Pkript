# Pkript/設定

Pkript の設定項目、直接呼び出し、セキュリティの解説です。

## 設定定数 (plugin/pkript.inc.php)

plugin/pkript.inc.php 内の定数を編集して動作を調整できます。

### スクリプトの置き場所
| 定数名 | 既定値 | 説明 |
| --- | --- | --- |
| PKRIPT_SCRIPT_DIR | 'plugin/pkript/script/' | スクリプトの配置ディレクトリ |
| PKRIPT_SCRIPT_EXT | 'pks,js' | 受け付ける拡張子（カンマ区切り、優先順） |
| PKRIPT_ALLOW_PAGE_SCRIPT | 1 | :config/pkript/script/ ページからの実行を許可するか |
| PKRIPT_PAGE_SCRIPT_FROZEN_ONLY | 0 | ページ実行を凍結ページのみに限定するか |
| PKRIPT_PAGE_PREFIX | ':config/pkript/script/' | ページスクリプトのページ名接頭辞 |
| PKRIPT_ALLOW_DATA | 1 | data.* によるデータ保存を有効にするか |
| PKRIPT_DATA_PREFIX | ':config/pkript/data/' | データを置くページ名の接頭辞 |
| PKRIPT_DATA_MIN_TRUST | PKRIPT_WRITE_MIN_TRUST | data.set() に必要な信頼度 |
| PKRIPT_BIND | 1 | #hello のように、スクリプト名で直接呼び出せるようにするか |

PKRIPT_BIND を 0 にすると #pkript(hello) と &amp;pkript(hello); だけになり、#hello は未定義のプラグイン扱いになります。下の「#aaa による直接呼び出し」も参照してください。

### 文法
| 定数名 | 既定値 | 説明 |
| --- | --- | --- |
| PKRIPT_JSX | 1 | JSX記法（&lt;p&gt;{name}&lt;/p&gt;）を使えるようにするか |
| PKRIPT_REGEX | 1 | 正規表現リテラル（/[a-z]+/i）を使えるようにするか |
| PKRIPT_REGEX_BACKTRACK | 100000 | 1回の照合で許すバックトラック量 |
| PKRIPT_MAX_REGEX | 512 | パターンの最大バイト数 |

PKRIPT_JSX を 0 にすると &lt; は比較演算子としてだけ働きます。詳しくは [Pkript/文法/JSX](Pkript_%E6%96%87%E6%B3%95_JSX.md) を参照してください。

PKRIPT_REGEX を 0 にすると / は除算としてだけ働きます。PKRIPT_REGEX_BACKTRACK は暴走するパターン（ReDoS）を止めるための上限で、PHPの既定値1,000,000より大幅に低く設定しています。詳しくは [Pkript/文法/正規表現](Pkript_%E6%96%87%E6%B3%95_%E6%AD%A3%E8%A6%8F%E8%A1%A8%E7%8F%BE.md) を参照してください。

### 1リクエストあたりの上限
1ページに #pkript をいくつ書いても、wiki.convert の入れ子で別のスクリプトが動いても、下の値が1リクエストの合計です。

| 定数名 | 既定値 | 説明 |
| --- | --- | --- |
| PKRIPT_MAX_STEPS | 1000000 | 評価ステップ数 |
| PKRIPT_MAX_TIME | 3 | 実行時間（秒）。Pkript の中で過ごした時間の合計です |
| PKRIPT_MAX_MEMORY | memory_limit の3/4 | メモリ使用量 |
| PKRIPT_MAX_CONVERT | 32 | wiki.convert の呼び出し回数 |
| PKRIPT_MAX_READS | 5000 | ページの参照回数（wiki.exists / source / pages / link） |
| PKRIPT_MAX_WRITES | 4 | ページの書き込み回数 |

### 1回の実行・1つの値の上限
| 定数名 | 既定値 | 説明 |
| --- | --- | --- |
| PKRIPT_MAX_DEPTH | 64 | 関数の最大呼び出し深度 |
| PKRIPT_MAX_LOOP | 100000 | 1ループの最大繰り返し回数 |
| PKRIPT_MAX_STRING | 1048576 | 文字列の最大バイト数（1MB） |
| PKRIPT_MAX_ARRAY | 10000 | 配列の最大要素数 |
| PKRIPT_MAX_PAGES | 1000 | wiki.pages が返せる最大ページ数 |
| PKRIPT_MAX_PAGE_BYTES | 524288 | 書き込めるページの最大バイト数（512KB） |
| PKRIPT_MAX_IMPORTS | 16 | import できる最大スクリプト数 |
| PKRIPT_MAX_IMPORT_DEPTH | 4 | import の最大深度 |

### 権限とキャッシュ
| 定数名 | 既定値 | 説明 |
| --- | --- | --- |
| PKRIPT_WRITE_MIN_TRUST | PKRIPT_TRUST_FILE | ページを書き込める最低の信頼度 |
| PKRIPT_IMPORT_LOWER_TRUST | 0 | 自分より信頼度の低いスクリプトの import を許すか |
| PKRIPT_SECRET_FILE | CACHE_DIR . 'pkript_secret.dat' | CSRFトークンの秘密鍵の置き場所 |
| PKRIPT_AST_CACHE | 1 | 解析済みスクリプトを cache/ に保存するか |
| PKRIPT_DEBUG | 1 | エラーに行番号と列番号を出すか、console.log の内容を出すか |
| PKRIPT_MAX_LOG | 100 | console.log で残せる行数 |
| PKRIPT_MAX_LOG_BYTES | 8192 | console.log 全体のバイト数 |

これらは pukiwiki.ini.php で define すれば上書きできます。

## #aaa による直接呼び出し

#pkript(hello) ではなく #hello と書いて直接呼び出せます。lib/plugin.php の exist_plugin が**実在するPHPプラグインを探して見つからなかったときだけ**、同名のスクリプトを探してラッパー関数を生成します。したがって同名のPHPプラグインは常に優先され、#edit や #attach をスクリプトに乗っ取らせることはできません。

```text
#hello(World)
&hello(World);
```

この動作は PKRIPT_BIND で切り替えます。0 にするとラッパーの生成をやめ、#pkript(hello) と &amp;pkript(hello); だけが呼び出し口になり、#hello は未定義のプラグイン扱いに戻ります。

```php
// pukiwiki.ini.php
define('PKRIPT_BIND', 0);   // 既定は 1
```

lib/plugin.php を自分で改造している場合など、フォールバックが入っていない環境では exist_plugin 関数に以下を追加してください。

```php
if (file_exists(PLUGIN_DIR . 'pkript.inc.php')) {
    require_once(PLUGIN_DIR . 'pkript.inc.php');
    if (function_exists('plugin_pkript_bind') && plugin_pkript_bind($name)) {
        $exist[$name] = TRUE;
        $count[$name] = 1;
        return TRUE;
    }
}
```

## セキュリティ

### 信頼度
スクリプトをどこに置いたかで権限が決まります。

| 置き場所 | 信頼度 | ページの書き込み |
| --- | --- | --- |
| ファイル（plugin/pkript/script/） | PKRIPT_TRUST_FILE | できます |
| 凍結ページ（:config/pkript/script/） | PKRIPT_TRUST_FROZEN | できません |
| 編集可能ページ（:config/pkript/script/） | PKRIPT_TRUST_PAGE | できません |

読み出し、表示、計算はどの信頼度でも同じように使えます。差が出るのはページの書き込みだけです。

#### import と信頼度
import しても権限は増えません。

- 実行全体が、関わったスクリプトのうち**一番低い信頼度**で動きます。凍結ページがファイルのライブラリを読み込んでも、その中の wiki.write は凍結ページの信頼度で判定されます。
- 自分より信頼度の低いスクリプトは読み込めません。編集可能なページの書き手が、凍結ページにコードを差し込めてしまうためです。PKRIPT_IMPORT_LOWER_TRUST を 1 にすると許可されますが、その場合も実行全体は低いほうに落ちます。

#### CSRFトークン
wiki.write と wiki.append は wiki.token() のトークンを要求します。トークンは**ログイン中のユーザーに紐付きます**。

認証を設定していないWikiでは紐付ける相手がいないため、トークンはサイト共通の値でしかありません。Wikiを読める人はトークンも読めます。誰でも編集できるWikiでの書き込み制限は、トークンではなく $edit_auth_pages で行ってください。

### HTMLサニタイザ
スクリプトが出力したHTMLは自動で検査され、危険なタグや属性は無害化されます。

- script, iframe 等の危険なタグは無効化されます。
- onclick 等のイベント属性は除去されます。
- href, src の javascript: や data: スキームは除去されます。
- class や id には自動で pkript- 接頭辞が付与されます。

### style属性で使えるCSS

プロパティのホワイトリストと、値のパターン検査の二段構えです。見た目のためのプロパティは広く通します。

| 分類 | 通すもの |
| --- | --- |
| 色・文字 | color, background-color, font-*, text-*, line-height, letter-spacing, word-spacing, white-space, word-break, overflow-wrap, list-style* |
| 箱 | margin*, padding*, border*, box-shadow, box-sizing, width, height, min-*, max-*, display, vertical-align, opacity, visibility, overflow*, float, clear, cursor |
| 表 | border-collapse, border-spacing, table-layout, caption-side, outline* |
| Flexbox | flex*, justify-*, align-*, order, gap, row-gap, column-gap |
| Grid | grid-template-*, grid-auto-*, grid-area, grid-column, grid-row, place-* |
| 動き | transition*, animation*, transform, transform-origin, will-change, filter |

```text
<div style="transform:rotate(3deg); transition:all 0.3s ease-in-out">
```

**通さないもの**は、見た目ではなく**位置**に関わるものです。

- position と z-index は通しません（fixed / absolute でWiki自身の上に重ねられるため）
- ビューポート単位（vw / vh）は通しません（ウィンドウに対して自分を大きくするのはオーバーレイの第一歩であるため）
- url(...) を含む値は通しません。したがって background-image も通しません
- expression(...)、javascript:、@import、\、/* を含む値は通しません
- 関数はホワイトリスト制です。rgb / rgba / hsl / hsla、translate* / scale* / rotate* / skew* / matrix / perspective、cubic-bezier / steps、repeat / minmax / fit-content、blur などのfilter系のみで、引数も同じ規則で検査します。calc() はまだ通していません

値が規則に合わないプロパティは、そのプロパティだけ黙って消えます（要素は残ります）。

animation-name が指すキーフレームは**スクリプトからは定義できません**。&lt;style&gt; は書けず、書いてもサニタイザが落とすため、参照できるのはスキンが既に定義しているものだけです。
