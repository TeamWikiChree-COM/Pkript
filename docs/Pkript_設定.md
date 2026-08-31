* Pkript/設定

plugin/pkript.inc.php の設定定数、直接呼び出し、セキュリティの仕様リファレンス。

## 設定定数 (plugin/pkript.inc.php)

定数を define することで動作を変更できる。pukiwiki.ini.php で define した値が優先される。

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
| PKRIPT_BIND | 1 | スクリプト名による直接呼び出し（#hello 形式）を有効にするか |

PKRIPT_BIND を 0 にすると #pkript(hello) と &amp;pkript(hello); のみが呼び出し口になる。#hello は未定義のプラグイン扱いになる。

### 文法
| 定数名 | 既定値 | 説明 |
| --- | --- | --- |
| PKRIPT_JSX | 1 | JSX記法（&lt;p&gt;{name}&lt;/p&gt;）を有効にするか |
| PKRIPT_REGEX | 1 | 正規表現リテラル（/[a-z]+/i）を有効にするか |
| PKRIPT_REGEX_BACKTRACK | 100000 | 1回の照合で許すバックトラック量 |
| PKRIPT_MAX_REGEX | 512 | パターンの最大バイト数 |

PKRIPT_JSX を 0 にすると &lt; は比較演算子としてのみ動作する（[Pkript/文法/JSX](Pkript_%E6%96%87%E6%B3%95_JSX.md) 参照）。

PKRIPT_REGEX を 0 にすると / は除算としてのみ動作する。PKRIPT_REGEX_BACKTRACK は ReDoS を防ぐための上限（[Pkript/文法/正規表現](Pkript_%E6%96%87%E6%B3%95_%E6%AD%A3%E8%A6%8F%E8%A1%A8%E7%8F%BE.md) 参照）。

### 1リクエストあたりの上限
1ページに #pkript を複数書いても、wiki.convert の入れ子でスクリプトが動いても、以下は1リクエストの合計値。

| 定数名 | 既定値 | 説明 |
| --- | --- | --- |
| PKRIPT_MAX_STEPS | 1000000 | 評価ステップ数 |
| PKRIPT_MAX_TIME | 3 | 実行時間（秒）。Pkript 内での合計時間 |
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
| PKRIPT_IMPORT_LOWER_TRUST | 0 | 自分より信頼度の低いスクリプトの import を許可するか |
| PKRIPT_SECRET_FILE | CACHE_DIR . 'pkript_secret.dat' | CSRFトークン秘密鍵の保存場所 |
| PKRIPT_AST_CACHE | 1 | 解析済みスクリプトを cache/ に保存するか |
| PKRIPT_DEBUG | 1 | エラーに行番号・列番号を付与するか、console.log を出力するか |
| PKRIPT_MAX_LOG | 100 | console.log で記録できる行数 |
| PKRIPT_MAX_LOG_BYTES | 8192 | console.log 全体のバイト数上限 |

## #aaa による直接呼び出し

#pkript(hello) の代わりに #hello と書いて呼び出せる。lib/plugin.php の exist_plugin が実在するPHPプラグインを探して見つからなかったときだけ、同名のスクリプトを探してラッパー関数を生成する。同名のPHPプラグインは常に優先される。#edit や #attach をスクリプトで置き換えることはできない。

```text
#hello(World)
&hello(World);
```

PKRIPT_BIND を 0 にするとラッパー生成が無効になり、#pkript(hello) と &amp;pkript(hello); のみが有効な呼び出し口になる。

```php
// pukiwiki.ini.php
define('PKRIPT_BIND', 0);   // 既定は 1
```

exist_plugin のフォールバック処理を独自改造している環境では、exist_plugin 関数に以下を追加する。

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
スクリプトの配置場所によって信頼度が決まる。

| 置き場所 | 信頼度 | ページの書き込み |
| --- | --- | --- |
| ファイル（plugin/pkript/script/） | PKRIPT_TRUST_FILE | 可 |
| 凍結ページ（:config/pkript/script/） | PKRIPT_TRUST_FROZEN | 不可 |
| 編集可能ページ（:config/pkript/script/） | PKRIPT_TRUST_PAGE | 不可 |

読み出し・表示・計算はすべての信頼度で共通。書き込みのみ信頼度で制限される。

#### import と信頼度
import しても信頼度は昇格しない。

- 実行全体の信頼度は、関わったすべてのスクリプトの最低値になる。凍結ページがファイルのライブラリを読み込んでも、wiki.write の判定は凍結ページの信頼度で行われる。
- 自スクリプトより信頼度の低いスクリプトは読み込めない。PKRIPT_IMPORT_LOWER_TRUST を 1 にすると許可されるが、実行全体の信頼度は低い方に落ちる。

#### CSRFトークン
wiki.write と wiki.append は wiki.token() が返すトークンを要求する。トークンはログイン中のユーザーに紐付く。

認証を設定していないWikiではトークンはサイト共通の値になる。Wikiを読めるユーザーはトークンも読める。誰でも編集できるWikiで書き込みを制限するには $edit_auth_pages を使うこと。

### HTMLサニタイザ
スクリプトの出力HTMLは自動でサニタイズされる。

- script / iframe 等の危険なタグは無効化される。
- onclick 等のイベント属性は除去される。
- href / src の javascript: と data: スキームは除去される。
- class と id には自動で pkript- 接頭辞が付与される。

### style属性で使えるCSS

プロパティのホワイトリストと値のパターン検査を組み合わせて検証する。

| 分類 | 許可するプロパティ |
| --- | --- |
| 色・文字 | color, background-color, font-*, text-*, line-height, letter-spacing, word-spacing, white-space, word-break, overflow-wrap, list-style* |
| ボックス | margin*, padding*, border*, box-shadow, box-sizing, width, height, min-*, max-*, display, vertical-align, opacity, visibility, overflow*, float, clear, cursor |
| テーブル | border-collapse, border-spacing, table-layout, caption-side, outline* |
| Flexbox | flex*, justify-*, align-*, order, gap, row-gap, column-gap |
| Grid | grid-template-*, grid-auto-*, grid-area, grid-column, grid-row, place-* |
| アニメーション | transition*, animation*, transform, transform-origin, will-change, filter |

```text
<div style="transform:rotate(3deg); transition:all 0.3s ease-in-out">
```

禁止されるプロパティと値:

- position と z-index は禁止
- ビューポート単位（vw / vh）は禁止
- url(...) を含む値は禁止（background-image を含む）
- expression(...) / javascript: / @import / \ / /* を含む値は禁止
- 関数はホワイトリスト制。rgb / rgba / hsl / hsla、translate* / scale* / rotate* / skew* / matrix / perspective、cubic-bezier / steps、repeat / minmax / fit-content、blur などのfilter関数のみ許可。引数も同じ規則で検査される。calc() は現在非対応

規則に合わないプロパティは、そのプロパティのみ除去される（要素は残る）。

animation-name が参照するキーフレームはスクリプトから定義できない。&lt;style&gt; タグはサニタイザが除去するため、参照できるのはスキンが定義しているキーフレームのみ。
