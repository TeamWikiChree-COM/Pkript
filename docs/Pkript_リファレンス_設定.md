# Pkript/リファレンス/設定

plugin/pkript.inc.php の設定定数リファレンス。pukiwiki.ini.php で define した値が優先される。

## スクリプトの配置

| 定数名 | 型 | 既定値 | 説明 |
| --- | --- | --- | --- |
| PKRIPT_SCRIPT_DIR | string | 'plugin/pkript/script/' | ファイルスクリプトの配置ディレクトリ |
| PKRIPT_SCRIPT_EXT | string | 'pks,js' | 受け付ける拡張子（カンマ区切り、左が優先） |
| PKRIPT_ALLOW_PAGE_SCRIPT | int | 1 | ページスクリプト（:config/pkript/script/）を有効にするか |
| PKRIPT_PAGE_SCRIPT_FROZEN_ONLY | int | 0 | ページスクリプトを凍結ページのみに限定するか |
| PKRIPT_PAGE_PREFIX | string | ':config/pkript/script/' | ページスクリプトのページ名接頭辞 |
| PKRIPT_BIND | int | 1 | スクリプト名による直接呼び出し（#name 形式）を有効にするか |

PKRIPT_BIND を 0 にすると #pkript(name) と &amp;pkript(name); のみが有効になる（[#bind](%23bind.md) 参照）。

## データストア

| 定数名 | 型 | 既定値 | 説明 |
| --- | --- | --- | --- |
| PKRIPT_ALLOW_DATA | int | 1 | data.* によるデータ保存を有効にするか |
| PKRIPT_DATA_PREFIX | string | ':config/pkript/data/' | データページの接頭辞 |
| PKRIPT_DATA_MIN_TRUST | int | PKRIPT_WRITE_MIN_TRUST | data.set() に必要な最低信頼度 |

PKRIPT_ALLOW_DATA を 0 にすると data.get() は常に既定値を返し、data.set() は拒否される。

## 文法機能の有効・無効

| 定数名 | 型 | 既定値 | 説明 |
| --- | --- | --- | --- |
| PKRIPT_JSX | int | 1 | JSX記法（&lt;tag&gt;{expr}&lt;/tag&gt;）を有効にするか |
| PKRIPT_REGEX | int | 1 | 正規表現リテラル（/pattern/flags）を有効にするか |

- PKRIPT_JSX を 0 にすると &lt; は比較演算子としてのみ動作する（[Pkript/文法/JSX](Pkript_%E6%96%87%E6%B3%95_JSX.md) 参照）
- PKRIPT_REGEX を 0 にすると / は除算としてのみ動作する（[Pkript/文法/正規表現](Pkript_%E6%96%87%E6%B3%95_%E6%AD%A3%E8%A6%8F%E8%A1%A8%E7%8F%BE.md) 参照）

## 正規表現の制限

| 定数名 | 型 | 既定値 | 説明 |
| --- | --- | --- | --- |
| PKRIPT_REGEX_BACKTRACK | int | 100000 | 1回の照合で許すバックトラック量（ReDoS対策） |
| PKRIPT_MAX_REGEX | int | 512 | パターンの最大バイト数 |

## リクエスト単位の上限

1リクエスト内で複数のスクリプトが実行されても、以下は合計値として適用される。

| 定数名 | 型 | 既定値 | 説明 |
| --- | --- | --- | --- |
| PKRIPT_MAX_STEPS | int | 1000000 | 評価ステップ数 |
| PKRIPT_MAX_TIME | float | 3 | Pkript内での実行時間（秒） |
| PKRIPT_MAX_MEMORY | int | memory_limit の 3/4 | メモリ使用量（バイト） |
| PKRIPT_MAX_CONVERT | int | 32 | wiki.convert() の呼び出し回数 |
| PKRIPT_MAX_READS | int | 5000 | ページ参照回数（wiki.exists / source / pages / link の合計） |
| PKRIPT_MAX_WRITES | int | 4 | ページ書き込み回数（wiki.write / append の合計） |

## 値単位の上限

| 定数名 | 型 | 既定値 | 説明 |
| --- | --- | --- | --- |
| PKRIPT_MAX_DEPTH | int | 64 | 関数の最大呼び出し深度 |
| PKRIPT_MAX_LOOP | int | 100000 | 1ループの最大繰り返し回数 |
| PKRIPT_MAX_STRING | int | 1048576 | 文字列の最大バイト数（1MB） |
| PKRIPT_MAX_ARRAY | int | 10000 | 配列の最大要素数 |
| PKRIPT_MAX_PAGES | int | 1000 | wiki.pages() が返せる最大ページ数 |
| PKRIPT_MAX_PAGE_BYTES | int | 524288 | wiki.write() で書けるページの最大バイト数（512KB） |
| PKRIPT_MAX_IMPORTS | int | 16 | import できる最大スクリプト数 |
| PKRIPT_MAX_IMPORT_DEPTH | int | 4 | import の最大深度 |

## 権限・セキュリティ

| 定数名 | 型 | 既定値 | 説明 |
| --- | --- | --- | --- |
| PKRIPT_WRITE_MIN_TRUST | int | PKRIPT_TRUST_FILE | ページを書き込める最低の信頼度 |
| PKRIPT_IMPORT_LOWER_TRUST | int | 0 | 自スクリプトより低い信頼度のスクリプトの import を許可するか |
| PKRIPT_SECRET_FILE | string | CACHE_DIR . 'pkript_secret.dat' | CSRFトークン秘密鍵の保存場所 |

## デバッグ

| 定数名 | 型 | 既定値 | 説明 |
| --- | --- | --- | --- |
| PKRIPT_AST_CACHE | int | 1 | 解析済みスクリプトを cache/ に保存するか |
| PKRIPT_DEBUG | int | 1 | エラーに行番号・列番号を付与するか、console.log を出力するか |
| PKRIPT_MAX_LOG | int | 100 | console.log で記録できる行数 |
| PKRIPT_MAX_LOG_BYTES | int | 8192 | console.log 全体の最大バイト数 |

## 信頼度

スクリプトの配置場所によって信頼度が決まる。

| 配置場所 | 定数 | wiki.write の可否 |
| --- | --- | --- |
| plugin/pkript/script/（ファイル） | PKRIPT_TRUST_FILE | 可 |
| :config/pkript/script/（凍結ページ） | PKRIPT_TRUST_FROZEN | 不可 |
| :config/pkript/script/（編集可能ページ） | PKRIPT_TRUST_PAGE | 不可 |

読み出し・表示・計算はすべての信頼度で共通。書き込みのみ信頼度で制限される。

### import と信頼度

- 実行全体の信頼度は、関わったすべてのスクリプトの最低値になる
- 自スクリプトより低い信頼度のスクリプトは読み込めない（PKRIPT_IMPORT_LOWER_TRUST = 1 で許可できるが、実行全体の信頼度は低い方に落ちる）

## CSRFトークン

wiki.write / wiki.append はトークンを要求する。トークンはログイン中のユーザーに紐付く。

認証なしのWikiではトークンはサイト共通の値になる（Wikiを読めるユーザーはトークンも読める）。書き込み制限には $edit_auth_pages を使うこと。

## HTMLサニタイザ

スクリプトの出力HTMLは自動でサニタイズされる。

- script / iframe 等の危険なタグは無効化
- onclick 等のイベント属性は除去
- href / src の javascript: と data: スキームは除去
- class と id には pkript- 接頭辞が付与

### style属性

プロパティのホワイトリストと値のパターン検査で許可/拒否を判定する。

| 分類 | 許可するプロパティ |
| --- | --- |
| 色・文字 | color, background-color, font-*, text-*, line-height, letter-spacing, word-spacing, white-space, word-break, overflow-wrap, list-style* |
| ボックス | margin*, padding*, border*, box-shadow, box-sizing, width, height, min-*, max-*, display, vertical-align, opacity, visibility, overflow*, float, clear, cursor |
| テーブル | border-collapse, border-spacing, table-layout, caption-side, outline* |
| Flexbox | flex*, justify-*, align-*, order, gap, row-gap, column-gap |
| Grid | grid-template-*, grid-auto-*, grid-area, grid-column, grid-row, place-* |
| アニメーション | transition*, animation*, transform, transform-origin, will-change, filter |

禁止されるプロパティ・値:

| 禁止対象 | 理由 |
| --- | --- |
| position / z-index | Wikiレイアウト上に重ねることを防ぐため |
| vw / vh 等のビューポート単位 | ウィンドウ全体に広がることを防ぐため |
| url(...) を含む値 | 外部リソース参照を防ぐため（background-image を含む） |
| expression(...) / javascript: / @import / \ / /* | スクリプト注入を防ぐため |

関数はホワイトリスト制: rgb / rgba / hsl / hsla、translate* / scale* / rotate* / skew* / matrix / perspective、cubic-bezier / steps、repeat / minmax / fit-content、blur 等のfilter関数のみ許可。calc() は現在非対応。

規則に合わないプロパティはそのプロパティのみ除去される（要素は残る）。

animation-name が参照するキーフレームはスクリプトから定義できない（&lt;style&gt; タグはサニタイザが除去する）。

## #name による直接呼び出し

PKRIPT_BIND が 1（既定）のとき、#pkript(name) の代わりに #name と書ける。

lib/plugin.php の exist_plugin が実在するPHPプラグインを見つけられなかった場合のみ、同名のスクリプトを探してラッパー関数を生成する。同名のPHPプラグインは常に優先される。

```text
#hello(World)
&hello(World);
```

PKRIPT_BIND を 0 にするとラッパー生成を無効にする。

```php
// pukiwiki.ini.php
define('PKRIPT_BIND', 0);   // 既定は 1
```

exist_plugin を独自改造している環境では以下を exist_plugin 関数内に追加する:

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
